<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\TeachingMaterial;
use App\Models\Term;
use App\Models\TermSubject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class TeachingController extends Controller
{
    public function context(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);

        return response()->json([
            'data' => [
                'current_session' => $session ? $this->sessionPayload($session) : null,
                'current_term' => $term ? $this->termPayload($term) : null,
                'periods' => $this->periods($schoolId),
                'categories' => $this->categories(),
            ],
        ]);
    }

    public function staff(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveRequestedSessionAndTerm($schoolId, $request);
        if (!$session || !$term) {
            return response()->json(['message' => 'No academic session/term configured.'], 422);
        }

        $query = User::query()
            ->where('school_id', $schoolId)
            ->where('role', User::ROLE_STAFF)
            ->orderBy('name');
        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        $staff = $query->get(['id', 'name', 'email', 'username'])
            ->map(function (User $staffUser) use ($schoolId, $session, $term) {
                $count = TeachingMaterial::query()
                    ->where('school_id', $schoolId)
                    ->where('staff_user_id', (int) $staffUser->id)
                    ->where('academic_session_id', (int) $session->id)
                    ->where('term_id', (int) $term->id)
                    ->count();
                $subjectCount = TermSubject::query()
                    ->where('term_subjects.school_id', $schoolId)
                    ->where('term_subjects.teacher_user_id', (int) $staffUser->id)
                    ->where('term_subjects.term_id', (int) $term->id)
                    ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
                    ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
                    ->where('terms.academic_session_id', (int) $session->id)
                    ->where('classes.academic_session_id', (int) $session->id)
                    ->count();

                return [
                    'id' => (int) $staffUser->id,
                    'name' => $staffUser->name,
                    'email' => $staffUser->email,
                    'username' => $staffUser->username,
                    'materials_count' => $count,
                    'subjects_count' => $subjectCount,
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'staff' => $staff,
                'selected_session' => $this->sessionPayload($session),
                'selected_term' => $this->termPayload($term),
            ],
        ]);
    }

    public function materials(Request $request)
    {
        $schoolId = (int) $request->user()->school_id;
        [$session, $term] = $this->resolveRequestedSessionAndTerm($schoolId, $request);
        if (!$session || !$term) {
            return response()->json(['message' => 'No academic session/term configured.'], 422);
        }

        $payload = $request->validate([
            'staff_user_id' => ['required', 'integer'],
        ]);

        $staff = User::query()
            ->where('school_id', $schoolId)
            ->where('role', User::ROLE_STAFF)
            ->where('id', (int) $payload['staff_user_id'])
            ->first();
        if (!$staff) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        $materials = TeachingMaterial::query()
            ->select('teaching_materials.*', 'subjects.name as subject_name', 'classes.name as class_name', 'classes.level as class_level')
            ->leftJoin('term_subjects', 'term_subjects.id', '=', 'teaching_materials.term_subject_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->where('teaching_materials.school_id', $schoolId)
            ->where('teaching_materials.staff_user_id', (int) $staff->id)
            ->where('teaching_materials.academic_session_id', (int) $session->id)
            ->where('teaching_materials.term_id', (int) $term->id)
            ->orderBy('subjects.name')
            ->orderBy('classes.name')
            ->orderBy('teaching_materials.category')
            ->orderByDesc('teaching_materials.id')
            ->get()
            ->map(fn (TeachingMaterial $material) => $this->materialPayload($material))
            ->values();
        $subjects = $this->assignedSubjects($schoolId, (int) $staff->id, (int) $session->id, (int) $term->id);

        return response()->json([
            'data' => [
                'staff' => [
                    'id' => (int) $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'username' => $staff->username,
                ],
                'selected_session' => $this->sessionPayload($session),
                'selected_term' => $this->termPayload($term),
                'subjects' => $subjects,
                'materials' => $materials,
                'categories' => $this->categories(),
            ],
        ]);
    }

    public function download(Request $request, TeachingMaterial $material)
    {
        $schoolId = (int) $request->user()->school_id;
        abort_unless((int) $material->school_id === $schoolId, 404);

        if ($material->status !== TeachingMaterial::STATUS_READY) {
            return response()->json(['message' => 'File is still processing. Please try again shortly.'], 409);
        }
        if (!$material->file_path || !Storage::disk('public')->exists($material->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::disk('public')->download($material->file_path, $material->original_name);
    }

    private function resolveRequestedSessionAndTerm(int $schoolId, Request $request): array
    {
        [$currentSession, $currentTerm] = $this->resolveCurrentSessionAndTerm($schoolId);
        if (!$currentSession || !$currentTerm) {
            return [null, null];
        }

        $sessionId = (int) $request->query('academic_session_id', $currentSession->id);
        $termId = (int) $request->query('term_id', $currentTerm->id);

        $session = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('id', $sessionId)
            ->first();
        if (!$session) {
            return [$currentSession, $currentTerm];
        }

        $term = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->where('id', $termId)
            ->first();
        if (!$term) {
            $term = Term::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $session->id)
                ->orderBy('id')
                ->first();
        }

        return [$session, $term];
    }

    private function resolveCurrentSessionAndTerm(int $schoolId): array
    {
        $session = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->first();
        if (!$session) {
            return [null, null];
        }

        $term = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id)
            ->where('is_current', true)
            ->first();
        if (!$term) {
            $term = Term::query()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', (int) $session->id)
                ->orderBy('id')
                ->first();
        }

        return [$session, $term];
    }

    private function periods(int $schoolId): array
    {
        return AcademicSession::query()
            ->where('school_id', $schoolId)
            ->with(['terms' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->map(function (AcademicSession $session) {
                return [
                    'id' => (int) $session->id,
                    'label' => (string) ($session->session_name ?: $session->academic_year ?: 'Session ' . $session->id),
                    'session_name' => $session->session_name,
                    'academic_year' => $session->academic_year,
                    'is_current' => (string) $session->status === 'current',
                    'terms' => $session->terms->map(fn (Term $term) => [
                        'id' => (int) $term->id,
                        'name' => $term->name,
                        'is_current' => (bool) $term->is_current,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function materialPayload(TeachingMaterial $material): array
    {
        return [
            'id' => (int) $material->id,
            'term_subject_id' => $material->term_subject_id ? (int) $material->term_subject_id : null,
            'subject_id' => $material->subject_id ? (int) $material->subject_id : null,
            'subject_name' => $material->getAttribute('subject_name') ?: null,
            'class_name' => $material->getAttribute('class_name') ?: null,
            'class_level' => $material->getAttribute('class_level') ?: null,
            'subject_label' => $this->subjectLabel($material),
            'category' => (string) $material->category,
            'category_label' => $this->categories()[$material->category] ?? $material->category,
            'title' => $material->title,
            'original_name' => $material->original_name,
            'mime_type' => $material->mime_type,
            'file_size' => (int) ($material->file_size ?? 0),
            'compressed_size' => (int) ($material->compressed_size ?? 0),
            'status' => $material->status,
            'processing_note' => $material->processing_note,
            'download_url' => '/api/school-admin/teaching/materials/' . $material->id . '/download',
            'created_at' => optional($material->created_at)?->toDateTimeString(),
            'updated_at' => optional($material->updated_at)?->toDateTimeString(),
        ];
    }

    private function categories(): array
    {
        return [
            TeachingMaterial::CATEGORY_TOPIC => 'Topics for the Term',
            TeachingMaterial::CATEGORY_EXAM_QUESTION => 'Exam Questions',
            TeachingMaterial::CATEGORY_LESSON_NOTE => 'Lesson Notes',
            TeachingMaterial::CATEGORY_LESSON_PLAN => 'Lesson Plans',
        ];
    }

    private function assignedSubjects(int $schoolId, int $staffUserId, int $sessionId, int $termId): array
    {
        return TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.teacher_user_id', $staffUserId)
            ->where('term_subjects.term_id', $termId)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
            ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->where('terms.academic_session_id', $sessionId)
            ->where('classes.academic_session_id', $sessionId)
            ->orderBy('subjects.name')
            ->orderBy('classes.name')
            ->get([
                'term_subjects.id as term_subject_id',
                'term_subjects.subject_id as subject_id',
                'subjects.name as subject_name',
                'classes.name as class_name',
                'classes.level as class_level',
            ])
            ->map(fn ($row) => [
                'term_subject_id' => (int) $row->term_subject_id,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => (string) $row->subject_name,
                'class_name' => (string) $row->class_name,
                'class_level' => (string) $row->class_level,
                'label' => trim((string) $row->subject_name . ' - ' . (string) $row->class_name),
            ])
            ->values()
            ->all();
    }

    private function subjectLabel(TeachingMaterial $material): string
    {
        $subject = trim((string) ($material->getAttribute('subject_name') ?: ''));
        $class = trim((string) ($material->getAttribute('class_name') ?: ''));
        if ($subject !== '' && $class !== '') {
            return "{$subject} - {$class}";
        }
        if ($subject !== '') {
            return $subject;
        }

        return 'General / Unassigned';
    }

    private function sessionPayload(AcademicSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'session_name' => $session->session_name,
            'academic_year' => $session->academic_year,
        ];
    }

    private function termPayload(Term $term): array
    {
        return [
            'id' => (int) $term->id,
            'name' => $term->name,
        ];
    }
}
