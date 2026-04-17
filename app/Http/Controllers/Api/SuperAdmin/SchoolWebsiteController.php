<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAdmissionApplication;
use App\Support\SchoolPublicWebsiteData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolWebsiteController extends Controller
{
    public function show(School $school)
    {
        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);
        $websiteContent = SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);
        $entranceExamConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);

        return response()->json([
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'logo_url' => $this->storageUrl($school->logo_path),
            ],
            'available_classes' => $availableClasses,
            'website_content' => $websiteContent,
            'entrance_exam_config' => $entranceExamConfig,
        ]);
    }

    public function upsert(Request $request, School $school)
    {
        $payload = $request->validate([
            'website_content' => ['sometimes', 'array'],
            'website_content.hero_title' => ['nullable', 'string', 'max:160'],
            'website_content.hero_subtitle' => ['nullable', 'string', 'max:600'],
            'website_content.about_title' => ['nullable', 'string', 'max:120'],
            'website_content.about_text' => ['nullable', 'string', 'max:3000'],
            'website_content.vision_text' => ['nullable', 'string', 'max:3000'],
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
            'website_content.social_links' => ['nullable', 'array'],
            'website_content.social_links.x.enabled' => ['nullable', 'boolean'],
            'website_content.social_links.x.url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! SchoolPublicWebsiteData::validateSocialLink('x', $value)) {
                    $fail('X link must be a valid x.com or twitter.com URL.');
                }
            }],
            'website_content.social_links.facebook.enabled' => ['nullable', 'boolean'],
            'website_content.social_links.facebook.url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! SchoolPublicWebsiteData::validateSocialLink('facebook', $value)) {
                    $fail('Facebook link must be a valid facebook.com URL.');
                }
            }],
            'website_content.social_links.tiktok.enabled' => ['nullable', 'boolean'],
            'website_content.social_links.tiktok.url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! SchoolPublicWebsiteData::validateSocialLink('tiktok', $value)) {
                    $fail('TikTok link must be a valid tiktok.com URL.');
                }
            }],
            'website_content.social_links.whatsapp.enabled' => ['nullable', 'boolean'],
            'website_content.social_links.whatsapp.url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! SchoolPublicWebsiteData::validateSocialLink('whatsapp', $value)) {
                    $fail('WhatsApp link must be a valid wa.me, chat.whatsapp.com, or whatsapp.com URL.');
                }
            }],
            'entrance_exam_config' => ['sometimes', 'array'],
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

        if (! $request->has('website_content') && ! $request->has('entrance_exam_config')) {
            return response()->json([
                'message' => 'Provide website_content or entrance_exam_config.',
            ], 422);
        }

        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);

        $school->website_content = $request->has('website_content')
            ? SchoolPublicWebsiteData::normalizeWebsiteContent($payload['website_content'] ?? [], $school)
            : SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);

        $school->entrance_exam_config = $request->has('entrance_exam_config')
            ? SchoolPublicWebsiteData::normalizeEntranceExamConfig($payload['entrance_exam_config'] ?? [], $availableClasses)
            : SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);

        $school->save();

        return response()->json([
            'message' => 'School website content updated successfully.',
            'data' => [
                'website_content' => SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school),
                'entrance_exam_config' => SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses),
                'available_classes' => $availableClasses,
            ],
        ]);
    }

    public function applications(School $school)
    {
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





