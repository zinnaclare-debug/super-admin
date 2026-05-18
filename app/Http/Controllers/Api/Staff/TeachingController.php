<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Jobs\CompressTeachingMaterialJob;
use App\Models\AcademicSession;
use App\Models\TeachingMaterial;
use App\Models\Term;
use App\Models\TermSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TeachingController extends Controller
{
    private const MAX_TOPIC_FILES_PER_SUBJECT = 8;

    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);

        if (!$session || !$term) {
            return response()->json(['message' => 'No current academic session/term configured.'], 422);
        }

        $materials = TeachingMaterial::query()
            ->select('teaching_materials.*', 'subjects.name as subject_name', 'classes.name as class_name', 'classes.level as class_level')
            ->leftJoin('term_subjects', 'term_subjects.id', '=', 'teaching_materials.term_subject_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->where('teaching_materials.school_id', $schoolId)
            ->where('teaching_materials.staff_user_id', (int) $user->id)
            ->where('teaching_materials.academic_session_id', (int) $session->id)
            ->where('teaching_materials.term_id', (int) $term->id)
            ->orderBy('subjects.name')
            ->orderBy('classes.name')
            ->orderBy('teaching_materials.category')
            ->orderByDesc('teaching_materials.id')
            ->get()
            ->map(fn (TeachingMaterial $material) => $this->materialPayload($material, true))
            ->values();

        $subjects = $this->assignedSubjects($schoolId, (int) $user->id, (int) $session->id, (int) $term->id);

        return response()->json([
            'data' => [
                'current_session' => $this->sessionPayload($session),
                'current_term' => $this->termPayload($term),
                'categories' => $this->categories(),
                'subjects' => $subjects,
                'materials' => $materials,
                'limits' => [
                    'max_topic_files_per_subject' => self::MAX_TOPIC_FILES_PER_SUBJECT,
                    'exam_question_files_per_subject' => 1,
                    'target_compression_kb' => 150,
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);

        if (!$session || !$term) {
            return response()->json(['message' => 'No current academic session/term configured.'], 422);
        }

        $payload = $request->validate([
            'term_subject_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'title' => ['nullable', 'string', 'max:180'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $category = (string) $payload['category'];
        $termSubject = $this->resolveAssignedTermSubject(
            $schoolId,
            (int) $user->id,
            (int) $session->id,
            (int) $term->id,
            (int) $payload['term_subject_id']
        );
        if (!$termSubject) {
            return response()->json(['message' => 'Select a subject assigned to you for the current term.'], 422);
        }

        $files = $request->file('files', []);
        if ($category === TeachingMaterial::CATEGORY_EXAM_QUESTION && count($files) > 1) {
            return response()->json(['message' => 'Exam question accepts only one file. New upload replaces the previous one.'], 422);
        }

        if ($category === TeachingMaterial::CATEGORY_TOPIC) {
            $existingCount = TeachingMaterial::query()
                ->where('school_id', $schoolId)
                ->where('staff_user_id', (int) $user->id)
                ->where('academic_session_id', (int) $session->id)
                ->where('term_id', (int) $term->id)
                ->where('term_subject_id', (int) $termSubject->id)
                ->where('category', $category)
                ->count();

            if ($existingCount + count($files) > self::MAX_TOPIC_FILES_PER_SUBJECT) {
                return response()->json([
                    'message' => 'You can upload a maximum of ' . self::MAX_TOPIC_FILES_PER_SUBJECT . ' topic files for this subject in the current term.',
                ], 422);
            }
        }

        if ($category === TeachingMaterial::CATEGORY_EXAM_QUESTION) {
            $oldMaterials = TeachingMaterial::query()
                ->where('school_id', $schoolId)
                ->where('staff_user_id', (int) $user->id)
                ->where('academic_session_id', (int) $session->id)
                ->where('term_id', (int) $term->id)
                ->where('term_subject_id', (int) $termSubject->id)
                ->where('category', TeachingMaterial::CATEGORY_EXAM_QUESTION)
                ->get();

            foreach ($oldMaterials as $oldMaterial) {
                $this->deleteStoredFile($oldMaterial);
                $oldMaterial->delete();
            }
        }

        $created = [];
        foreach ($files as $file) {
            $dir = "schools/{$schoolId}/teaching/{$session->id}/{$term->id}/{$user->id}/{$termSubject->id}";
            $path = $file->store($dir, 'public');
            $material = TeachingMaterial::query()->create([
                'school_id' => $schoolId,
                'staff_user_id' => (int) $user->id,
                'academic_session_id' => (int) $session->id,
                'term_id' => (int) $term->id,
                'term_subject_id' => (int) $termSubject->id,
                'subject_id' => (int) $termSubject->subject_id,
                'category' => $category,
                'title' => trim((string) ($payload['title'] ?? '')) ?: null,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'compressed_size' => null,
                'status' => TeachingMaterial::STATUS_PROCESSING,
                'processing_note' => 'Queued for compression.',
            ]);

            CompressTeachingMaterialJob::dispatch((int) $material->id);
            $material->setAttribute('subject_name', $termSubject->subject_name ?? null);
            $material->setAttribute('class_name', $termSubject->class_name ?? null);
            $material->setAttribute('class_level', $termSubject->class_level ?? null);
            $created[] = $this->materialPayload($material, true);
        }

        return response()->json([
            'message' => 'Teaching material uploaded and queued for compression.',
            'data' => $created,
        ], 201);
    }

    public function destroy(Request $request, TeachingMaterial $material)
    {
        $user = $request->user();
        abort_unless((int) $material->school_id === (int) $user->school_id, 404);
        abort_unless((int) $material->staff_user_id === (int) $user->id, 403);

        $this->deleteStoredFile($material);
        $material->delete();

        return response()->json(['message' => 'Teaching material deleted.']);
    }

    public function download(Request $request, TeachingMaterial $material)
    {
        $user = $request->user();
        abort_unless((int) $material->school_id === (int) $user->school_id, 404);
        abort_unless((int) $material->staff_user_id === (int) $user->id, 403);

        return $this->downloadMaterial($material);
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

    private function materialPayload(TeachingMaterial $material, bool $includeDownloadUrl = false): array
    {
        $payload = [
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
            'created_at' => optional($material->created_at)?->toDateTimeString(),
            'updated_at' => optional($material->updated_at)?->toDateTimeString(),
        ];

        if ($includeDownloadUrl) {
            $payload['download_url'] = '/api/staff/teaching/materials/' . $material->id . '/download';
        }

        return $payload;
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

    private function resolveAssignedTermSubject(
        int $schoolId,
        int $staffUserId,
        int $sessionId,
        int $termId,
        int $termSubjectId
    ): ?TermSubject {
        return TermSubject::query()
            ->where('term_subjects.id', $termSubjectId)
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.teacher_user_id', $staffUserId)
            ->where('term_subjects.term_id', $termId)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
            ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->where('terms.academic_session_id', $sessionId)
            ->where('classes.academic_session_id', $sessionId)
            ->first([
                'term_subjects.*',
                'subjects.name as subject_name',
                'classes.name as class_name',
                'classes.level as class_level',
            ]);
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

    private function deleteStoredFile(TeachingMaterial $material): void
    {
        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            Storage::disk('public')->delete($material->file_path);
        }
    }

    private function downloadMaterial(TeachingMaterial $material)
    {
        if ($material->status !== TeachingMaterial::STATUS_READY) {
            return response()->json(['message' => 'File is still processing. Please try again shortly.'], 409);
        }
        if (!$material->file_path || !Storage::disk('public')->exists($material->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::disk('public')->download($material->file_path, $material->original_name);
    }
}
