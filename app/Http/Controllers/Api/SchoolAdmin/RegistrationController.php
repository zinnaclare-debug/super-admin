<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassDepartment;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\LevelDepartment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\ClassTemplateSchema;
use App\Support\UserCredentialStore;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function enrollmentOptions(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;

        $payload = $request->validate([
            'education_level' => 'nullable|string|max:60',
        ]);

        $educationLevel = $this->normalizeEducationLevel($payload['education_level'] ?? null);
        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            return response()->json(['message' => 'Invalid education level selected.'], 422);
        }

        $session = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        if (!$session) {
            return response()->json([
                'data' => [
                    'current_session' => null,
                    'current_term' => null,
                    'classes' => [],
                ],
                'message' => 'No current academic session',
            ]);
        }

        $termQuery = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id);

        $currentTerm = null;
        if (Schema::hasColumn('terms', 'is_current')) {
            $currentTerm = (clone $termQuery)->where('is_current', true)->first();
        }
        if (!$currentTerm) {
            $currentTerm = (clone $termQuery)->orderBy('id')->first();
        }

        $classesQuery = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->orderBy('level')
            ->orderBy('name');

        if ($educationLevel !== null) {
            $classesQuery->whereRaw('LOWER(level) = ?', [$educationLevel]);
        }

        $classes = $classesQuery->get(['id', 'name', 'level', 'academic_session_id']);

        foreach ($classes as $class) {
            $this->syncClassDepartmentsFromLevel($schoolId, $class);
        }

        $classIds = $classes->pluck('id')->all();
        $departmentsByClass = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->when(!empty($classIds), function ($q) use ($classIds) {
                $q->whereIn('class_id', $classIds);
            })
            ->orderBy('name')
            ->get(['id', 'class_id', 'name'])
            ->groupBy('class_id');

        return response()->json([
            'data' => [
                'current_session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                    'status' => $session->status,
                ],
                'current_term' => $currentTerm ? [
                    'id' => (int) $currentTerm->id,
                    'name' => $currentTerm->name,
                ] : null,
                'classes' => $classes->map(function ($class) use ($departmentsByClass) {
                    return [
                        'id' => (int) $class->id,
                        'name' => $class->name,
                        'level' => strtolower(trim((string) $class->level)),
                        'departments' => collect($departmentsByClass->get($class->id, collect()))
                            ->map(fn ($department) => [
                                'id' => (int) $department->id,
                                'name' => $department->name,
                            ])
                            ->values()
                            ->all(),
                    ];
                })->values()->all(),
            ],
        ]);
    }

    public function preview(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = (int) $school->id;

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'password' => 'required|min:6',
            'education_level' => 'nullable|string|max:60',
            'class_id' => 'required_if:role,student|integer|exists:classes,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        if ($request->input('role') === 'student') {
            $placement = $this->resolveStudentPlacement(
                $schoolId,
                (int) $request->input('class_id'),
                $request->filled('department_id') ? (int) $request->input('department_id') : null,
                $educationLevel
            );
            if ($educationLevel === null) {
                $educationLevel = strtolower(trim((string) $placement['class']->level));
            }
        }

        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            return response()->json(['message' => 'Invalid education level selected.'], 422);
        }

        $username = $this->generateUsername($school, (string) $request->name);

        return response()->json([
            'username' => $username,
            'message' => 'Username generated successfully',
        ]);
    }

    public function confirm(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = (int) $school->id;

        $emailRule = $request->input('role') === 'staff'
            ? 'required|string|email|unique:users,email'
            : 'nullable|string|email|unique:users,email';

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'email' => $emailRule,
            'password' => 'required|min:6',
            'username' => 'required|string|unique:users,username',
            'education_level' => 'nullable|string|max:60',
            'class_id' => 'required_if:role,student|integer|exists:classes,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        $result = $this->createRegisteredUser($request, $schoolId, (string) $request->input('username'), $educationLevel);

        return response()->json([
            'message' => 'User registered successfully',
            'username' => $result['username'],
            'photo_url' => $this->storageUrl($result['photo_path']),
        ], 201);
    }

    public function register(Request $request)
    {
        $school = $request->user()->school;
        $schoolId = (int) $school->id;

        $emailRule = $request->input('role') === 'staff'
            ? 'required|string|email|unique:users,email'
            : 'nullable|string|email|unique:users,email';

        $request->validate([
            'role' => 'required|in:student,staff',
            'name' => 'required|string',
            'password' => 'required|min:6',
            'email' => $emailRule,
            'education_level' => 'nullable|string|max:60',
            'class_id' => 'required_if:role,student|integer|exists:classes,id',
            'department_id' => 'nullable|integer|exists:class_departments,id',
            'sex' => 'required_if:role,staff|nullable|in:M,F,male,female',
            'dob' => 'required_if:role,staff|nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $username = $this->generateUsername($school, (string) $request->name);
        $educationLevel = $this->normalizeEducationLevel($request->input('education_level'));
        $result = $this->createRegisteredUser($request, $schoolId, $username, $educationLevel);

        return response()->json([
            'message' => 'Registration successful',
            'username' => $result['username'],
            'photo_url' => $this->storageUrl($result['photo_path']),
        ], 201);
    }

    public function bulkTemplate(Request $request)
    {
        $headers = [
            'name',
            'email',
            'password',
            'username',
            'education_level',
            'class_name',
            'department_name',
            'sex',
            'religion',
            'dob',
            'address',
            'guardian_name',
            'guardian_email',
            'guardian_mobile',
            'guardian_location',
            'guardian_state_of_origin',
            'guardian_occupation',
            'guardian_relationship',
        ];

        $example = [
            'Amina Yusuf',
            'amina@example.com',
            'Pass1234',
            '',
            'secondary',
            'SS 1',
            'Science',
            'F',
            'Islam',
            '2012-01-15',
            '12 School Road',
            'Hafsat Yusuf',
            'hafsat@example.com',
            '08030000000',
            'Lagos',
            'Kano',
            'Trader',
            'mother',
        ];

        $encode = fn (array $row) => implode(',', array_map(function ($value) {
            $text = (string) $value;
            $escaped = str_replace('"', '""', $text);
            return "\"{$escaped}\"";
        }, $row));

        $csv = $encode($headers) . "\n" . $encode($example) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="student_bulk_template.csv"',
        ]);
    }

    public function bulkPreview(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $school = $request->user()->school;
        $schoolId = (int) $school->id;
        $currentSession = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        $parsed = $this->parseBulkCsv($request->file('csv'));
        $rows = $parsed['rows'];

        if (empty($rows)) {
            return response()->json([
                'message' => 'CSV file is empty.',
                'data' => [
                    'summary' => [
                        'total_rows' => 0,
                        'valid_rows' => 0,
                        'invalid_rows' => 0,
                    ],
                    'rows' => [],
                ],
            ], 422);
        }

        $usedUsernames = [];
        $usedEmails = [];
        $previewRows = [];
        $validRows = 0;
        $invalidRows = 0;

        foreach ($rows as $index => $row) {
            $validated = $this->validateBulkStudentRow(
                $school,
                $schoolId,
                $currentSession,
                $row,
                $usedUsernames,
                $usedEmails
            );

            if ($validated['ok']) {
                $validRows++;
            } else {
                $invalidRows++;
            }

            $previewRows[] = [
                'row_number' => $index + 2,
                'status' => $validated['ok'] ? 'valid' : 'invalid',
                'errors' => $validated['errors'],
                'data' => [
                    'name' => $validated['data']['name'] ?? null,
                    'email' => $validated['data']['email'] ?? null,
                    'username' => $validated['data']['username'] ?? null,
                    'education_level' => $validated['data']['education_level'] ?? null,
                    'class_name' => $validated['data']['class_name'] ?? null,
                    'department_name' => $validated['data']['department_name'] ?? null,
                ],
            ];
        }

        return response()->json([
            'message' => 'Bulk preview generated.',
            'data' => [
                'summary' => [
                    'total_rows' => count($rows),
                    'valid_rows' => $validRows,
                    'invalid_rows' => $invalidRows,
                ],
                'rows' => $previewRows,
            ],
        ]);
    }

    public function bulkConfirm(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $school = $request->user()->school;
        $schoolId = (int) $school->id;
        $currentSession = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();

        $parsed = $this->parseBulkCsv($request->file('csv'));
        $rows = $parsed['rows'];
        if (empty($rows)) {
            return response()->json([
                'message' => 'CSV file is empty.',
            ], 422);
        }

        $usedUsernames = [];
        $usedEmails = [];
        $validPayloads = [];
        $previewRows = [];
        $invalidRows = 0;

        foreach ($rows as $index => $row) {
            $validated = $this->validateBulkStudentRow(
                $school,
                $schoolId,
                $currentSession,
                $row,
                $usedUsernames,
                $usedEmails
            );

            $previewRows[] = [
                'row_number' => $index + 2,
                'status' => $validated['ok'] ? 'valid' : 'invalid',
                'errors' => $validated['errors'],
                'data' => [
                    'name' => $validated['data']['name'] ?? null,
                    'email' => $validated['data']['email'] ?? null,
                    'username' => $validated['data']['username'] ?? null,
                    'education_level' => $validated['data']['education_level'] ?? null,
                    'class_name' => $validated['data']['class_name'] ?? null,
                    'department_name' => $validated['data']['department_name'] ?? null,
                ],
            ];

            if ($validated['ok']) {
                $validPayloads[] = [
                    'row_number' => $index + 2,
                    'data' => $validated['data'],
                ];
            } else {
                $invalidRows++;
            }
        }

        if ($invalidRows > 0) {
            return response()->json([
                'message' => 'CSV has invalid rows. Fix errors and preview again before confirm.',
                'data' => [
                    'summary' => [
                        'total_rows' => count($rows),
                        'valid_rows' => count($validPayloads),
                        'invalid_rows' => $invalidRows,
                    ],
                    'rows' => $previewRows,
                ],
            ], 422);
        }

        $actorUserId = (int) $request->user()->id;
        $createdRows = [];

        DB::transaction(function () use ($schoolId, $actorUserId, $validPayloads, &$createdRows) {
            foreach ($validPayloads as $item) {
                $data = $item['data'];

                $user = User::create([
                    'school_id' => $schoolId,
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => 'student',
                ]);

                UserCredentialStore::sync($user, $data['password'], $actorUserId);

                $studentPayload = [
                    'user_id' => $user->id,
                    'school_id' => $schoolId,
                    'sex' => $data['sex'],
                    'religion' => $data['religion'],
                    'dob' => $data['dob'],
                    'address' => $data['address'],
                ];
                if (Schema::hasColumn('students', 'education_level')) {
                    $studentPayload['education_level'] = $data['education_level'];
                }

                $student = Student::create($studentPayload);
                $class = SchoolClass::query()
                    ->where('school_id', $schoolId)
                    ->where('id', (int) $data['class_id'])
                    ->firstOrFail();
                $this->enrollStudentInClassSession(
                    $schoolId,
                    $student,
                    $class,
                    $data['session_term_ids'],
                    $data['department_id']
                );

                if (!empty($data['guardian_name'])) {
                    Guardian::create([
                        'school_id' => $schoolId,
                        'user_id' => $user->id,
                        'name' => $data['guardian_name'],
                        'email' => $data['guardian_email'],
                        'mobile' => $data['guardian_mobile'],
                        'location' => $data['guardian_location'],
                        'state_of_origin' => $data['guardian_state_of_origin'],
                        'occupation' => $data['guardian_occupation'],
                        'relationship' => $data['guardian_relationship'],
                    ]);
                }

                $createdRows[] = [
                    'row_number' => $item['row_number'],
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'class_name' => $data['class_name'],
                    'department_name' => $data['department_name'],
                ];
            }
        });

        return response()->json([
            'message' => 'Bulk student registration completed successfully.',
            'data' => [
                'summary' => [
                    'imported_rows' => count($createdRows),
                ],
                'credentials' => $createdRows,
            ],
        ], 201);
    }

    private function parseBulkCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (!$path) {
            throw ValidationException::withMessages([
                'csv' => ['Unable to read uploaded CSV file.'],
            ]);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'csv' => ['Unable to open uploaded CSV file.'],
            ]);
        }

        try {
            $headerRow = fgetcsv($handle);
            if (!is_array($headerRow) || empty($headerRow)) {
                throw ValidationException::withMessages([
                    'csv' => ['CSV must include a header row.'],
                ]);
            }

            $headers = array_map(fn ($value) => $this->normalizeCsvHeader((string) $value), $headerRow);
            if (!$this->csvHasHeader($headers, ['name', 'full_name', 'student_name'])) {
                throw ValidationException::withMessages([
                    'csv' => ['CSV header must include "name".'],
                ]);
            }
            if (!$this->csvHasHeader($headers, ['password', 'passcode'])) {
                throw ValidationException::withMessages([
                    'csv' => ['CSV header must include "password".'],
                ]);
            }
            if (
                !$this->csvHasHeader($headers, ['class_name', 'class']) &&
                !$this->csvHasHeader($headers, ['class_id'])
            ) {
                throw ValidationException::withMessages([
                    'csv' => ['CSV header must include "class_name" or "class_id".'],
                ]);
            }

            $rows = [];
            while (($values = fgetcsv($handle)) !== false) {
                if ($values === [null] || empty($values)) {
                    continue;
                }

                $values = array_pad($values, count($headers), null);
                $values = array_slice($values, 0, count($headers));
                $row = array_combine($headers, $values);
                if (!$row) {
                    continue;
                }

                $nonEmpty = collect($row)->contains(fn ($value) => trim((string) $value) !== '');
                if (!$nonEmpty) {
                    continue;
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function validateBulkStudentRow(
        School $school,
        int $schoolId,
        ?AcademicSession $currentSession,
        array $row,
        array &$usedUsernames,
        array &$usedEmails
    ): array {
        $errors = [];

        $name = $this->csvValue($row, ['name', 'full_name', 'student_name']);
        if (!$name) {
            $errors[] = 'Name is required.';
        }

        $password = $this->csvValue($row, ['password', 'passcode']);
        if (!$password) {
            $errors[] = 'Password is required.';
        } elseif (mb_strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        $email = $this->csvValue($row, ['email', 'student_email']);
        $normalizedEmail = null;
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email format is invalid.';
            } else {
                $normalizedEmail = strtolower($email);
                if (in_array($normalizedEmail, $usedEmails, true)) {
                    $errors[] = 'Email is duplicated in CSV.';
                } elseif (
                    User::query()
                        ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                        ->exists()
                ) {
                    $errors[] = 'Email already exists.';
                }
            }
        }

        if (!$currentSession) {
            $errors[] = 'No current academic session is configured.';
        }

        $educationLevel = $this->normalizeEducationLevel($this->csvValue($row, ['education_level', 'level']));

        $classIdInput = $this->csvValue($row, ['class_id']);
        $classNameInput = $this->csvValue($row, ['class_name', 'class']);

        $class = null;
        if ($currentSession) {
            $classQuery = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $currentSession->id);

            if ($classIdInput !== null) {
                if (!ctype_digit($classIdInput)) {
                    $errors[] = 'Class ID must be numeric.';
                } else {
                    $class = (clone $classQuery)
                        ->where('id', (int) $classIdInput)
                        ->first();
                }
            } elseif ($classNameInput !== null) {
                $class = (clone $classQuery)
                    ->whereRaw('LOWER(name) = ?', [strtolower($classNameInput)])
                    ->first();
            } else {
                $errors[] = 'Class is required (class_name or class_id).';
            }

            if (!$class && ($classIdInput !== null || $classNameInput !== null)) {
                $errors[] = 'Class not found in current session.';
            }
        }

        $departmentId = null;
        $departmentName = null;
        $sessionTermIds = [];

        if ($class) {
            $classLevel = strtolower(trim((string) $class->level));
            if ($educationLevel !== null && $educationLevel !== $classLevel) {
                $errors[] = 'Education level does not match selected class level.';
            }
            $educationLevel = $classLevel;

            if (!$this->isValidEducationLevel($schoolId, $educationLevel)) {
                $errors[] = 'Education level is invalid.';
            }

            $sessionTermIds = Term::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $class->academic_session_id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            if (empty($sessionTermIds)) {
                $errors[] = 'No terms found for selected class session.';
            }

            $this->syncClassDepartmentsFromLevel($schoolId, $class);
            $classDepartments = ClassDepartment::query()
                ->where('school_id', $schoolId)
                ->where('class_id', (int) $class->id)
                ->orderBy('name')
                ->get(['id', 'name']);

            $departmentIdInput = $this->csvValue($row, ['department_id']);
            $departmentNameInput = $this->csvValue($row, ['department_name', 'department']);

            if ($classDepartments->isNotEmpty()) {
                if ($departmentIdInput !== null) {
                    if (!ctype_digit($departmentIdInput)) {
                        $errors[] = 'Department ID must be numeric.';
                    } else {
                        $resolved = $classDepartments->first(
                            fn ($department) => (int) $department->id === (int) $departmentIdInput
                        );
                        if (!$resolved) {
                            $errors[] = 'Department ID is invalid for selected class.';
                        } else {
                            $departmentId = (int) $resolved->id;
                            $departmentName = (string) $resolved->name;
                        }
                    }
                } elseif ($departmentNameInput !== null) {
                    $resolved = $classDepartments->first(
                        fn ($department) => strtolower((string) $department->name) === strtolower($departmentNameInput)
                    );
                    if (!$resolved) {
                        $errors[] = 'Department name is invalid for selected class.';
                    } else {
                        $departmentId = (int) $resolved->id;
                        $departmentName = (string) $resolved->name;
                    }
                } else {
                    $errors[] = 'Department is required for selected class.';
                }
            } elseif ($departmentIdInput !== null || $departmentNameInput !== null) {
                $errors[] = 'Selected class has no departments configured.';
            }
        }

        $username = $this->csvValue($row, ['username', 'user_name', 'login_username']);
        if ($username !== null) {
            if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
                $errors[] = 'Username may only contain letters, numbers, dot, underscore, and hyphen.';
            } else {
                $normalizedUsername = strtolower($username);
                if (in_array($normalizedUsername, $usedUsernames, true)) {
                    $errors[] = 'Username is duplicated in CSV.';
                } elseif (
                    User::query()
                        ->whereRaw('LOWER(username) = ?', [$normalizedUsername])
                        ->exists()
                ) {
                    $errors[] = 'Username already exists.';
                }
            }
        } elseif ($name) {
            $username = $this->generateBulkUsername($school, $name, $usedUsernames);
        }

        $sex = $this->csvValue($row, ['sex', 'gender']);
        if ($sex !== null) {
            $sexKey = strtolower($sex);
            if (in_array($sexKey, ['m', 'male'], true)) {
                $sex = 'M';
            } elseif (in_array($sexKey, ['f', 'female'], true)) {
                $sex = 'F';
            } else {
                $errors[] = 'Sex must be M, F, male, or female.';
            }
        }

        $dob = $this->csvValue($row, ['dob', 'date_of_birth']);
        if ($dob !== null) {
            $timestamp = strtotime($dob);
            if ($timestamp === false) {
                $errors[] = 'Date of birth is invalid.';
            } else {
                $dob = date('Y-m-d', $timestamp);
            }
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'username' => $username,
            'education_level' => $educationLevel,
            'class_id' => $class ? (int) $class->id : null,
            'class_name' => $class ? (string) $class->name : null,
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'session_term_ids' => $sessionTermIds,
            'sex' => $sex,
            'religion' => $this->csvValue($row, ['religion']),
            'dob' => $dob,
            'address' => $this->csvValue($row, ['address']),
            'guardian_name' => $this->csvValue($row, ['guardian_name']),
            'guardian_email' => $this->csvValue($row, ['guardian_email']),
            'guardian_mobile' => $this->csvValue($row, ['guardian_mobile', 'guardian_phone']),
            'guardian_location' => $this->csvValue($row, ['guardian_location']),
            'guardian_state_of_origin' => $this->csvValue($row, ['guardian_state_of_origin']),
            'guardian_occupation' => $this->csvValue($row, ['guardian_occupation']),
            'guardian_relationship' => $this->csvValue($row, ['guardian_relationship']),
        ];

        $ok = empty($errors);
        if ($ok) {
            if ($username !== null) {
                $usedUsernames[] = strtolower($username);
            }
            if ($normalizedEmail !== null) {
                $usedEmails[] = $normalizedEmail;
            }
        }

        return [
            'ok' => $ok,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/i', '_', $header);
        return trim((string) $header, '_');
    }

    private function csvHasHeader(array $headers, array $accepted): bool
    {
        foreach ($accepted as $key) {
            if (in_array($key, $headers, true)) {
                return true;
            }
        }

        return false;
    }

    private function csvValue(array $row, array $accepted): ?string
    {
        foreach ($accepted as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function generateBulkUsername(School $school, string $name, array $usedUsernames): string
    {
        $candidate = $this->generateUsername($school, $name);
        if (!$this->bulkUsernameTaken($candidate, $usedUsernames)) {
            return $candidate;
        }

        if (preg_match('/^(.*?)(\d+)$/', $candidate, $matches)) {
            $stem = (string) $matches[1];
            $number = (int) $matches[2];
        } else {
            $stem = $candidate;
            $number = 1;
        }

        do {
            $number++;
            $candidate = "{$stem}{$number}";
        } while ($this->bulkUsernameTaken($candidate, $usedUsernames));

        return $candidate;
    }

    private function bulkUsernameTaken(string $username, array $usedUsernames): bool
    {
        $normalized = strtolower($username);
        if (in_array($normalized, $usedUsernames, true)) {
            return true;
        }

        return User::query()
            ->whereRaw('LOWER(username) = ?', [$normalized])
            ->exists();
    }

    private function createRegisteredUser(
        Request $request,
        int $schoolId,
        string $username,
        ?string $educationLevel
    ): array {
        $studentPlacement = null;
        if ($request->input('role') === 'student') {
            $studentPlacement = $this->resolveStudentPlacement(
                $schoolId,
                (int) $request->input('class_id'),
                $request->filled('department_id') ? (int) $request->input('department_id') : null,
                $educationLevel
            );

            if ($educationLevel === null) {
                $educationLevel = strtolower(trim((string) $studentPlacement['class']->level));
            }
        }

        if ($educationLevel !== null && !$this->isValidEducationLevel($schoolId, $educationLevel)) {
            throw ValidationException::withMessages([
                'education_level' => ['Invalid education level selected.'],
            ]);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $dir = "schools/{$schoolId}/profiles";
            $ext = $request->file('photo')->getClientOriginalExtension();
            $filename = $username . '.' . $ext;
            $photoPath = $request->file('photo')->storeAs($dir, $filename, 'public');
        }

        try {
            DB::transaction(function () use (
                $request,
                $schoolId,
                $username,
                $educationLevel,
                $photoPath,
                $studentPlacement
            ) {
                $user = User::create([
                    'school_id' => $schoolId,
                    'name' => $request->input('name'),
                    'username' => $username,
                    'email' => $request->input('email'),
                    'photo_path' => $photoPath,
                    'password' => Hash::make((string) $request->input('password')),
                    'role' => $request->input('role'),
                ]);

                UserCredentialStore::sync(
                    $user,
                    (string) $request->input('password'),
                    (int) $request->user()->id
                );

                if ($request->input('role') === 'student') {
                    $studentPayload = [
                        'user_id' => $user->id,
                        'school_id' => $schoolId,
                        'sex' => $request->input('sex'),
                        'religion' => $request->input('religion'),
                        'dob' => $request->input('dob'),
                        'address' => $request->input('address'),
                        'photo_path' => $photoPath,
                    ];
                    if (Schema::hasColumn('students', 'education_level')) {
                        $studentPayload['education_level'] = $educationLevel;
                    }

                    $student = Student::create($studentPayload);
                    if ($studentPlacement) {
                        $this->enrollStudentInClassSession(
                            $schoolId,
                            $student,
                            $studentPlacement['class'],
                            $studentPlacement['session_term_ids'],
                            $studentPlacement['department_id']
                        );
                    }
                } else {
                    Staff::create([
                        'user_id' => $user->id,
                        'school_id' => $schoolId,
                        'sex' => $request->input('sex') ?? null,
                        'dob' => $request->input('dob') ?? null,
                        'address' => $request->input('address') ?? null,
                        'position' => $request->input('staff_position') ?? null,
                        'education_level' => $educationLevel,
                        'photo_path' => $photoPath,
                    ]);
                }

                if ($request->filled('guardian_name')) {
                    Guardian::create([
                        'school_id' => $schoolId,
                        'user_id' => $user->id,
                        'name' => $request->input('guardian_name'),
                        'email' => $request->input('guardian_email'),
                        'mobile' => $request->input('guardian_mobile'),
                        'location' => $request->input('guardian_location'),
                        'state_of_origin' => $request->input('guardian_state_of_origin'),
                        'occupation' => $request->input('guardian_occupation'),
                        'relationship' => $request->input('guardian_relationship'),
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            throw $exception;
        }

        return [
            'username' => $username,
            'photo_path' => $photoPath,
        ];
    }

    private function resolveStudentPlacement(
        int $schoolId,
        int $classId,
        ?int $departmentId,
        ?string $educationLevel
    ): array {
        $class = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('id', $classId)
            ->first();

        if (!$class) {
            throw ValidationException::withMessages([
                'class_id' => ['Invalid class selected.'],
            ]);
        }

        $classLevel = strtolower(trim((string) $class->level));
        if ($educationLevel !== null && $educationLevel !== $classLevel) {
            throw ValidationException::withMessages([
                'class_id' => ['Selected class does not match the chosen education level.'],
            ]);
        }

        $sessionTermIds = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($sessionTermIds)) {
            throw ValidationException::withMessages([
                'class_id' => ['No terms found for the selected class session.'],
            ]);
        }

        $this->syncClassDepartmentsFromLevel($schoolId, $class);

        $classDepartments = ClassDepartment::query()
            ->where('school_id', $schoolId)
            ->where('class_id', $class->id)
            ->orderBy('name')
            ->get(['id']);

        $resolvedDepartmentId = null;
        if ($classDepartments->isNotEmpty()) {
            if (!$departmentId) {
                throw ValidationException::withMessages([
                    'department_id' => ['Select department for the selected class.'],
                ]);
            }

            $departmentExists = $classDepartments
                ->pluck('id')
                ->contains((int) $departmentId);

            if (!$departmentExists) {
                throw ValidationException::withMessages([
                    'department_id' => ['Invalid department selected for this class.'],
                ]);
            }

            $resolvedDepartmentId = (int) $departmentId;
        } elseif ($departmentId) {
            throw ValidationException::withMessages([
                'department_id' => ['Selected class has no departments configured.'],
            ]);
        }

        return [
            'class' => $class,
            'department_id' => $resolvedDepartmentId,
            'session_term_ids' => $sessionTermIds,
        ];
    }

    private function enrollStudentInClassSession(
        int $schoolId,
        Student $student,
        SchoolClass $class,
        array $sessionTermIds,
        ?int $departmentId
    ): void {
        $currentSessionTermId = $this->resolveCurrentSessionTermId($schoolId, (int) $class->academic_session_id);
        if ($currentSessionTermId) {
            $existingClassId = $this->resolveCurrentTermEnrollmentClassId(
                $schoolId,
                (int) $student->id,
                (int) $currentSessionTermId
            );

            if ($existingClassId && (int) $existingClassId !== (int) $class->id) {
                throw ValidationException::withMessages([
                    'class_id' => ['Student already has a class in the current term/session.'],
                ]);
            }
        }

        DB::table('class_students')->updateOrInsert([
            'school_id' => $schoolId,
            'academic_session_id' => $class->academic_session_id,
            'class_id' => $class->id,
            'student_id' => $student->id,
        ], [
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        foreach ($sessionTermIds as $termId) {
            $where = [
                'student_id' => $student->id,
                'class_id' => $class->id,
                'term_id' => (int) $termId,
            ];

            if (Schema::hasColumn('enrollments', 'school_id')) {
                $where['school_id'] = $schoolId;
            }

            $exists = Enrollment::query()->where($where)->exists();
            if ($exists) {
                Enrollment::query()->where($where)->update([
                    'department_id' => $departmentId,
                    'updated_at' => now(),
                ]);
                continue;
            }

            Enrollment::create([
                ...$where,
                'department_id' => $departmentId,
            ]);
        }
    }

    private function resolveCurrentSessionTermId(int $schoolId, int $academicSessionId): ?int
    {
        $isCurrentSession = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('id', $academicSessionId)
            ->where('status', 'current')
            ->exists();

        if (!$isCurrentSession) {
            return null;
        }

        $termQuery = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $academicSessionId);

        if (Schema::hasColumn('terms', 'is_current')) {
            $current = (clone $termQuery)->where('is_current', true)->value('id');
            if ($current) {
                return (int) $current;
            }
        }

        $fallback = (clone $termQuery)->orderBy('id')->value('id');
        return $fallback ? (int) $fallback : null;
    }

    private function resolveCurrentTermEnrollmentClassId(int $schoolId, int $studentId, int $termId): ?int
    {
        $query = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->orderByDesc('id');

        if (Schema::hasColumn('enrollments', 'school_id')) {
            $query->where('school_id', $schoolId);
        }

        $classId = $query->value('class_id');
        return $classId ? (int) $classId : null;
    }

    private function syncClassDepartmentsFromLevel(int $schoolId, SchoolClass $class): void
    {
        $levelDepartments = LevelDepartment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $class->academic_session_id)
            ->where('level', $class->level)
            ->get(['name']);

        foreach ($levelDepartments as $department) {
            ClassDepartment::firstOrCreate([
                'school_id' => $schoolId,
                'class_id' => $class->id,
                'name' => $department->name,
            ]);
        }
    }

    private function generateUsername($school, string $fullName): string
    {
        $prefix = strtoupper((string) $school->username_prefix);
        $surname = strtolower(explode(' ', trim($fullName))[0]);

        $lastUser = User::query()
            ->where('school_id', $school->id)
            ->where('username', 'LIKE', "{$prefix}-{$surname}%")
            ->orderByDesc('id')
            ->first();

        if ($lastUser) {
            preg_match('/(\d+)$/', (string) $lastUser->username, $matches);
            $number = isset($matches[1]) ? ((int) $matches[1] + 1) : 1;
        } else {
            $number = 1;
        }

        return "{$prefix}-{$surname}{$number}";
    }

    private function normalizeEducationLevel(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        return $normalized !== '' ? $normalized : null;
    }

    private function isValidEducationLevel(int $schoolId, string $level): bool
    {
        $normalizedLevel = strtolower(trim($level));
        if ($normalizedLevel === '') {
            return false;
        }

        $existsInClasses = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(level) = ?', [$normalizedLevel])
            ->exists();
        if ($existsInClasses) {
            return true;
        }

        $school = School::query()->find($schoolId);
        if (!$school) {
            return false;
        }

        $activeTemplateLevels = ClassTemplateSchema::activeLevelKeys(
            ClassTemplateSchema::normalize($school->class_templates)
        );

        return in_array($normalizedLevel, $activeTemplateLevels, true);
    }

    private function storageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);
        return str_starts_with($relativeOrAbsolute, 'http://')
            || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
    }
}
