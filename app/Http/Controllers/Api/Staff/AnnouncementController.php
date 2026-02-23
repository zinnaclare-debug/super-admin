<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;
        $staffLevel = strtolower((string) optional($user->staffProfile)->education_level);

        $query = Announcement::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(function ($q) use ($staffLevel) {
                $q->whereNull('level');
                if ($staffLevel !== '') {
                    $q->orWhere('level', $staffLevel);
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
}

