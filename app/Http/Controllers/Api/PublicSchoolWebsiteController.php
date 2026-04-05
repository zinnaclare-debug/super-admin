<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolAdmissionApplication;
use App\Models\SchoolWebsiteContent;
use App\Support\SchoolPublicWebsiteData;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
                'content_feed' => $this->contentFeedPayload($school, 1),
                'entrance_exam' => [
                    'enabled' => (bool) $entranceExamConfig['enabled'],
                    'application_open' => (bool) $entranceExamConfig['application_open'],
                    'verification_open' => (bool) $entranceExamConfig['verification_open'],
                    'apply_intro' => $entranceExamConfig['apply_intro'],
                    'exam_intro' => $entranceExamConfig['exam_intro'],
                    'verify_intro' => $entranceExamConfig['verify_intro'],
                    'application_fee_amount' => (float) ($entranceExamConfig['application_fee_amount'] ?? 0),
                    'application_fee_tax_rate' => (float) ($entranceExamConfig['application_fee_tax_rate'] ?? 1.6),
                    'application_fee_tax_amount' => (float) ($entranceExamConfig['application_fee_tax_amount'] ?? 0),
                    'application_fee_total' => (float) ($entranceExamConfig['application_fee_total'] ?? 0),
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

    public function contents(Request $request)
    {
        $school = $this->tenantSchoolOrFail($request);
        $page = max(1, (int) $request->query('page', 1));

        return response()->json($this->contentFeedPayload($school, $page));
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

        $feeAmount = (float) ($entranceExamConfig['application_fee_amount'] ?? 0);
        $taxRate = (float) ($entranceExamConfig['application_fee_tax_rate'] ?? 1.6);
        $taxAmount = round($feeAmount * ($taxRate / 100), 2);
        $total = round($feeAmount + $taxAmount, 2);

        $application = SchoolAdmissionApplication::create([
            'school_id' => $school->id,
            'application_number' => null,
            'full_name' => trim((string) $payload['full_name']),
            'phone' => trim((string) $payload['phone']),
            'email' => strtolower(trim((string) $payload['email'])),
            'applying_for_class' => $selectedClass,
            'payment_status' => $total > 0 ? 'pending' : 'success',
            'payment_reference' => null,
            'amount_due' => $feeAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'amount_total' => $total,
            'amount_paid' => $total > 0 ? null : $total,
            'paid_at' => $total > 0 ? null : now(),
            'exam_status' => 'pending',
        ]);

        if ($total <= 0) {
            $application->application_number = $this->generateApplicationNumber($school);
            $application->save();

            return response()->json([
                'message' => 'Application submitted successfully.',
                'data' => [
                    'application_number' => $application->application_number,
                    'full_name' => $application->full_name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'applying_for_class' => $application->applying_for_class,
                    'exam_available' => true,
                    'receipt_available' => true,
                ],
            ], 201);
        }

        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Paystack is not configured on the server.'], 500);
        }

        $reference = $this->generatePaymentReference($school);
        $callbackUrl = (string) config('services.paystack.callback_url');

        $initializePayload = [
            'email' => $application->email,
            'amount' => (int) round($total * 100),
            'reference' => $reference,
            'metadata' => [
                'type' => 'entrance_exam',
                'school_id' => $school->id,
                'application_id' => $application->id,
            ],
        ];

        if ($callbackUrl !== '') {
            $initializePayload['callback_url'] = $callbackUrl;
        }

        if (! empty($school->paystack_subaccount_code)) {
            $initializePayload['subaccount'] = $school->paystack_subaccount_code;
        }

        $response = Http::withToken($secret)
            ->timeout(20)
            ->post(rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/initialize', $initializePayload);

        $json = $response->json();
        if (! $response->successful() || ! ($json['status'] ?? false)) {
            return response()->json([
                'message' => $json['message'] ?? 'Failed to initialize payment.',
            ], 502);
        }

        $authUrl = (string) data_get($json, 'data.authorization_url', '');
        $accessCode = (string) data_get($json, 'data.access_code', '');

        $application->payment_reference = $reference;
        $application->paystack_access_code = $accessCode !== '' ? $accessCode : null;
        $application->paystack_authorization_url = $authUrl !== '' ? $authUrl : null;
        $application->save();

        return response()->json([
            'message' => 'Payment initialized.',
            'data' => [
                'reference' => $reference,
                'authorization_url' => $authUrl,
                'amount_total' => $total,
                'application_id' => $application->id,
            ],
        ], 201);
    }

    public function verifyEntranceExamPayment(Request $request)
    {
        $school = $this->tenantSchoolOrFail($request);
        $payload = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
        ]);

        $application = SchoolAdmissionApplication::query()
            ->where('school_id', $school->id)
            ->where('payment_reference', trim((string) $payload['reference']))
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Payment reference not found.'], 404);
        }

        if ((string) $application->payment_status === 'success') {
            return response()->json([
                'data' => $this->applicationPaymentPayload($application),
            ]);
        }

        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Paystack is not configured on the server.'], 500);
        }

        $verifyRes = Http::withToken($secret)
            ->timeout(20)
            ->get(rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/verify/' . urlencode((string) $application->payment_reference));

        $json = $verifyRes->json();
        if (! $verifyRes->successful() || ! ($json['status'] ?? false)) {
            $application->update([
                'payment_status' => 'failed',
                'paystack_status' => (string) data_get($json, 'data.status', 'failed'),
                'paystack_gateway_response' => (string) ($json['message'] ?? 'verification_failed'),
            ]);

            return response()->json([
                'message' => $json['message'] ?? 'Failed to verify payment.',
            ], 502);
        }

        $paystackStatus = (string) data_get($json, 'data.status', '');
        $gatewayResponse = (string) data_get($json, 'data.gateway_response', '');
        $channel = (string) data_get($json, 'data.channel', '');
        $amountKobo = (int) data_get($json, 'data.amount', 0);
        $amountPaid = round($amountKobo / 100, 2);

        if ($paystackStatus !== 'success') {
            $application->update([
                'payment_status' => 'failed',
                'paystack_status' => $paystackStatus,
                'paystack_gateway_response' => $gatewayResponse,
                'paystack_channel' => $channel ?: null,
            ]);

            return response()->json([
                'message' => 'Payment not successful yet.',
            ], 422);
        }

        if (abs($amountPaid - (float) ($application->amount_total ?? 0)) > 0.01) {
            return response()->json([
                'message' => 'Amount mismatch detected during verification.',
            ], 422);
        }

        $paidAt = data_get($json, 'data.paid_at')
            ? Carbon::parse((string) data_get($json, 'data.paid_at'))
            : now();

        if (! $application->application_number) {
            $application->application_number = $this->generateApplicationNumber($school);
        }

        $application->payment_status = 'success';
        $application->paystack_status = $paystackStatus;
        $application->paystack_gateway_response = $gatewayResponse;
        $application->paystack_channel = $channel ?: null;
        $application->amount_paid = $amountPaid;
        $application->paid_at = $paidAt;
        $application->save();

        return response()->json([
            'data' => $this->applicationPaymentPayload($application),
        ]);
    }

    public function entranceExamReceipt(Request $request)
    {
        $school = $this->tenantSchoolOrFail($request);
        $payload = $request->validate([
            'application_number' => ['required', 'string', 'max:80'],
        ]);

        $application = SchoolAdmissionApplication::query()
            ->where('school_id', $school->id)
            ->where('application_number', trim((string) $payload['application_number']))
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Receipt not found.'], 404);
        }

        if ((string) $application->payment_status !== 'success') {
            return response()->json(['message' => 'Receipt is available only for successful payments.'], 422);
        }

        $logoDataUri = $this->logoDataUri($school->logo_path);

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.entrance_exam_receipt', [
                'school' => $school,
                'logoDataUri' => $logoDataUri,
                'application' => $application,
                'generatedAt' => now(),
            ])->render();

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (! is_dir($dompdfTempDir)) {
                @mkdir($dompdfTempDir, 0775, true);
            }
            $options->set('tempDir', $dompdfTempDir);
            $options->set('fontDir', $dompdfTempDir);
            $options->set('fontCache', $dompdfTempDir);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $safeReference = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $application->payment_reference);
            $filename = 'entrance_exam_receipt_' . ($safeReference ?: $application->id) . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to generate receipt PDF.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        if ((string) $application->payment_status !== 'success') {
            return response()->json(['message' => 'Complete the entrance exam payment first.'], 403);
        }

        $classExam = SchoolPublicWebsiteData::findClassExam($entranceExamConfig, $application->applying_for_class);
        if (! $classExam || ! $classExam['enabled'] || count($classExam['questions']) === 0) {
            return response()->json(['message' => 'No active entrance exam is configured for this class yet.'], 422);
        }

        if ($application->exam_status === 'completed') {
            return response()->json([
                'already_submitted' => true,
                'data' => $this->applicationSummaryPayload($application),
            ]);
        }

        return response()->json([
            'already_submitted' => false,
            'data' => [
                'application' => $this->applicationSummaryPayload($application),
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

        if ((string) $application->payment_status !== 'success') {
            return response()->json(['message' => 'Complete the entrance exam payment first.'], 403);
        }

        if ($application->exam_status === 'completed') {
            return response()->json(['message' => 'Entrance exam has already been submitted.'], 422);
        }

        $payload = $request->validate([
            'application_number' => ['required', 'string', 'max:60'],
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
            'data' => $this->applicationSummaryPayload($application),
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
            'data' => $this->applicationReviewPayload($application),
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
        ]);

        $application = SchoolAdmissionApplication::query()
            ->where('school_id', $school->id)
            ->where('application_number', trim((string) $payload['application_number']))
            ->first();

        if (! $application) {
            abort(404, 'Application not found.');
        }

        return $application;
    }

    private function applicationSummaryPayload(SchoolAdmissionApplication $application): array
    {
        return [
            'application_number' => $application->application_number,
            'full_name' => $application->full_name,
            'applying_for_class' => $application->applying_for_class,
            'exam_status' => $application->exam_status,
            'submitted_at' => optional($application->exam_submitted_at)->toIso8601String(),
        ];
    }

    private function applicationReviewPayload(SchoolAdmissionApplication $application): array
    {
        return [
            'application_number' => $application->application_number,
            'full_name' => $application->full_name,
            'applying_for_class' => $application->applying_for_class,
            'review_status' => $this->resolveReviewStatus($application),
        ];
    }

    private function applicationPaymentPayload(SchoolAdmissionApplication $application): array
    {
        return [
            'application_number' => $application->application_number,
            'full_name' => $application->full_name,
            'applying_for_class' => $application->applying_for_class,
            'payment_status' => $application->payment_status,
            'receipt_available' => (string) $application->payment_status === 'success',
        ];
    }

    private function resolveReviewStatus(SchoolAdmissionApplication $application): string
    {
        $status = strtolower(trim((string) ($application->admin_result_status ?? '')));
        if ($status === 'passed') {
            return 'pass';
        }
        if ($status === 'failed') {
            return 'fail';
        }
        return 'awaiting results';
    }

    private function contentFeedPayload(School $school, int $page = 1): array
    {
        $paginator = SchoolWebsiteContent::query()
            ->where('school_id', $school->id)
            ->latest()
            ->paginate(10, ['*'], 'page', max(1, $page));

        return [
            'data' => collect($paginator->items())
                ->map(fn (SchoolWebsiteContent $content) => $this->contentPayload($content))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function contentPayload(SchoolWebsiteContent $content): array
    {
        return [
            'id' => $content->id,
            'heading' => $content->heading,
            'content' => $content->content,
            'image_urls' => collect($content->image_paths ?? [])
                ->map(fn ($path) => $this->storageUrl($path))
                ->filter()
                ->values()
                ->all(),
            'created_at' => optional($content->created_at)->toIso8601String(),
            'display_date' => optional($content->created_at)->format('F j, Y'),
        ];
    }

    private function generateApplicationNumber(School $school): string
    {
        do {
            $candidate = sprintf('ADM-%d-%s', $school->id, strtoupper(Str::random(8)));
        } while (SchoolAdmissionApplication::query()->where('application_number', $candidate)->exists());

        return $candidate;
    }

    private function generatePaymentReference(School $school): string
    {
        return sprintf('ADM-PAY-%d-%s', $school->id, strtoupper(Str::random(10)));
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

    private function logoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                return null;
            }

            $contents = $disk->get($path);
            $mime = $disk->mimeType($path) ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

