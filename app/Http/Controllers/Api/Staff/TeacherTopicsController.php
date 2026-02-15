<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\TermSubject;
use App\Models\TopicMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeacherTopicsController extends Controller
{
    private function currentTerm(Request $request): ?Term
    {
        $schoolId = $request->user()->school_id;
        $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
        if (!$session) return null;

        return Term::where('school_id', $schoolId)
            ->where('academic_session_id', $session->id)
            ->where('is_current', true)
            ->first();
    }

    // GET /api/staff/topics/subjects
    public function myAssignedSubjects(Request $request)
    {
        $user = $request->user();
        $schoolId = $user->school_id;
        abort_unless($user->role === 'staff', 403);

        $currentTerm = $this->currentTerm($request);
        if (!$currentTerm) return response()->json(['data' => []]);

        $rows = TermSubject::query()
            ->where('term_subjects.school_id', $schoolId)
            ->where('term_subjects.teacher_user_id', $user->id)
            ->where('term_subjects.term_id', $currentTerm->id)
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
            ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
            ->orderBy('classes.level')
            ->orderBy('classes.name')
            ->orderBy('subjects.name')
            ->get([
                'term_subjects.id as term_subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
                'classes.id as class_id',
                'classes.name as class_name',
                'classes.level as class_level',
                'terms.id as term_id',
                'terms.name as term_name',
            ]);

        return response()->json(['data' => $rows]);
    }

    // GET /api/staff/topics/subjects/{termSubject}/materials
    public function materials(Request $request, TermSubject $termSubject)
    {
        $user = $request->user();
        $schoolId = $user->school_id;
        abort_unless($user->role === 'staff', 403);

        abort_unless((int) $termSubject->school_id === (int) $schoolId, 403);
        abort_unless((int) $termSubject->teacher_user_id === (int) $user->id, 403);

        $currentTerm = $this->currentTerm($request);
        abort_unless($currentTerm && (int) $termSubject->term_id === (int) $currentTerm->id, 403);

        $items = TopicMaterial::query()
            ->where('school_id', $schoolId)
            ->where('teacher_user_id', $user->id)
            ->where('term_subject_id', $termSubject->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($m) {
                $m->file_url = Storage::disk('public')->url($m->file_path);
                return $m;
            });

        return response()->json(['data' => $items]);
    }

    // POST /api/staff/topics/subjects/{termSubject}/materials
    public function upload(Request $request, TermSubject $termSubject)
    {
        $user = $request->user();
        $schoolId = $user->school_id;
        abort_unless($user->role === 'staff', 403);

        abort_unless((int) $termSubject->school_id === (int) $schoolId, 403);
        abort_unless((int) $termSubject->teacher_user_id === (int) $user->id, 403);

        $currentTerm = $this->currentTerm($request);
        abort_unless($currentTerm && (int) $termSubject->term_id === (int) $currentTerm->id, 403);

        $data = $request->validate([
            'title' => 'nullable|string|max:150',
            'file'  => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $file = $request->file('file');
        $dir = "schools/{$schoolId}/topics/teacher-{$user->id}/term-subject-{$termSubject->id}";
        $path = $file->store($dir, 'public');
        $originalName = $file->getClientOriginalName();
        $resolvedTitle = isset($data['title']) && trim((string)$data['title']) !== ''
            ? trim((string)$data['title'])
            : pathinfo($originalName, PATHINFO_FILENAME);

        $row = TopicMaterial::create([
            'school_id' => $schoolId,
            'teacher_user_id' => $user->id,
            'term_subject_id' => $termSubject->id,
            'title' => $resolvedTitle,
            'file_path' => $path,
            'original_name' => $originalName,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => 'Uploaded',
            'data' => [
                'id' => $row->id,
                'title' => $row->title,
                'original_name' => $row->original_name,
                'file_url' => Storage::disk('public')->url($row->file_path),
            ]
        ], 201);
    }

    // DELETE /api/staff/topics/subjects/{termSubject}/materials/{material}
    public function destroy(Request $request, TermSubject $termSubject, TopicMaterial $material)
    {
        $user = $request->user();
        $schoolId = $user->school_id;
        abort_unless($user->role === 'staff', 403);

        abort_unless((int) $termSubject->school_id === (int) $schoolId, 403);
        abort_unless((int) $termSubject->teacher_user_id === (int) $user->id, 403);

        $currentTerm = $this->currentTerm($request);
        abort_unless($currentTerm && (int) $termSubject->term_id === (int) $currentTerm->id, 403);

        abort_unless((int) $material->term_subject_id === (int) $termSubject->id, 404);
        abort_unless((int) $material->teacher_user_id === (int) $user->id, 403);
        abort_unless((int) $material->school_id === (int) $schoolId, 403);

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }
}
