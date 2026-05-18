<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Jobs\CompressTeachingMaterialJob;
use App\Models\AcademicSession;
use App\Models\TeachingMaterial;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TeachingController extends Controller
{
    private const MAX_FILES_PER_CATEGORY = 8;

    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;
        [$session, $term] = $this->resolveCurrentSessionAndTerm($schoolId);

        if (!$session || !$term) {
            return response()->json(['message' => 'No current academic session/term configured.'], 422);
        }

        $materials = TeachingMaterial::query()
            ->where('school_id', $schoolId)
            ->where('staff_user_id', (int) $user->id)
            ->where('academic_session_id', (int) $session->id)
            ->where('term_id', (int) $term->id)
            ->orderBy('category')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TeachingMaterial $material) => $this->materialPayload($material, true))
            ->values();

        return response()->json([
            'data' => [
                'current_session' => $this->sessionPayload($session),
                'current_term' => $this->termPayload($term),
                'categories' => $this->categories(),
                'materials' => $materials,
                'limits' => [
                    'max_files_per_category' => self::MAX_FILES_PER_CATEGORY,
                    'exam_question_files' => 1,
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
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'title' => ['nullable', 'string', 'max:180'],
            'files' => ['required', 'array', 'min:1', 'max:' . self::MAX_FILES_PER_CATEGORY],
            'files.*' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $category = (string) $payload['category'];
        $files = $request->file('files', []);
        if ($category === TeachingMaterial::CATEGORY_EXAM_QUESTION && count($files) > 1) {
            return response()->json(['message' => 'Exam question accepts only one file. New upload replaces the previous one.'], 422);
        }

        if ($category !== TeachingMaterial::CATEGORY_EXAM_QUESTION) {
            $existingCount = TeachingMaterial::query()
                ->where('school_id', $schoolId)
                ->where('staff_user_id', (int) $user->id)
                ->where('academic_session_id', (int) $session->id)
                ->where('term_id', (int) $term->id)
                ->where('category', $category)
                ->count();

            if ($existingCount + count($files) > self::MAX_FILES_PER_CATEGORY) {
                return response()->json([
                    'message' => 'You can upload a maximum of ' . self::MAX_FILES_PER_CATEGORY . ' files for this section in the current term.',
                ], 422);
            }
        }

        if ($category === TeachingMaterial::CATEGORY_EXAM_QUESTION) {
            $oldMaterials = TeachingMaterial::query()
                ->where('school_id', $schoolId)
                ->where('staff_user_id', (int) $user->id)
                ->where('academic_session_id', (int) $session->id)
                ->where('term_id', (int) $term->id)
                ->where('category', TeachingMaterial::CATEGORY_EXAM_QUESTION)
                ->get();

            foreach ($oldMaterials as $oldMaterial) {
                $this->deleteStoredFile($oldMaterial);
                $oldMaterial->delete();
            }
        }

        $created = [];
        foreach ($files as $file) {
            $dir = "schools/{$schoolId}/teaching/{$session->id}/{$term->id}/{$user->id}";
            $path = $file->store($dir, 'public');
            $material = TeachingMaterial::query()->create([
                'school_id' => $schoolId,
                'staff_user_id' => (int) $user->id,
                'academic_session_id' => (int) $session->id,
                'term_id' => (int) $term->id,
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
        ];
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
