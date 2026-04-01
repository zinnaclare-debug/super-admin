<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAdmissionApplication;
use App\Support\SchoolPublicWebsiteData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicSchoolWebsiteController extends Controller
{
    public function show(Request $request)
    {
        $school = $this->tenantSchool($request);

        if (! $school) {
            return response()->json([
                'is_tenant' => false,
                'school' => null,
            ]);
        }

        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);
        $websiteContent = SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);
        $entranceExamConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);

        return response()->json([
            'is_tenant' => true,
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'subdomain' => $school->subdomain,
                'logo_url' => $this->storageUrl($school->logo_path),
                'location' => $school->location,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'website_content' => $websiteContent,
                'entrance_exam' => [
                    'enabled' => (bool) $entranceExamConfig['enabled'],
                    'application_open' => (bool) $entranceExamConfig['application_open'],
                    'verification_open' => (bool) $entranceExamConfig['verification_open'],
                    'apply_intro' => $entranceExamConfig['apply_intro'],
                    'exam_intro' => $entranceExamConfig['exam_intro'],
                    'verify_intro' => $entranceExamConfig['verify_intro'],
                    'available_classes' => array_values(array_map(
                        static fn (array $exam) => [
                            'class_name' => $exam['class_name'],
                            'enabled' => (bool) $exam['enabled'],
                            'question_count' => count((array) ($exam['questions'] ?? [])),
                            'duration_minutes' => (int) $exam['duration_minutes'],
                            'pass_mark' => (int) $exam['pass_mark'],
                        ],
                        (array) ($entranceExamConfig['class_exams'] ?? [])
                    )),
                ],
            ],
        ]);
    }

    public function applyNow(Request $request)
    {
        $school = $this->tenantSchoolOrFail($request);
        $availableClasses = SchoolPublicWebsiteData::availableClasses($school);
        $websiteContent = SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);
        $entranceExamConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig($school->entrance_exam_config, $availableClasses);

        if (! $websiteContent['show_apply_now'] || ! $entranceExamConfig['application_open']) {
            return response()->json(['message' => 'Applications are currently closed for this school.'], 403);
        }

        $payload = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'applying_for_class' => ['required', 'string', 'max:80'],
        ]);

        $selectedClass = trim((string) $payload['applying_for_class']);
        if (! empty($availableClasses) && ! in_array($selectedClass, $availableClasses, true)) {
            return response()->json(['message' => 'Invalid class selected.'], 422);
        }

        $application = SchoolAdmissionApplication::create([
            'school_id' => $school->id,
            'application_number' => $this->generateApplicationNumber($school),
            'full_name' => trim((string) $payload['full_name']),
            'phone' => trim((string) $payload['phone']),
            'email' => strtolower(trim((string) $payload['email'])),
            'applying_for_class' => $selectedClass,
            'exam_status' => 'pending',
        ]);

        $classExam = SchoolPublicWebsiteData::findClassExam($entranceExamConfig, $selectedClass);

        return response()->json([
            'message' => 'Application submitted successfully.',
            'data' => [
                'application_number' => $application->application_number,
                'full_name' => $application->full_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'applying_for_class' => $application->applying_for_class,
                'exam_available' => (bool) ($classExam['enabled'] ?? false) && count((array) ($classExam['questions'] ?? [])) > 0,
            ],
        ], 201);
    }

    public function lookupEntranceExam(Request $request)
    {
        $school = $this->tenantSchoolOrFail($request);
        $application = $this->findApplication($school, $request);
        $entranceExamConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig(
            $school->entrance_exam_config,
            SchoolPublicWebsiteData::availableClasses($school)
        );

        if (! $entranceExamConfig['enabled']) {
            return response()->json(['message' => 'Entrance exam is not enabled for this school.'], 403);
        }

        $classExam = SchoolPublicWebsiteData::findClassExam($entranceExamConfig, $application->applying_for_class);
        if (! $classExam || ! $classExam['enabled'] || count($classExam['questions']) === 0) {
            return response()->json(['message' => 'No active entrance exam is configured for this class yet.'], 422);
        }

        if ($application->exam_status === 'completed') {
            return response()->json([
                'already_submitted' => true,
                'data' => $this->applicationPayload($application),
            ]);
        }

        return response()->json([
            'already_submitted' => false,
            'data' => [
                'application' => $this->applicationPayload($application),
                'exam' => SchoolPublicWebsiteData::publicFacingClassExam($classExam),
            ],
        ]);
    }

    public function submitEntranceExam(Request $request)
    {
        $school = $this->tenantSchoolOrFail($request);
        $application = $this->findApplication($school, $request);
        $entranceExamConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig(
            $school->entrance_exam_config,
            SchoolPublicWebsiteData::availableClasses($school)
        );

        if (! $entranceExamConfig['enabled']) {
            return response()->json(['message' => 'Entrance exam is not enabled for this school.'], 403);
        }

        if ($application->exam_status === 'completed') {
            return response()->json(['message' => 'Entrance exam has already been submitted.'], 422);
        }

        $payload = $request->validate([
            'application_number' => ['required', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'answers' => ['required', 'array'],
        ]);

        $classExam = SchoolPublicWebsiteData::findClassExam($entranceExamConfig, $application->applying_for_class);
        if (! $classExam || ! $classExam['enabled'] || count($classExam['questions']) === 0) {
            return response()->json(['message' => 'No active entrance exam is configured for this class yet.'], 422);
        }

        $answers = collect($payload['answers'] ?? [])
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->all();

        $questions = array_values($classExam['questions']);
        $correctAnswers = 0;
        $answeredCount = 0;

        foreach ($questions as $index => $question) {
            $answer = $answers[$index] ?? '';
            if ($answer !== '') {
                $answeredCount++;
            }
            if ($answer !== '' && strtoupper((string) $question['correct_option']) === $answer) {
                $correctAnswers++;
            }
        }

        $questionCount = max(1, count($questions));
        $score = (int) round(($correctAnswers / $questionCount) * 100);
        $resultStatus = $score >= (int) $classExam['pass_mark'] ? 'passed' : 'failed';

        $application->exam_status = 'completed';
        $application->score = $score;
        $application->result_status = $resultStatus;
        $application->exam_submitted_at = now();
        $application->exam_answers = array_values($answers);
        $application->exam_result = [
            'question_count' => count($questions),
            'correct_answers' => $correctAnswers,
            'answered_count' => $answeredCount,
            'pass_mark' => (int) $classExam['pass_mark'],
            'duration_minutes' => (int) $classExam['duration_minutes'],
        ];
        $application->save();

        return response()->json([
            'message' => 'Entrance exam submitted successfully.',
            'data' => $this->applicationPayload($application),
        ]);
    }

    public function verifyScore(Request $request)
    {
        $school = $this->tenantSchoolOrFail($request);
        $application = $this->findApplication($school, $request);
        $entranceExamConfig = SchoolPublicWebsiteData::normalizeEntranceExamConfig(
            $school->entrance_exam_config,
            SchoolPublicWebsiteData::availableClasses($school)
        );

        if (! $entranceExamConfig['verification_open']) {
            return response()->json(['message' => 'Score verification is currently unavailable.'], 403);
        }

        return response()->json([
            'data' => $this->applicationPayload($application),
        ]);
    }

    private function tenantSchool(Request $request): ?School
    {
        return $request->attributes->get((string) config('tenancy.request_key', 'tenant_school'));
    }

    private function tenantSchoolOrFail(Request $request): School
    {
        $school = $this->tenantSchool($request);

        abort_if(! $school, 404, 'School website not found for this domain.');

        return $school;
    }

    private function findApplication(School $school, Request $request): SchoolAdmissionApplication
    {
        $payload = $request->validate([
            'application_number' => ['required', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = $this->normalizePhone($payload['phone'] ?? null);

        if ($email === '' && $phone === '') {
            abort(422, 'Provide the email or phone number used for the application.');
        }

        $application = SchoolAdmissionApplication::query()
            ->where('school_id', $school->id)
            ->where('application_number', trim((string) $payload['application_number']))
            ->first();

        if (! $application) {
            abort(404, 'Application not found.');
        }

        $emailMatches = $email !== '' && strtolower((string) $application->email) === $email;
        $phoneMatches = $phone !== '' && $this->normalizePhone($application->phone) === $phone;

        if (! $emailMatches && ! $phoneMatches) {
            abort(404, 'Application not found.');
        }

        return $application;
    }

    private function applicationPayload(SchoolAdmissionApplication $application): array
    {
        return [
            'application_number' => $application->application_number,
            'full_name' => $application->full_name,
            'phone' => $application->phone,
            'email' => $application->email,
            'applying_for_class' => $application->applying_for_class,
            'exam_status' => $application->exam_status,
            'score' => $application->score,
            'result_status' => $application->result_status,
            'submitted_at' => optional($application->exam_submitted_at)->toIso8601String(),
            'exam_result' => $application->exam_result,
        ];
    }

    private function generateApplicationNumber(School $school): string
    {
        do {
            $candidate = sprintf('ADM-%d-%s', $school->id, strtoupper(Str::random(8)));
        } while (SchoolAdmissionApplication::query()->where('application_number', $candidate)->exists());

        return $candidate;
    }

    private function normalizePhone(mixed $value): string
    {
        return preg_replace('/[^\d+]/', '', trim((string) ($value ?? ''))) ?: '';
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
