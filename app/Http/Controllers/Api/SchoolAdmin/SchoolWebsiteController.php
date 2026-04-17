<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\QuestionBankQuestion;
use App\Models\School;
use App\Models\SchoolAdmissionApplication;
use App\Models\SchoolClass;
use App\Models\SchoolWebsiteContent;
use App\Models\Subject;
use App\Models\TermSubject;
use App\Support\SchoolPublicWebsiteData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolWebsiteController extends Controller
{
    public function showWebsite(Request $request)
    {
        $school = $this->schoolFromRequest($request);

        return response()->json([
            'website_content' => SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school),
        ]);
    }


    public function listApplications(Request $request)
{
    $school = $request->user()->school;

    $query = SchoolAdmissionApplication::query()
        ->where('school_id', $school->id);

    $search = trim((string) $request->query('search', ''));
    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('application_number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    $class = trim((string) $request->query('class', ''));
    if ($class !== '') {
        $query->where('applying_for_class', $class);
    }

    $applications = $query
        ->latest()
        ->paginate((int) $request->query('per_page', 20));

    return response()->json([
        'data' => $applications->items(),
        'meta' => [
            'current_page' => $applications->currentPage(),
            'last_page' => $applications->lastPage(),
            'per_page' => $applications->perPage(),
            'total' => $applications->total(),
        ],
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
            'available_class_groups' => SchoolPublicWebsiteData::availableClassGroups($school),
            'entrance_exam_config' => SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses),
        ]);
    }

    public function updateEntranceExam(Request $request)
    {
        $school = $this->schoolFromRequest($request);
        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);
        $currentConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);

        $payload = $request->validate([
            'entrance_exam_config' => ['required', 'array'],
            'entrance_exam_config.enabled' => ['nullable', 'boolean'],
            'entrance_exam_config.application_open' => ['nullable', 'boolean'],
            'entrance_exam_config.verification_open' => ['nullable', 'boolean'],
            'entrance_exam_config.application_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'entrance_exam_config.application_fee_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'entrance_exam_config.application_fee_total' => ['nullable', 'numeric', 'min:0', 'max:20000000'],
            'entrance_exam_config.apply_intro' => ['nullable', 'string', 'max:1500'],
            'entrance_exam_config.exam_intro' => ['nullable', 'string', 'max:1500'],
            'entrance_exam_config.verify_intro' => ['nullable', 'string', 'max:1500'],
            'entrance_exam_config.class_exams' => ['nullable', 'array'],
            'entrance_exam_config.class_exams.*.class_name' => ['required', 'string', 'max:80'],
            'entrance_exam_config.class_exams.*.enabled' => ['nullable', 'boolean'],
            'entrance_exam_config.class_exams.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:180'],
            'entrance_exam_config.class_exams.*.pass_mark' => ['nullable', 'integer', 'min:0', 'max:100'],
            'entrance_exam_config.class_exams.*.instructions' => ['nullable', 'string', 'max:3000'],
            'entrance_exam_config.class_exams.*.application_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'entrance_exam_config.class_exams.*.questions' => ['nullable', 'array'],
            'entrance_exam_config.class_exams.*.questions.*.id' => ['nullable', 'string', 'max:80'],
            'entrance_exam_config.class_exams.*.questions.*.subject_id' => ['nullable', 'integer'],
            'entrance_exam_config.class_exams.*.questions.*.subject_name' => ['nullable', 'string', 'max:120'],
            'entrance_exam_config.class_exams.*.questions.*.question_bank_question_id' => ['nullable', 'integer'],
            'entrance_exam_config.class_exams.*.questions.*.source_type' => ['nullable', 'in:manual,question_bank,ai'],
            'entrance_exam_config.class_exams.*.questions.*.question' => ['nullable', 'string', 'max:500'],
            'entrance_exam_config.class_exams.*.questions.*.option_a' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.option_b' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.option_c' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.option_d' => ['nullable', 'string', 'max:255'],
            'entrance_exam_config.class_exams.*.questions.*.correct_option' => ['nullable', 'in:A,B,C,D'],
        ]);

        $incomingConfig = $payload['entrance_exam_config'] ?? [];
        $currentExamMap = collect($currentConfig['class_exams'] ?? [])
            ->keyBy(fn (array $exam) => strtolower(trim((string) ($exam['class_name'] ?? ''))));

        if (array_key_exists('class_exams', $incomingConfig) && is_array($incomingConfig['class_exams'])) {
            $incomingConfig['class_exams'] = collect($incomingConfig['class_exams'])
                ->map(function (array $exam) use ($currentExamMap) {
                    $key = strtolower(trim((string) ($exam['class_name'] ?? '')));
                    if (! array_key_exists('questions', $exam) && $currentExamMap->has($key)) {
                        $exam['questions'] = $currentExamMap->get($key)['questions'] ?? [];
                    }
                    return $exam;
                })
                ->all();
        }

        $school->entrance_exam_config = SchoolPublicWebsiteData::normalizeEntranceExamConfig($incomingConfig, $availableClasses);
        $school->save();

        return response()->json([
            'message' => 'Entrance exam settings updated successfully.',
            'data' => [
                'available_classes' => $availableClasses,
                'available_class_groups' => SchoolPublicWebsiteData::availableClassGroups($school),
                'entrance_exam_config' => SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses),
            ],
        ]);
    }

    public function classQuestions(Request $request, string $className)
    {
        $school = $this->schoolFromRequest($request);
        $resolvedClassName = $this->resolveEntranceExamClassName($school, $className);
        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);
        $config = SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);
        $classExam = SchoolPublicWebsiteData::findClassExam($config, $resolvedClassName) ?? [
            'class_name' => $resolvedClassName,
            'enabled' => false,
            'duration_minutes' => 30,
            'pass_mark' => 50,
            'instructions' => '',
            'questions' => [],
        ];

        $subjects = $this->subjectsForClass($school, $resolvedClassName);
        $selectedQuestionBankIds = collect($classExam['questions'] ?? [])
            ->pluck('question_bank_question_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'class_name' => $resolvedClassName,
                'class_exam' => $classExam,
                'subjects' => $subjects,
                'selected_question_bank_ids' => $selectedQuestionBankIds,
            ],
        ]);
    }

    public function exportClassQuestions(Request $request, string $className)
    {
        $school = $this->schoolFromRequest($request);
        $resolvedClassName = $this->resolveEntranceExamClassName($school, $className);
        $payload = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['required', 'integer'],
        ]);

        $subjects = $this->subjectsForClass($school, $resolvedClassName);
        $subjectMap = collect($subjects)->keyBy('subject_id');
        $allowedSubjectIds = $subjectMap->keys()->map(fn ($id) => (int) $id)->all();

        $questions = QuestionBankQuestion::query()
            ->where('school_id', $school->id)
            ->whereIn('subject_id', $allowedSubjectIds)
            ->whereIn('id', $payload['question_ids'])
            ->orderByDesc('id')
            ->get();

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'No valid question bank questions selected for this class.'], 422);
        }

        $addedCount = 0;

        $updatedClassExam = $this->mutateClassExamQuestions(
            $school,
            $resolvedClassName,
            function (array $questionsList) use ($questions, $subjectMap, &$addedCount) {
                $addedCount = 0;
                $existingBankIds = collect($questionsList)
                    ->pluck('question_bank_question_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $existingFingerprints = collect($questionsList)
                    ->map(fn (array $question) => $this->entranceQuestionFingerprint($question))
                    ->filter()
                    ->values()
                    ->all();

                foreach ($questions as $question) {
                    $fingerprint = $this->entranceQuestionFingerprint([
                        'question' => $question->question_text,
                        'option_a' => $question->option_a,
                        'option_b' => $question->option_b,
                        'option_c' => $question->option_c,
                        'option_d' => $question->option_d,
                    ]);

                    if (in_array((int) $question->id, $existingBankIds, true) || in_array($fingerprint, $existingFingerprints, true)) {
                        continue;
                    }

                    $subject = $subjectMap->get((int) $question->subject_id);
                    $questionsList[] = [
                        'id' => (string) Str::uuid(),
                        'subject_id' => (int) $question->subject_id,
                        'subject_name' => (string) ($subject['subject_name'] ?? $question->subject_name ?? ''),
                        'question_bank_question_id' => (int) $question->id,
                        'source_type' => 'question_bank',
                        'question' => (string) $question->question_text,
                        'option_a' => (string) $question->option_a,
                        'option_b' => (string) $question->option_b,
                        'option_c' => (string) $question->option_c,
                        'option_d' => (string) $question->option_d,
                        'correct_option' => (string) $question->correct_option,
                    ];
                    $existingBankIds[] = (int) $question->id;
                    $existingFingerprints[] = $fingerprint;
                    $addedCount++;
                }

                return $questionsList;
            }
        );

        return response()->json([
            'message' => $addedCount > 0 ? 'Selected questions added to entrance exam.' : 'Selected questions were already added to this entrance exam.',
            'data' => [
                'class_exam' => $updatedClassExam,
            ],
        ]);
    }

    public function storeClassQuestion(Request $request, string $className)
    {
        $school = $this->schoolFromRequest($request);
        $resolvedClassName = $this->resolveEntranceExamClassName($school, $className);
        $payload = $request->validate([
            'subject_id' => ['required', 'integer'],
            'question' => ['required', 'string', 'max:500'],
            'option_a' => ['required', 'string', 'max:255'],
            'option_b' => ['required', 'string', 'max:255'],
            'option_c' => ['required', 'string', 'max:255'],
            'option_d' => ['required', 'string', 'max:255'],
            'correct_option' => ['required', 'in:A,B,C,D'],
        ]);

        $subjects = $this->subjectsForClass($school, $resolvedClassName);
        $subjectMap = collect($subjects)->keyBy('subject_id');
        $subject = $subjectMap->get((int) $payload['subject_id']);

        if (! $subject) {
            return response()->json(['message' => 'Selected subject is not registered for this class.'], 422);
        }

        $updatedClassExam = $this->mutateClassExamQuestions(
            $school,
            $resolvedClassName,
            function (array $questionsList) use ($payload, $subject) {
                $questionsList[] = [
                    'id' => (string) Str::uuid(),
                    'subject_id' => (int) $payload['subject_id'],
                    'subject_name' => (string) ($subject['subject_name'] ?? ''),
                    'question_bank_question_id' => null,
                    'source_type' => 'manual',
                    'question' => trim((string) $payload['question']),
                    'option_a' => trim((string) $payload['option_a']),
                    'option_b' => trim((string) $payload['option_b']),
                    'option_c' => trim((string) $payload['option_c']),
                    'option_d' => trim((string) $payload['option_d']),
                    'correct_option' => trim((string) $payload['correct_option']),
                ];

                return $questionsList;
            }
        );

        return response()->json([
            'message' => 'Question added to entrance exam successfully.',
            'data' => [
                'class_exam' => $updatedClassExam,
            ],
        ], 201);
    }

    public function destroyClassQuestion(Request $request, string $className, string $questionId)
    {
        $school = $this->schoolFromRequest($request);
        $resolvedClassName = $this->resolveEntranceExamClassName($school, $className);

        $removed = false;

        $updatedClassExam = $this->mutateClassExamQuestions(
            $school,
            $resolvedClassName,
            function (array $questionsList) use ($questionId, &$removed) {
                $removed = false;
                $filtered = collect($questionsList)
                    ->reject(function (array $question) use ($questionId, &$removed) {
                        $matches = (string) ($question['id'] ?? '') === $questionId;
                        if ($matches) {
                            $removed = true;
                        }
                        return $matches;
                    })
                    ->values()
                    ->all();

                return $filtered;
            }
        );

        if (! $removed) {
            return response()->json(['message' => 'Entrance exam question not found.'], 404);
        }

        return response()->json([
            'message' => 'Question removed from entrance exam.',
            'data' => [
                'class_exam' => $updatedClassExam,
            ],
        ]);
    }

        public function resetEntranceExamApplication(Request $request, SchoolAdmissionApplication $application)
    {
        $school = $this->schoolFromRequest($request);
        abort_if((int) $application->school_id !== (int) $school->id, 404, 'Application not found.');

        $payload = $request->validate([
            'rescheduled_for' => ['required', 'date'],
        ]);

        $rescheduledFor = Carbon::parse($payload['rescheduled_for']);

        $application->exam_status = 'pending';
        $application->score = null;
        $application->result_status = null;
        $application->admin_result_status = null;
        $application->exam_submitted_at = null;
        $application->exam_answers = [];
        $application->exam_result = null;
        $application->exam_rescheduled_for = $rescheduledFor;
        $application->exam_reset_at = now();
        $application->save();

        return response()->json([
            'message' => 'Entrance exam reset successfully.',
            'data' => $this->applicationPayload($application->fresh()),
        ]);
    }
    public function updateApplicationStatus(Request $request, SchoolAdmissionApplication $application)
    {
        $school = $this->schoolFromRequest($request);
        abort_if((int) $application->school_id !== (int) $school->id, 404, 'Application not found.');

        $payload = $request->validate([
            'admin_result_status' => ['nullable', Rule::in(['passed', 'failed'])],
        ]);

        $application->admin_result_status = $payload['admin_result_status'] ?? null;
        $application->save();

        return response()->json([
            'message' => 'Entrance exam result status updated successfully.',
            'data' => $this->applicationPayload($application->fresh()),
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
                return $this->applicationPayload($application, $index + 1);
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
    private function applicationPayload(SchoolAdmissionApplication $application, ?int $sn = null): array
    {
        return [
            'id' => $application->id,
            'sn' => $sn,
            'application_number' => $application->application_number,
            'full_name' => $application->full_name,
            'phone' => $application->phone,
            'email' => $application->email,
            'information' => trim($application->phone . ' | ' . $application->email, ' |'),
            'applying_for_class' => $application->applying_for_class,
            'exam_status' => $application->exam_status,
            'score' => $application->score,
            'result_status' => $application->result_status,
            'admin_result_status' => $application->admin_result_status,
            'submitted_at' => optional($application->exam_submitted_at)->toIso8601String(),
            'created_at' => optional($application->created_at)->toIso8601String(),
            'exam_rescheduled_for' => optional($application->exam_rescheduled_for)->toIso8601String(),
            'exam_reset_at' => optional($application->exam_reset_at)->toIso8601String(),
        ];
    }
    private function schoolFromRequest(Request $request): School
    {
        $schoolId = (int) $request->user()->school_id;
        return School::query()->findOrFail($schoolId);
    }

    private function resolveEntranceExamClassName(School $school, string $className): string
    {
        $availableClass = collect(SchoolPublicWebsiteData::availableClasses($school))
            ->first(fn ($available) => strtolower(trim((string) $available)) === strtolower(trim((string) $className)));

        abort_if(! $availableClass, 404, 'Class not found for entrance exam setup.');

        return (string) $availableClass;
    }

    private function subjectsForClass(School $school, string $className): array
    {
        $normalizedClassName = strtolower(trim((string) $className));
        $currentSessionId = AcademicSession::query()
            ->where('school_id', $school->id)
            ->where('status', 'current')
            ->value('id');

        $classQuery = SchoolClass::query()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(name) = ?', [$normalizedClassName]);

        if ($currentSessionId) {
            $classQuery->where('academic_session_id', $currentSessionId);
        }

        $classIds = $classQuery->pluck('id');
        if ($classIds->isEmpty()) {
            $classIds = SchoolClass::query()
                ->where('school_id', $school->id)
                ->whereRaw('LOWER(name) = ?', [$normalizedClassName])
                ->pluck('id');
        }

        if ($classIds->isEmpty()) {
            return [];
        }

        $termSubjects = TermSubject::query()
            ->join('subjects', 'subjects.id', '=', 'term_subjects.subject_id')
            ->when(
                $currentSessionId,
                fn ($query) => $query
                    ->join('terms', 'terms.id', '=', 'term_subjects.term_id')
                    ->where('terms.academic_session_id', $currentSessionId),
                fn ($query) => $query->leftJoin('terms', 'terms.id', '=', 'term_subjects.term_id')
            )
            ->where('term_subjects.school_id', $school->id)
            ->whereIn('term_subjects.class_id', $classIds)
            ->orderBy('subjects.name')
            ->get([
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.code as subject_code',
            ])
            ->unique('subject_id')
            ->values();

        if ($termSubjects->isEmpty()) {
            return [];
        }

        $questionsBySubject = QuestionBankQuestion::query()
            ->where('school_id', $school->id)
            ->whereIn('subject_id', $termSubjects->pluck('subject_id')->all())
            ->orderByDesc('id')
            ->get()
            ->groupBy('subject_id');

        return $termSubjects->map(function ($subject) use ($questionsBySubject) {
            $subjectQuestions = $questionsBySubject->get($subject->subject_id, collect())
                ->map(fn (QuestionBankQuestion $question) => [
                    'id' => (int) $question->id,
                    'question_text' => (string) $question->question_text,
                    'option_a' => (string) $question->option_a,
                    'option_b' => (string) $question->option_b,
                    'option_c' => (string) ($question->option_c ?? ''),
                    'option_d' => (string) ($question->option_d ?? ''),
                    'correct_option' => (string) $question->correct_option,
                    'explanation' => (string) ($question->explanation ?? ''),
                    'media_type' => (string) ($question->media_type ?? ''),
                    'media_url' => $question->media_path ? $this->storageUrl($question->media_path) : null,
                    'source_type' => (string) ($question->source_type ?? 'manual'),
                ])
                ->values()
                ->all();

            return [
                'subject_id' => (int) $subject->subject_id,
                'subject_name' => (string) $subject->subject_name,
                'subject_code' => (string) ($subject->subject_code ?? ''),
                'questions' => $subjectQuestions,
                'question_count' => count($subjectQuestions),
            ];
        })->all();
    }

    private function mutateClassExamQuestions(School $school, string $className, callable $callback): ?array
    {
        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);
        $config = SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);
        $classExam = SchoolPublicWebsiteData::findClassExam($config, $className) ?? [
            'class_name' => $className,
            'enabled' => false,
            'duration_minutes' => 30,
            'pass_mark' => 50,
            'instructions' => '',
            'questions' => [],
        ];

        $classExam['questions'] = $callback((array) ($classExam['questions'] ?? []));

        $config['class_exams'] = collect($config['class_exams'] ?? [])
            ->map(fn (array $exam) => strtolower(trim((string) ($exam['class_name'] ?? ''))) === strtolower(trim($className)) ? $classExam : $exam)
            ->values()
            ->all();

        $school->entrance_exam_config = SchoolPublicWebsiteData::normalizeEntranceExamConfig($config, $availableClasses);
        $school->save();

        $normalizedConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);

        return SchoolPublicWebsiteData::findClassExam($normalizedConfig, $className);
    }

    private function entranceQuestionFingerprint(array $question): string
    {
        $parts = [
            strtolower(trim((string) ($question['question'] ?? ''))),
            strtolower(trim((string) ($question['option_a'] ?? ''))),
            strtolower(trim((string) ($question['option_b'] ?? ''))),
            strtolower(trim((string) ($question['option_c'] ?? ''))),
            strtolower(trim((string) ($question['option_d'] ?? ''))),
        ];

        return trim(implode('|', array_map(
            fn ($value) => preg_replace('/\s+/', ' ', (string) $value) ?? '',
            $parts
        )));
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





