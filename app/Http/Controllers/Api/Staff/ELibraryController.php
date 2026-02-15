<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ELibraryBook;
use App\Models\Term;
use App\Models\TermSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ELibraryController extends Controller
{
  // Backward-compatible alias for older route bindings.
  public function index(Request $request)
  {
    return $this->myUploads($request);
  }

  // GET /api/staff/e-library/assigned-subjects
  // Only subjects this teacher is assigned to (current session)
  public function assignedSubjects(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    $session = AcademicSession::where('school_id', $schoolId)
      ->where('status', 'current')
      ->first();

    if (!$session) return response()->json(['data' => []]);

    $currentTerm = Term::where('school_id', $schoolId)
      ->where('academic_session_id', $session->id)
      ->where('is_current', true)
      ->first();

    if (!$currentTerm) return response()->json(['data' => []]);

    $rows = TermSubject::query()
      ->where('term_subjects.school_id', $schoolId)
      ->where('term_subjects.teacher_user_id', $user->id)
      ->where('term_subjects.term_id', $currentTerm->id)
      ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->join('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->where('terms.academic_session_id', $session->id)
      ->where('classes.academic_session_id', $session->id)
      ->orderBy('classes.level')
      ->orderBy('classes.name')
      ->orderBy('terms.id')
      ->orderBy('subjects.name')
      ->get([
        'term_subjects.id as term_subject_id',
        'subjects.id as subject_id',
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

  // GET /api/staff/e-library  (teacher sees ONLY own uploads)
  public function myUploads(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    $items = ELibraryBook::query()
      ->leftJoin('term_subjects', 'term_subjects.id', '=', 'e_library_books.term_subject_id')
      ->leftJoin('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
      ->leftJoin('classes', 'classes.id', '=', 'term_subjects.class_id')
      ->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
      ->where('e_library_books.school_id', $schoolId)
      ->where('e_library_books.uploaded_by_user_id', $user->id)
      ->where(function ($q) use ($schoolId) {
        $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
        if (!$session) {
          $q->whereRaw('1 = 0');
          return;
        }
        $currentTerm = Term::where('school_id', $schoolId)
          ->where('academic_session_id', $session->id)
          ->where('is_current', true)
          ->first();
        if (!$currentTerm) {
          $q->whereRaw('1 = 0');
          return;
        }
        $q->where('term_subjects.term_id', $currentTerm->id);
      })
      ->orderByDesc('e_library_books.id')
      ->get([
        'e_library_books.*',
        'subjects.name as subject_name',
        'classes.name as class_name',
        'classes.level as class_level',
        'terms.name as term_name',
      ])
      ->map(function ($b) {
        $b->file_url = Storage::disk('public')->url($b->file_path);
        return $b;
      });

    return response()->json(['data' => $items]);
  }

  // POST /api/staff/e-library
  // Teacher can upload ONLY to a term_subject assigned to them
  public function upload(Request $request)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    $data = $request->validate([
      'term_subject_id' => 'required|integer|exists:term_subjects,id',
      'title' => 'required|string|max:150',
      'author' => 'nullable|string|max:150',
      'description' => 'nullable|string|max:500',
      'file' => 'required|file|mimes:pdf|max:20480',
    ]);

    // ✅ enforce: term_subject belongs to same school AND assigned to this teacher
    $ts = TermSubject::where('id', $data['term_subject_id'])
      ->where('school_id', $schoolId)
      ->where('teacher_user_id', $user->id)
      ->first();

    if (!$ts) {
      return response()->json(['message' => 'You are not assigned to this subject'], 403);
    }

    $session = AcademicSession::where('school_id', $schoolId)->where('status', 'current')->first();
    $currentTerm = $session
      ? Term::where('school_id', $schoolId)->where('academic_session_id', $session->id)->where('is_current', true)->first()
      : null;

    if (!$session || !$currentTerm || (int)$ts->term_id !== (int)$currentTerm->id) {
      return response()->json(['message' => 'Upload allowed only for current session and current term'], 403);
    }


    $file = $request->file('file');
    $dir = "schools/{$schoolId}/e-library";
    $path = $file->store($dir, 'public');

    $book = ELibraryBook::create([
      'school_id' => $schoolId,
      'uploaded_by_user_id' => $user->id,
      'term_subject_id' => $ts->id,
      'subject_id' => $ts->subject_id, // keep if your table has it; if not, remove this line
      'title' => $data['title'],
      'author' => $data['author'] ?? null,
      'description' => $data['description'] ?? null,
      'file_path' => $path,
      'original_name' => $file->getClientOriginalName(),
      'mime_type' => $file->getClientMimeType(),
      'size' => $file->getSize(),
    ]);

    return response()->json([
      'message' => 'Uploaded',
      'data' => [
        'id' => $book->id,
        'file_url' => Storage::disk('public')->url($book->file_path),
      ]
    ], 201);
  }

  // DELETE /api/staff/e-library/{book}
  // Teacher can delete ONLY their own uploads
  public function destroy(Request $request, ELibraryBook $book)
  {
    $user = $request->user();
    abort_unless($user->role === 'staff', 403);

    $schoolId = $user->school_id;

    abort_unless((int)$book->school_id === (int)$schoolId, 403);
    abort_unless((int)$book->uploaded_by_user_id === (int)$user->id, 403);

    if ($book->file_path && Storage::disk('public')->exists($book->file_path)) {
      Storage::disk('public')->delete($book->file_path);
    }

    $book->delete();

    return response()->json(['message' => 'Deleted']);
  }
}
