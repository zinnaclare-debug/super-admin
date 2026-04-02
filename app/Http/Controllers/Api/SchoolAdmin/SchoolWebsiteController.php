<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAdmissionApplication;
use App\Models\SchoolWebsiteContent;
use App\Support\SchoolPublicWebsiteData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolWebsiteController extends Controller
{
    public function showWebsite(Request $request)
    {
        $school = $this->schoolFromRequest($request);

        return response()->json([
            'website_content' => SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school),
        ]);
    }

    public function updateWebsite(Request $request)
    {
        $school = $this->schoolFromRequest($request);

        $payload = $request->validate([
            'website_content' => ['required', 'array'],
            'website_content.hero_title' => ['nullable', 'string', 'max:160'],
            'website_content.hero_subtitle' => ['nullable', 'string', 'max:600'],
            'website_content.about_title' => ['nullable', 'string', 'max:120'],
            'website_content.about_text' => ['nullable', 'string', 'max:3000'],
            'website_content.core_values_text' => ['nullable', 'string', 'max:3000'],
            'website_content.mission_text' => ['nullable', 'string', 'max:3000'],
            'website_content.admissions_intro' => ['nullable', 'string', 'max:1200'],
            'website_content.address' => ['nullable', 'string', 'max:255'],
            'website_content.contact_email' => ['nullable', 'email', 'max:255'],
            'website_content.contact_phone' => ['nullable', 'string', 'max:40'],
            'website_content.primary_color' => ['nullable', 'string', 'max:7'],
            'website_content.accent_color' => ['nullable', 'string', 'max:7'],
            'website_content.show_apply_now' => ['nullable', 'boolean'],
            'website_content.show_entrance_exam' => ['nullable', 'boolean'],
            'website_content.show_verify_score' => ['nullable', 'boolean'],
        ]);

        $school->website_content = SchoolPublicWebsiteData::normalizeWebsiteContent($payload['website_content'] ?? [], $school);
        $school->save();

        return response()->json([
            'message' => 'Website updated successfully.',
            'data' => [
                'website_content' => SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school),
            ],
        ]);
    }

    public function showEntranceExam(Request $request)
    {
        $school = $this->schoolFromRequest($request);
        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);

        return response()->json([
            'available_classes' => $availableClasses,
            'entrance_exam_config' => SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses),
        ]);
    }

    public function updateEntranceExam(Request $request)
    {
        $school = $this->schoolFromRequest($request);
        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);

        $payload = $request->validate([
            'entrance_exam_config' => ['required', 'array'],
            'entrance_exam_config.enabled' => ['nullable', 'boolean'],
            'entrance_exam_config.application_open' => ['nullable', 'boolean'],
            'entrance_exam_config.verification_open' => ['nullable', 'boolean'],
            'entrance_exam_config.apply_intro' => ['nullable', 'string', 'max:1500'],
            'entrance_exam_config.exam_intro' => ['nullable', 'string', 'max:1500'],
            'entrance_exam_config.verify_intro' => ['nullable', 'string', 'max:1500'],
            'entrance_exam_config.class_exams' => ['nullable', 'array'],
            'entrance_exam_config.class_exams.*.class_name' => ['required', 'string', 'max:80'],
            'entrance_exam_config.class_exams.*.enabled' => ['nullable', 'boolean'],
            'entrance_exam_config.class_exams.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:180'],
            'entrance_exam_config.class_exams.*.pass_mark' => ['nullable', 'integer', 'min:0', 'max:100'],
            'entrance_exam_config.class_exams.*.instructions' => ['nullable', 'string', 'max:3000'],
            'entrance_exam_config.class_exams.*.questions' => ['nullable', 'array'],
            'entrance_exam_config.class_exams.*.questions.*.question' => ['nullable', 'string', 'max:500'],
            'entrance_exam_config.class_exams.*.questions.*.option_a' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.option_b' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.option_c' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.option_d' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.correct_option' => ['nullable', 'in:A,B,C,D'],
        ]);

        $school->entrance_exam_config = SchoolPublicWebsiteData::normalizeEntranceExamConfig(
            $payload['entrance_exam_config'] ?? [],
            $availableClasses
        );
        $school->save();

        return response()->json([
            'message' => 'Entrance exam settings updated successfully.',
            'data' => [
                'available_classes' => $availableClasses,
                'entrance_exam_config' => SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses),
            ],
        ]);
    }

    public function applications(Request $request)
    {
        $school = $this->schoolFromRequest($request);

        $applications = SchoolAdmissionApplication::query()
            ->where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->get()
            ->values()
            ->map(function (SchoolAdmissionApplication $application, int $index) {
                return [
                    'id' => $application->id,
                    'sn' => $index + 1,
                    'application_number' => $application->application_number,
                    'full_name' => $application->full_name,
                    'phone' => $application->phone,
                    'email' => $application->email,
                    'information' => trim($application->phone . ' | ' . $application->email, ' |'),
                    'applying_for_class' => $application->applying_for_class,
                    'exam_status' => $application->exam_status,
                    'score' => $application->score,
                    'result_status' => $application->result_status,
                    'submitted_at' => optional($application->exam_submitted_at)->toIso8601String(),
                    'created_at' => optional($application->created_at)->toIso8601String(),
                ];
            });

        return response()->json(['data' => $applications]);
    }

    public function contents(Request $request)
    {
        $school = $this->schoolFromRequest($request);

        $contents = SchoolWebsiteContent::query()
            ->where('school_id', $school->id)
            ->latest()
            ->get()
            ->map(fn (SchoolWebsiteContent $content) => $this->contentPayload($content))
            ->values();

        return response()->json(['data' => $contents]);
    }

    public function storeContent(Request $request)
    {
        $school = $this->schoolFromRequest($request);

        $payload = $request->validate([
            'heading' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $imagePaths = [];
        foreach ($request->file('photos', []) as $photo) {
            $imagePaths[] = $photo->store("schools/{$school->id}/website-contents", 'public');
        }

        $content = SchoolWebsiteContent::create([
            'school_id' => $school->id,
            'heading' => trim((string) $payload['heading']),
            'content' => trim((string) $payload['content']),
            'image_paths' => $imagePaths,
        ]);

        return response()->json([
            'message' => 'School content created successfully.',
            'data' => $this->contentPayload($content),
        ], 201);
    }

    public function updateContent(Request $request, SchoolWebsiteContent $content)
    {
        $school = $this->schoolFromRequest($request);
        abort_if((int) $content->school_id !== (int) $school->id, 404, 'Content not found.');

        $payload = $request->validate([
            'heading' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'keep_image_paths' => ['nullable', 'array'],
            'keep_image_paths.*' => ['string'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $existingPaths = collect($content->image_paths ?? [])->filter()->values();
        $keptPaths = collect($payload['keep_image_paths'] ?? [])
            ->map(fn ($path) => trim((string) $path))
            ->filter(fn ($path) => $path !== '' && $existingPaths->contains($path))
            ->values();

        $newPhotos = $request->file('photos', []);
        $totalImages = $keptPaths->count() + count($newPhotos);
        if ($totalImages > 5) {
            return response()->json(['message' => 'A content entry can only have up to 5 photos.'], 422);
        }

        $removedPaths = $existingPaths->diff($keptPaths)->values();
        foreach ($removedPaths as $removedPath) {
            Storage::disk('public')->delete($removedPath);
        }

        $newPaths = [];
        foreach ($newPhotos as $photo) {
            $newPaths[] = $photo->store("schools/{$school->id}/website-contents", 'public');
        }

        $content->heading = trim((string) $payload['heading']);
        $content->content = trim((string) $payload['content']);
        $content->image_paths = $keptPaths->concat($newPaths)->values()->all();
        $content->save();

        return response()->json([
            'message' => 'School content updated successfully.',
            'data' => $this->contentPayload($content->fresh()),
        ]);
    }

    public function destroyContent(Request $request, SchoolWebsiteContent $content)
    {
        $school = $this->schoolFromRequest($request);
        abort_if((int) $content->school_id !== (int) $school->id, 404, 'Content not found.');

        foreach ((array) ($content->image_paths ?? []) as $path) {
            Storage::disk('public')->delete($path);
        }

        $content->delete();

        return response()->json([
            'message' => 'School content deleted successfully.',
        ]);
    }

    private function schoolFromRequest(Request $request): School
    {
        $schoolId = (int) $request->user()->school_id;
        return School::query()->findOrFail($schoolId);
    }

    private function contentPayload(SchoolWebsiteContent $content): array
    {
        $paths = collect($content->image_paths ?? [])->filter()->values();

        return [
            'id' => $content->id,
            'heading' => $content->heading,
            'content' => $content->content,
            'image_paths' => $paths->all(),
            'image_urls' => $paths
                ->map(fn ($path) => $this->storageUrl($path))
                ->filter()
                ->values()
                ->all(),
            'created_at' => optional($content->created_at)->toIso8601String(),
            'display_date' => optional($content->created_at)->format('F j, Y'),
        ];
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $relativeOrAbsolute = Storage::disk('public')->url($path);

        return str_starts_with($relativeOrAbsolute, 'http://') || str_starts_with($relativeOrAbsolute, 'https://')
            ? $relativeOrAbsolute
            : url($relativeOrAbsolute);
    }
}
