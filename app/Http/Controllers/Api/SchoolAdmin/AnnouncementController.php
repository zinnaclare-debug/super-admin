<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\School;
use App\Models\SchoolClass;
use App\Support\ClassTemplateSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $schoolId = (int) $user->school_id;

        $request->validate([
            'level' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
        ]);

        $allowedLevels = $this->resolveAllowedLevels($schoolId);
        $requestedLevel = $this->normalizeLevel($request->input('level'));
        if ($requestedLevel !== null && !in_array($requestedLevel, $allowedLevels, true)) {
            return response()->json(['message' => 'Invalid level selected.'], 422);
        }

        $query = Announcement::query()
            ->where('school_id', $schoolId)
            ->when($requestedLevel !== null, function ($q) use ($requestedLevel) {
                $q->where('level', $requestedLevel);
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
            'level' => ['nullable', 'string', 'max:60'],
            'expires_at' => ['nullable', 'date'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,webm,mov', 'max:51200'],
        ]);

        $allowedLevels = $this->resolveAllowedLevels($schoolId);
        $level = $this->normalizeLevel($payload['level'] ?? null);
        if ($level !== null && !in_array($level, $allowedLevels, true)) {
            return response()->json(['message' => 'Invalid level selected.'], 422);
        }

        $mediaPath = null;
        $mediaType = null;
        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store("schools/{$schoolId}/announcements", 'public');
            $mediaType = $this->mediaTypeFromFile($request->file('media'));
        }

        $announcement = Announcement::create([
            'school_id' => $schoolId,
            'created_by_user_id' => $user->id,
            'title' => trim($payload['title']),
            'message' => trim($payload['message']),
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'level' => $level,
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
            'level' => ['sometimes', 'nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'remove_media' => ['sometimes', 'boolean'],
            'media' => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,webm,mov', 'max:51200'],
        ]);

        $allowedLevels = $this->resolveAllowedLevels($schoolId);

        if (array_key_exists('title', $payload)) {
            $announcement->title = trim((string) $payload['title']);
        }

        if (array_key_exists('message', $payload)) {
            $announcement->message = trim((string) $payload['message']);
        }

        if (array_key_exists('level', $payload)) {
            $level = $this->normalizeLevel($payload['level']);
            if ($level !== null && !in_array($level, $allowedLevels, true)) {
                return response()->json(['message' => 'Invalid level selected.'], 422);
            }
            $announcement->level = $level;
        }

        if (array_key_exists('is_active', $payload)) {
            $announcement->is_active = (bool) $payload['is_active'];
        }

        if (array_key_exists('expires_at', $payload)) {
            $announcement->expires_at = $payload['expires_at'];
        }

        if ((bool) ($payload['remove_media'] ?? false)) {
            $this->deleteMedia($announcement->media_path);
            $announcement->media_path = null;
            $announcement->media_type = null;
        }

        if ($request->hasFile('media')) {
            $this->deleteMedia($announcement->media_path);
            $announcement->media_path = $request->file('media')->store("schools/{$schoolId}/announcements", 'public');
            $announcement->media_type = $this->mediaTypeFromFile($request->file('media'));
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

        $this->deleteMedia($announcement->media_path);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    private function toPayload(Announcement $item): array
    {
        $audience = $item->level
            ? $this->prettyLevel($item->level) . ' only'
            : 'School-wide';

        return [
            'id' => $item->id,
            'title' => $item->title,
            'message' => $item->message,
            'media_type' => $item->media_type,
            'media_url' => $this->mediaUrl($item->media_path),
            'level' => $item->level,
            'audience' => $audience,
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

    private function mediaTypeFromFile($file): ?string
    {
        $mimeType = strtolower((string) $file->getMimeType());
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return null;
    }

    private function mediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);

        return str_starts_with($relativeOrAbsolute, 'http://') || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
    }

    private function deleteMedia(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function normalizeLevel(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        return $normalized !== '' ? $normalized : null;
    }

    private function prettyLevel(string $value): string
    {
        return ucwords(str_replace('_', ' ', strtolower(trim($value))));
    }

    private function resolveAllowedLevels(int $schoolId): array
    {
        $fromClasses = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->pluck('level')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        $school = School::query()->find($schoolId);
        $fromTemplates = $school
            ? ClassTemplateSchema::activeLevelKeys(ClassTemplateSchema::normalize($school->class_templates))
            : [];

        return collect(array_merge($fromClasses, $fromTemplates))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}