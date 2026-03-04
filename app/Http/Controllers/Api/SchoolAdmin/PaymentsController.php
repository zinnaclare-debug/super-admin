<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolFeePayment;
use App\Models\SchoolFeeSetting;
use App\Models\Student;
use App\Models\StudentFeePlan;
use App\Models\Term;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PaymentsController extends Controller
{
    public function config(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $activeLevels = $this->extractSessionLevels($schoolId, $session->id, $session->levels ?? []);
        $settings = SchoolFeeSetting::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->get();
        $settingsByLevel = $settings
            ->filter(fn ($row) => !empty($row->level))
            ->keyBy(fn ($row) => strtolower(trim((string) $row->level)));
        $feesByLevel = [];
        foreach ($activeLevels as $level) {
            $feesByLevel[$level] = isset($settingsByLevel[$level])
                ? (float) $settingsByLevel[$level]->amount_due
                : null;
        }
        $legacySetting = $settings->first(fn ($row) => empty($row->level));
        $school = School::where('id', $schoolId)->first();

        return response()->json([
            'data' => [
                'current_session' => [
                    'id' => $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'current_term' => [
                    'id' => $term->id,
                    'name' => $term->name,
                ],
                'active_levels' => $activeLevels,
                'fees_by_level' => $feesByLevel,
                'legacy_amount_due' => $legacySetting ? (float) $legacySetting->amount_due : null,
                'paystack_subaccount_code' => $school?->paystack_subaccount_code,
            ],
        ]);
    }

    public function upsertConfig(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $payload = $request->validate([
            'fees_by_level' => 'sometimes|array',
            'fees_by_level.*' => 'nullable|numeric|min:0',
            'amount_due' => 'sometimes|numeric|min:0',
            'paystack_subaccount_code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
        ]);

        $activeLevels = $this->extractSessionLevels($schoolId, $session->id, $session->levels ?? []);
        $allowedLevelSet = collect($activeLevels)->flip();

        $normalizedFees = [];
        if (array_key_exists('fees_by_level', $payload)) {
            foreach ((array) $payload['fees_by_level'] as $level => $amount) {
                $normalizedLevel = strtolower(trim((string) $level));
                if (!$allowedLevelSet->has($normalizedLevel)) {
                    return response()->json([
                        'message' => "Invalid level '{$level}' for this session.",
                    ], 422);
                }
                if ($amount === null || $amount === '') {
                    continue;
                }
                $normalizedFees[$normalizedLevel] = round((float) $amount, 2);
            }
        }

        DB::transaction(function () use (
            $schoolId,
            $session,
            $term,
            $request,
            $normalizedFees,
            $payload
        ) {
            SchoolFeeSetting::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->where('term_id', $term->id)
                ->delete();

            if (!empty($normalizedFees)) {
                foreach ($normalizedFees as $level => $amountDue) {
                    SchoolFeeSetting::create([
                        'school_id' => $schoolId,
                        'academic_session_id' => $session->id,
                        'term_id' => $term->id,
                        'level' => $level,
                        'amount_due' => $amountDue,
                        'set_by_user_id' => $request->user()->id,
                    ]);
                }
            } elseif (array_key_exists('amount_due', $payload)) {
                // Legacy fallback for old clients still sending a single amount.
                SchoolFeeSetting::create([
                    'school_id' => $schoolId,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'level' => null,
                    'amount_due' => round((float) $payload['amount_due'], 2),
                    'set_by_user_id' => $request->user()->id,
                ]);
            }
        });

        $school = School::where('id', $schoolId)->first();
        if ($school) {
            $school->paystack_subaccount_code = trim((string) ($payload['paystack_subaccount_code'] ?? '')) ?: null;
            $school->save();
        }

        $saved = SchoolFeeSetting::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('term_id', $term->id)
            ->get();
        $savedByLevel = $saved
            ->filter(fn ($row) => !empty($row->level))
            ->keyBy(fn ($row) => strtolower(trim((string) $row->level)));
        $feesByLevel = [];
        foreach ($activeLevels as $level) {
            $feesByLevel[$level] = isset($savedByLevel[$level])
                ? (float) $savedByLevel[$level]->amount_due
                : null;
        }

        return response()->json([
            'message' => 'Payment settings saved.',
            'data' => [
                'active_levels' => $activeLevels,
                'fees_by_level' => $feesByLevel,
                'paystack_subaccount_code' => $school?->paystack_subaccount_code,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $view = strtolower(trim((string) $request->query('view', 'payments')));
        $view = $view === 'outstanding' ? 'outstanding' : 'payments';

        $status = $this->normalizeStatusFilter((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));
        $level = trim((string) $request->query('level', ''));
        $class = trim((string) $request->query('class', ''));
        $department = trim((string) $request->query('department', ''));
        $perPage = max(1, (int) $request->query('per_page', 15));
        $page = max(1, (int) $request->query('page', 1));

        if ($view === 'outstanding') {
            $baseRows = $this->buildOutstandingRows($schoolId, (int) $session->id, (int) $term->id, $search);
        } else {
            $baseRows = $this->buildPaymentRows($schoolId, (int) $session->id, (int) $term->id, $search, $status);
        }

        $filterOptions = $this->extractFilterOptions($baseRows);
        $filteredRows = $this->applyCommonFilters($baseRows, $level, $class, $department);
        $paginator = $this->paginateRows($filteredRows, $perPage, $page);

        $totalPaid = (float) collect($filteredRows)->sum(function ($row) use ($view) {
            return $view === 'outstanding'
                ? (float) ($row['amount_paid'] ?? 0)
                : ((($row['status'] ?? '') === 'success') ? (float) ($row['amount_paid'] ?? 0) : 0);
        });
        $totalOutstanding = (float) collect($filteredRows)->sum(fn ($row) => (float) ($row['amount_outstanding'] ?? 0));

        return response()->json([
            'data' => array_values($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'context' => [
                'view' => $view,
                'status' => $status,
                'current_session' => [
                    'id' => $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'current_term' => [
                    'id' => $term->id,
                    'name' => $term->name,
                ],
                'active_levels' => $this->extractSessionLevels($schoolId, $session->id, $session->levels ?? []),
                'filter_options' => $filterOptions,
                'totals' => [
                    'paid' => $totalPaid,
                    'outstanding' => $totalOutstanding,
                ],
            ],
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $payload = $request->validate([
            'view' => 'nullable|string|in:payments,outstanding',
            'status' => 'nullable|string',
            'level' => 'nullable|string|max:120',
            'class' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:120',
            'search' => 'nullable|string|max:120',
        ]);

        $view = strtolower(trim((string) ($payload['view'] ?? 'payments')));
        $view = $view === 'outstanding' ? 'outstanding' : 'payments';
        $status = $this->normalizeStatusFilter((string) ($payload['status'] ?? 'all'));
        $level = trim((string) ($payload['level'] ?? ''));
        $class = trim((string) ($payload['class'] ?? ''));
        $department = trim((string) ($payload['department'] ?? ''));
        $search = trim((string) ($payload['search'] ?? ''));

        if ($view === 'outstanding') {
            $baseRows = $this->buildOutstandingRows($schoolId, (int) $session->id, (int) $term->id, $search);
        } else {
            $baseRows = $this->buildPaymentRows($schoolId, (int) $session->id, (int) $term->id, $search, $status);
        }
        $rows = $this->applyCommonFilters($baseRows, $level, $class, $department);
        $rows = collect($rows)->values()->map(function ($row, $idx) {
            $row['sn'] = $idx + 1;
            return $row;
        })->all();

        $school = School::query()->find($schoolId);
        $totals = [
            'paid' => (float) collect($rows)->sum(fn ($row) => (float) ($row['amount_paid'] ?? 0)),
            'outstanding' => (float) collect($rows)->sum(fn ($row) => (float) ($row['amount_outstanding'] ?? 0)),
        ];

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.school_admin_payments_summary', [
                'school' => $school,
                'session' => $session,
                'term' => $term,
                'viewMode' => $view,
                'statusMode' => $status,
                'rows' => $rows,
                'totals' => $totals,
                'filters' => [
                    'level' => $level,
                    'class' => $class,
                    'department' => $department,
                    'search' => $search,
                ],
                'generatedAt' => now(),
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            $fileName = 'payments_' . $view . '_' . now()->format('Ymd_His') . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to generate payments PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function studentPlan(Request $request, User $user)
    {
        $schoolId = (int) $request->user()->school_id;
        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Student not found.'], 404);
        }
        if ($user->role !== 'student') {
            return response()->json(['message' => 'Selected user is not a student.'], 422);
        }

        $student = Student::query()
            ->where('school_id', $schoolId)
            ->where('user_id', (int) $user->id)
            ->first();
        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $level = $this->resolveStudentLevel(
            $schoolId,
            (int) $student->id,
            (int) $session->id,
            (int) $term->id
        );
        $setting = $this->resolveFeeSetting(
            $schoolId,
            (int) $session->id,
            (int) $term->id,
            $level
        );

        $plan = StudentFeePlan::query()
            ->where('school_id', $schoolId)
            ->where('student_id', (int) $student->id)
            ->where('academic_session_id', (int) $session->id)
            ->where('term_id', (int) $term->id)
            ->first();

        $placement = $this->resolveStudentPlacement((int) $student->id, (int) $term->id);

        return response()->json([
            'data' => [
                'current_session' => [
                    'id' => (int) $session->id,
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                ],
                'current_term' => [
                    'id' => (int) $term->id,
                    'name' => $term->name,
                ],
                'student' => [
                    'id' => (int) $student->id,
                    'user_id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'username' => (string) $user->username,
                    'education_level' => $level,
                    'class_name' => $placement['class_name'],
                    'department_name' => $placement['department_name'],
                ],
                'plan' => [
                    'has_custom_plan' => $plan !== null,
                    'line_items' => $plan ? $this->normalizeLineItems((array) ($plan->line_items ?? []), 10) : [],
                    'amount_due' => $plan ? (float) $plan->amount_due : null,
                ],
                'fallback' => [
                    'amount_due' => $setting ? (float) $setting->amount_due : 0,
                    'configured_level' => $setting?->level,
                ],
            ],
        ]);
    }

    public function upsertStudentPlan(Request $request, User $user)
    {
        $schoolId = (int) $request->user()->school_id;
        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Student not found.'], 404);
        }
        if ($user->role !== 'student') {
            return response()->json(['message' => 'Selected user is not a student.'], 422);
        }

        $student = Student::query()
            ->where('school_id', $schoolId)
            ->where('user_id', (int) $user->id)
            ->first();
        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$session || !$term) {
            return response()->json([
                'message' => 'No current academic session/term configured.',
            ], 422);
        }

        $payload = $request->validate([
            'line_items' => ['required', 'array', 'max:10'],
            'line_items.*.enabled' => ['nullable', 'boolean'],
            'line_items.*.description' => ['nullable', 'string', 'max:120'],
            'line_items.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lineItems = $this->normalizeLineItems((array) ($payload['line_items'] ?? []), 10);
        if (empty($lineItems)) {
            StudentFeePlan::query()
                ->where('school_id', $schoolId)
                ->where('student_id', (int) $student->id)
                ->where('academic_session_id', (int) $session->id)
                ->where('term_id', (int) $term->id)
                ->delete();

            return response()->json([
                'message' => 'Custom student payment plan cleared. Student will use level-based school fee.',
                'data' => [
                    'has_custom_plan' => false,
                    'line_items' => [],
                    'amount_due' => null,
                ],
            ]);
        }

        $amountDue = round((float) collect($lineItems)
            ->filter(fn ($row) => (bool) ($row['enabled'] ?? false))
            ->sum('amount'), 2);

        if ($amountDue <= 0) {
            return response()->json([
                'message' => 'Select at least one checked fee item with amount greater than zero.',
            ], 422);
        }

        $plan = StudentFeePlan::query()->updateOrCreate(
            [
                'school_id' => $schoolId,
                'student_id' => (int) $student->id,
                'academic_session_id' => (int) $session->id,
                'term_id' => (int) $term->id,
            ],
            [
                'line_items' => $lineItems,
                'amount_due' => $amountDue,
                'configured_by_user_id' => (int) $request->user()->id,
            ]
        );

        return response()->json([
            'message' => 'Student payment plan saved.',
            'data' => [
                'has_custom_plan' => true,
                'line_items' => $this->normalizeLineItems((array) ($plan->line_items ?? []), 10),
                'amount_due' => (float) $plan->amount_due,
            ],
        ]);
    }

    private function buildPaymentRows(int $schoolId, int $sessionId, int $termId, string $search, string $status): array
    {
        $query = SchoolFeePayment::query()
            ->where('school_fee_payments.school_id', $schoolId)
            ->where('school_fee_payments.academic_session_id', $sessionId)
            ->where('school_fee_payments.term_id', $termId)
            ->join('students', 'students.id', '=', 'school_fee_payments.student_id')
            ->join('users', 'users.id', '=', 'school_fee_payments.student_user_id')
            ->select([
                'school_fee_payments.id',
                'school_fee_payments.reference',
                'school_fee_payments.amount_due_snapshot',
                'school_fee_payments.amount_paid',
                'school_fee_payments.currency',
                'school_fee_payments.status',
                'school_fee_payments.paystack_status',
                'school_fee_payments.paystack_gateway_response',
                'school_fee_payments.paid_at',
                'school_fee_payments.created_at',
                'students.id as student_id',
                'students.education_level as student_level',
                'users.name as student_name',
                'users.email as student_email',
                'users.username as student_username',
            ])
            ->orderByDesc('school_fee_payments.id');

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%")
                    ->orWhere('school_fee_payments.reference', 'like', "%{$search}%");
            });
        }

        if ($status === 'successful') {
            $query->where('school_fee_payments.status', 'success');
        } elseif ($status === 'unsuccessful') {
            $query->where('school_fee_payments.status', '!=', 'success');
        }

        $rows = $query->get();
        $studentIds = $rows->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $placements = $this->resolvePlacementMap($schoolId, $sessionId, $termId, $studentIds);
        $paidTotals = SchoolFeePayment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->where('status', 'success')
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->selectRaw('student_id, SUM(amount_paid) as total_paid')
            ->pluck('total_paid', 'student_id');

        return $rows->map(function ($row) use ($placements, $paidTotals) {
            $studentId = (int) $row->student_id;
            $placement = $placements[$studentId] ?? [
                'level' => strtolower(trim((string) ($row->student_level ?? ''))),
                'class_name' => null,
                'department_name' => null,
            ];

            $status = (string) $row->status;
            $failureReason = null;
            if ($status !== 'success') {
                $failureReason = (string) ($row->paystack_gateway_response ?: $row->paystack_status ?: 'Payment unsuccessful');
            }

            $amountRemaining = max((float) $row->amount_due_snapshot - (float) ($paidTotals[$studentId] ?? 0), 0);

            return [
                'id' => (int) $row->id,
                'reference' => $row->reference,
                'amount_paid' => (float) $row->amount_paid,
                'currency' => $row->currency,
                'status' => $status,
                'paid_at' => $row->paid_at,
                'created_at' => $row->created_at,
                'failure_reason' => $failureReason,
                'level' => $placement['level'] ?: '-',
                'class_name' => $placement['class_name'] ?: '-',
                'department_name' => $placement['department_name'] ?: '-',
                'amount_outstanding' => (float) $amountRemaining,
                'student' => [
                    'id' => $studentId,
                    'name' => $row->student_name,
                    'email' => $row->student_email,
                    'username' => $row->student_username,
                ],
            ];
        })->values()->all();
    }

    private function buildOutstandingRows(int $schoolId, int $sessionId, int $termId, string $search): array
    {
        $studentsQuery = Student::query()
            ->where('students.school_id', $schoolId)
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('users.role', 'student')
            ->select([
                'students.id as student_id',
                'students.education_level as student_level',
                'users.name as student_name',
                'users.email as student_email',
                'users.username as student_username',
            ])
            ->orderBy('users.name');

        if ($search !== '') {
            $studentsQuery->where(function ($sub) use ($search) {
                $sub->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%");
            });
        }

        $students = $studentsQuery->get();
        $studentIds = $students->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (empty($studentIds)) {
            return [];
        }

        $placements = $this->resolvePlacementMap($schoolId, $sessionId, $termId, $studentIds);

        $paidTotals = SchoolFeePayment::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->where('status', 'success')
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->selectRaw('student_id, SUM(amount_paid) as total_paid')
            ->pluck('total_paid', 'student_id');

        $plans = StudentFeePlan::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->whereIn('student_id', $studentIds)
            ->get(['student_id', 'amount_due'])
            ->keyBy('student_id');

        $settings = SchoolFeeSetting::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->get();
        $settingsByLevel = $settings
            ->filter(fn ($row) => !empty($row->level))
            ->keyBy(fn ($row) => strtolower(trim((string) $row->level)));
        $legacy = $settings->first(fn ($row) => empty($row->level));

        $rows = [];
        foreach ($students as $student) {
            $studentId = (int) $student->student_id;
            $placement = $placements[$studentId] ?? [
                'level' => strtolower(trim((string) ($student->student_level ?? ''))),
                'class_name' => null,
                'department_name' => null,
            ];

            $level = $placement['level'] ?: strtolower(trim((string) ($student->student_level ?? '')));
            $customPlan = $plans->get($studentId);
            if ($customPlan) {
                $amountDue = (float) $customPlan->amount_due;
            } else {
                $amountDue = isset($settingsByLevel[$level])
                    ? (float) $settingsByLevel[$level]->amount_due
                    : (float) ($legacy?->amount_due ?? 0);
            }

            $amountPaid = (float) ($paidTotals[$studentId] ?? 0);
            $amountOutstanding = max($amountDue - $amountPaid, 0);
            if ($amountOutstanding <= 0.00001) {
                continue;
            }

            $rows[] = [
                'student' => [
                    'id' => $studentId,
                    'name' => (string) $student->student_name,
                    'email' => (string) $student->student_email,
                    'username' => (string) $student->student_username,
                ],
                'level' => $level ?: '-',
                'class_name' => $placement['class_name'] ?: '-',
                'department_name' => $placement['department_name'] ?: '-',
                'amount_paid' => $amountPaid,
                'amount_outstanding' => $amountOutstanding,
            ];
        }

        return collect($rows)
            ->sortBy(fn ($row) => strtolower((string) ($row['student']['name'] ?? '')))
            ->values()
            ->all();
    }

    private function extractFilterOptions(array $rows): array
    {
        $levels = collect($rows)->map(fn ($row) => strtolower(trim((string) ($row['level'] ?? ''))))
            ->filter(fn ($v) => $v !== '' && $v !== '-')
            ->unique()->sort()->values()->all();
        $classes = collect($rows)->map(fn ($row) => trim((string) ($row['class_name'] ?? '')))
            ->filter(fn ($v) => $v !== '' && $v !== '-')
            ->unique()->sort()->values()->all();
        $departments = collect($rows)->map(fn ($row) => trim((string) ($row['department_name'] ?? '')))
            ->filter(fn ($v) => $v !== '' && $v !== '-')
            ->unique()->sort()->values()->all();

        return [
            'levels' => $levels,
            'classes' => $classes,
            'departments' => $departments,
        ];
    }

    private function applyCommonFilters(array $rows, string $level, string $class, string $department): array
    {
        $levelNeedle = strtolower(trim($level));
        $classNeedle = strtolower(trim($class));
        $departmentNeedle = strtolower(trim($department));

        return collect($rows)->filter(function ($row) use ($levelNeedle, $classNeedle, $departmentNeedle) {
            $rowLevel = strtolower(trim((string) ($row['level'] ?? '')));
            $rowClass = strtolower(trim((string) ($row['class_name'] ?? '')));
            $rowDepartment = strtolower(trim((string) ($row['department_name'] ?? '')));

            if ($levelNeedle !== '' && $rowLevel !== $levelNeedle) {
                return false;
            }
            if ($classNeedle !== '' && $rowClass !== $classNeedle) {
                return false;
            }
            if ($departmentNeedle !== '' && $rowDepartment !== $departmentNeedle) {
                return false;
            }

            return true;
        })->values()->all();
    }

    private function resolvePlacementMap(int $schoolId, int $sessionId, int $termId, array $studentIds): array
    {
        if (empty($studentIds)) {
            return [];
        }

        $map = [];

        $enrollments = DB::table('enrollments')
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->leftJoin('class_departments', 'class_departments.id', '=', 'enrollments.department_id')
            ->whereIn('enrollments.student_id', $studentIds)
            ->where('enrollments.term_id', $termId)
            ->when(Schema::hasColumn('enrollments', 'school_id'), function ($query) use ($schoolId) {
                $query->where('enrollments.school_id', $schoolId);
            })
            ->where('classes.school_id', $schoolId)
            ->orderByDesc('enrollments.id')
            ->select([
                'enrollments.student_id',
                'classes.level as level',
                'classes.name as class_name',
                'class_departments.name as department_name',
            ])
            ->get();

        foreach ($enrollments as $row) {
            $sid = (int) $row->student_id;
            if (isset($map[$sid])) {
                continue;
            }
            $map[$sid] = [
                'level' => strtolower(trim((string) ($row->level ?? ''))),
                'class_name' => trim((string) ($row->class_name ?? '')),
                'department_name' => trim((string) ($row->department_name ?? '')),
            ];
        }

        $missingIds = array_values(array_diff($studentIds, array_keys($map)));
        if (!empty($missingIds)) {
            $classRows = DB::table('class_students')
                ->join('classes', 'classes.id', '=', 'class_students.class_id')
                ->where('class_students.school_id', $schoolId)
                ->where('class_students.academic_session_id', $sessionId)
                ->whereIn('class_students.student_id', $missingIds)
                ->orderByDesc('class_students.id')
                ->select([
                    'class_students.student_id',
                    'classes.level as level',
                    'classes.name as class_name',
                ])
                ->get();

            foreach ($classRows as $row) {
                $sid = (int) $row->student_id;
                if (isset($map[$sid])) {
                    continue;
                }
                $map[$sid] = [
                    'level' => strtolower(trim((string) ($row->level ?? ''))),
                    'class_name' => trim((string) ($row->class_name ?? '')),
                    'department_name' => '',
                ];
            }
        }

        return $map;
    }

    private function paginateRows(array $rows, int $perPage, int $page): LengthAwarePaginator
    {
        $collection = collect($rows)->values();
        $total = $collection->count();
        $items = $collection->forPage($page, $perPage)->values()->all();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function normalizeStatusFilter(string $status): string
    {
        $needle = strtolower(trim($status));
        return match ($needle) {
            'successful', 'success' => 'successful',
            'unsuccessful', 'failed', 'failure' => 'unsuccessful',
            default => 'all',
        };
    }

    private function resolveCurrentSessionAndTerm(int $schoolId): array
    {
        $session = AcademicSession::where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();
        if (!$session) {
            return [null, null];
        }

        $term = Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('is_current', true)
            ->first();
        if (!$term) {
            $term = Term::where('school_id', $schoolId)
                ->where('academic_session_id', $session->id)
                ->orderBy('id')
                ->first();
        }

        return [$session, $term];
    }

    private function extractSessionLevels(int $schoolId, int $sessionId, mixed $rawLevels): array
    {
        $levels = collect(is_array($rawLevels) ? $rawLevels : [])
            ->map(function ($item) {
                $name = is_array($item) ? ($item['level'] ?? null) : $item;
                return strtolower(trim((string) $name));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($levels)) {
            return $levels;
        }

        $classLevels = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->pluck('level')
            ->map(fn ($level) => strtolower(trim((string) $level)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $classLevels;
    }

    private function normalizeLineItems(array $items, int $maxItems = 10): array
    {
        $rows = [];
        foreach (array_slice($items, 0, $maxItems) as $index => $item) {
            $enabledRaw = $item['enabled'] ?? true;
            $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabled === null) {
                $enabled = true;
            }
            $description = trim((string) ($item['description'] ?? ''));
            $rawAmount = $item['amount'] ?? null;
            $amount = is_numeric($rawAmount) ? round((float) $rawAmount, 2) : null;

            if ($description === '' && ($amount === null || $amount <= 0)) {
                continue;
            }

            if ($description === '') {
                $description = 'Fee Item ' . ($index + 1);
            }

            $rows[] = [
                'enabled' => (bool) $enabled,
                'description' => $description,
                'amount' => max((float) ($amount ?? 0), 0),
            ];
        }

        return $rows;
    }

    private function resolveFeeSetting(int $schoolId, int $sessionId, int $termId, ?string $studentLevel): ?SchoolFeeSetting
    {
        $base = SchoolFeeSetting::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId);

        if ($studentLevel) {
            $match = (clone $base)
                ->where('level', $studentLevel)
                ->first();
            if ($match) {
                return $match;
            }
        }

        $legacy = (clone $base)->whereNull('level')->first();
        if ($legacy) {
            return $legacy;
        }

        if (!$studentLevel) {
            return (clone $base)->orderBy('id')->first();
        }

        return null;
    }

    private function resolveStudentLevel(int $schoolId, int $studentId, int $sessionId, int $termId): ?string
    {
        $level = DB::table('enrollments')
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->where('classes.school_id', $schoolId)
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.term_id', $termId)
            ->when(Schema::hasColumn('enrollments', 'school_id'), function ($q) use ($schoolId) {
                $q->where('enrollments.school_id', $schoolId);
            })
            ->orderByDesc('enrollments.id')
            ->value('classes.level');

        if (!$level) {
            $level = DB::table('class_students')
                ->join('classes', 'classes.id', '=', 'class_students.class_id')
                ->where('class_students.school_id', $schoolId)
                ->where('class_students.student_id', $studentId)
                ->where('class_students.academic_session_id', $sessionId)
                ->orderByDesc('class_students.id')
                ->value('classes.level');
        }

        $normalized = strtolower(trim((string) $level));
        return $normalized !== '' ? $normalized : null;
    }

    private function resolveStudentPlacement(int $studentId, int $termId): array
    {
        $placement = [
            'class_name' => null,
            'department_name' => null,
        ];

        $row = DB::table('enrollments')
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->leftJoin('class_departments', 'class_departments.id', '=', 'enrollments.department_id')
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.term_id', $termId)
            ->orderByDesc('enrollments.id')
            ->select([
                'classes.name as class_name',
                'class_departments.name as department_name',
            ])
            ->first();

        if ($row) {
            $placement['class_name'] = $row->class_name ? (string) $row->class_name : null;
            $placement['department_name'] = $row->department_name ? (string) $row->department_name : null;
        }

        return $placement;
    }
}
