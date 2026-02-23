<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $student = Student::query()
            ->where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        $studentLevel = $student ? $this->resolveStudentLevel($student->id, $schoolId) : '';

        $query = Announcement::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(function ($q) use ($studentLevel) {
                $q->whereNull('level');
                if ($studentLevel !== '') {
                    $q->orWhere('level', $studentLevel);
                }
            })
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('author:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $data = $query->get()->map(fn (Announcement $item) => [
            'id' => $item->id,
            'title' => $item->title,
            'message' => $item->message,
            'level' => $item->level,
            'audience' => $item->level ? ucfirst($item->level) . ' only' : 'School-wide',
            'published_at' => optional($item->published_at)->toIso8601String(),
            'author' => $item->author ? [
                'id' => $item->author->id,
                'name' => $item->author->name,
            ] : null,
        ]);

        return response()->json(['data' => $data]);
    }

    private function resolveStudentLevel(int $studentId, int $schoolId): string
    {
        $current = DB::table('class_students')
            ->join('classes', 'classes.id', '=', 'class_students.class_id')
            ->join('academic_sessions', 'academic_sessions.id', '=', 'class_students.academic_session_id')
            ->where('class_students.school_id', $schoolId)
            ->where('class_students.student_id', $studentId)
            ->where('academic_sessions.status', 'current')
            ->orderByDesc('class_students.id')
            ->value('classes.level');

        if (!empty($current)) {
            return strtolower((string) $current);
        }

        $latest = DB::table('class_students')
            ->join('classes', 'classes.id', '=', 'class_students.class_id')
            ->where('class_students.school_id', $schoolId)
            ->where('class_students.student_id', $studentId)
            ->orderByDesc('class_students.id')
            ->value('classes.level');

        return strtolower((string) $latest);
    }
}

