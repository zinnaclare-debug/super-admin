<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $request->validate([
            'level' => ['nullable', Rule::in(['nursery', 'primary', 'secondary'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);

        $query = Announcement::query()
            ->where('school_id', $schoolId)
            ->when($request->filled('level'), function ($q) use ($request) {
                $q->where('level', $request->string('level')->toString());
            })
            ->when($request->string('status')->toString() === 'active', function ($q) {
                $q->where('is_active', true);
            })
            ->when($request->string('status')->toString() === 'inactive', function ($q) {
                $q->where('is_active', false);
            })
            ->with('author:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        $data = $query->get()->map(fn (Announcement $item) => $this->toPayload($item));

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
            'level' => ['nullable', Rule::in(['nursery', 'primary', 'secondary'])],
            'expires_at' => ['nullable', 'date'],
        ]);

        $announcement = Announcement::create([
            'school_id' => $schoolId,
            'created_by_user_id' => $user->id,
            'title' => trim($payload['title']),
            'message' => trim($payload['message']),
            'level' => $payload['level'] ?? null,
            'is_active' => true,
            'published_at' => now(),
            'expires_at' => $payload['expires_at'] ?? null,
        ]);

        $announcement->load('author:id,name');

        return response()->json([
            'message' => 'Announcement created.',
            'data' => $this->toPayload($announcement),
        ], 201);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        if ((int) $announcement->school_id !== $schoolId) {
            abort(404);
        }

        $payload = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'message' => ['sometimes', 'required', 'string', 'max:5000'],
            'level' => ['sometimes', 'nullable', Rule::in(['nursery', 'primary', 'secondary'])],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if (array_key_exists('title', $payload)) {
            $announcement->title = trim((string) $payload['title']);
        }

        if (array_key_exists('message', $payload)) {
            $announcement->message = trim((string) $payload['message']);
        }

        if (array_key_exists('level', $payload)) {
            $announcement->level = $payload['level'];
        }

        if (array_key_exists('is_active', $payload)) {
            $announcement->is_active = (bool) $payload['is_active'];
        }

        if (array_key_exists('expires_at', $payload)) {
            $announcement->expires_at = $payload['expires_at'];
        }

        $announcement->save();
        $announcement->load('author:id,name');

        return response()->json([
            'message' => 'Announcement updated.',
            'data' => $this->toPayload($announcement),
        ]);
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        if ((int) $announcement->school_id !== $schoolId) {
            abort(404);
        }

        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    private function toPayload(Announcement $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'message' => $item->message,
            'level' => $item->level,
            'audience' => $item->level ? ucfirst($item->level) . ' only' : 'School-wide',
            'is_active' => (bool) $item->is_active,
            'published_at' => optional($item->published_at)->toIso8601String(),
            'expires_at' => optional($item->expires_at)->toIso8601String(),
            'author' => $item->author ? [
                'id' => $item->author->id,
                'name' => $item->author->name,
            ] : null,
            'created_at' => optional($item->created_at)->toIso8601String(),
            'updated_at' => optional($item->updated_at)->toIso8601String(),
        ];
    }
}

