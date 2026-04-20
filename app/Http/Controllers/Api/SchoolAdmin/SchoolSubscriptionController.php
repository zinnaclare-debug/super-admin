<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolSubscriptionInvoice;
use App\Support\SchoolPublicWebsiteData;
use App\Support\SchoolSubscriptionBilling;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SchoolSubscriptionController extends Controller
{
    public function show(Request $request)
    {
        $school = School::query()->find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        return response()->json([
            'data' => SchoolSubscriptionBilling::buildSummary($school),
        ]);
    }

    public function invoice(Request $request)
    {
        $payload = $request->validate([
            'billing_cycle' => 'required|in:termly,yearly',
        ]);

        $school = School::query()->find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        $cycle = (string) $payload['billing_cycle'];
        $context = SchoolSubscriptionBilling::quoteForCycle($school, $cycle);
        $settings = $context['settings'];
        $quote = $context['quote'];
        $session = $context['session'];
        $term = $context['term'];

        if ($settings->is_free_version) {
            return response()->json(['message' => 'This school is currently on the free version.'], 422);
        }

        if (!$session || !$term) {
            return response()->json(['message' => 'A current academic session and term must be configured before billing can start.'], 422);
        }

        if (!$quote) {
            return response()->json(['message' => 'Subscription billing has not been configured for this payment option yet.'], 422);
        }

        $websiteContent = SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);
        $existingInvoice = $this->findMatchingInvoice(
            (int) $school->id,
            (int) $session->id,
            $cycle === SchoolSubscriptionBilling::CYCLE_TERMLY ? (int) $term->id : null,
            $cycle
        );

        $statusLabel = $existingInvoice
            ? $this->subscriptionInvoiceStatusLabel((string) $existingInvoice->status)
            : 'Pending Payment';

        $invoiceNumber = $existingInvoice?->reference ?: sprintf(
            'SSB-QUOTE-%d-%s-%s',
            (int) $school->id,
            strtoupper($cycle === SchoolSubscriptionBilling::CYCLE_YEARLY ? 'Y' : 'T'),
            now()->format('YmdHis')
        );

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');

            $html = view('pdf.school_subscription_invoice', [
                'school' => $school,
                'logoDataUri' => $this->imageDataUri($school->logo_path),
                'invoiceNumber' => $invoiceNumber,
                'generatedAt' => now()->toDateTimeString(),
                'statusLabel' => $statusLabel,
                'billingLabel' => $cycle === SchoolSubscriptionBilling::CYCLE_YEARLY ? 'Yearly School Billing Invoice' : 'Termly School Billing Invoice',
                'primaryColor' => $websiteContent['primary_color'] ?? '#0f172a',
                'accentColor' => $websiteContent['accent_color'] ?? '#0f766e',
                'primaryTint' => $this->blendHexColor((string) ($websiteContent['primary_color'] ?? '#0f172a'), '#ffffff', 0.84),
                'accentTint' => $this->blendHexColor((string) ($websiteContent['accent_color'] ?? '#0f766e'), '#ffffff', 0.82),
                'currency' => (string) ($quote['currency'] ?? 'NGN'),
                'quote' => $quote,
                'studentCount' => (int) ($quote['student_count'] ?? 0),
                'currentSessionName' => $session->session_name ?: ($session->academic_year ?? '-'),
                'currentTermName' => $term->name ?: '-',
                'bankName' => $settings->bank_name ?: SchoolSubscriptionBilling::DEFAULT_BANK_NAME,
                'bankAccountNumber' => $settings->bank_account_number ?: SchoolSubscriptionBilling::DEFAULT_BANK_ACCOUNT_NUMBER,
                'bankAccountName' => $settings->bank_account_name ?: SchoolSubscriptionBilling::DEFAULT_BANK_ACCOUNT_NAME,
                'bankNote' => $settings->notes,
                'schoolEmail' => $school->contact_email ?: $school->email,
                'schoolPhone' => $school->contact_phone,
                'schoolLocation' => $school->location,
                'existingInvoice' => $existingInvoice,
            ])->render();

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdfTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($dompdfTempDir)) {
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

            $filename = 'school_subscription_invoice_' . $cycle . '.pdf';

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to generate school subscription invoice PDF.', [
                'school_id' => (int) $school->id,
                'billing_cycle' => $cycle,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to generate the school subscription invoice right now.',
            ], 500);
        }
    }

    public function initializePaystack(Request $request)
    {
        $payload = $request->validate([
            'billing_cycle' => 'required|in:termly,yearly',
        ]);

        $school = School::query()->find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        $cycle = (string) $payload['billing_cycle'];
        $context = SchoolSubscriptionBilling::quoteForCycle($school, $cycle);
        $settings = $context['settings'];
        $quote = $context['quote'];
        $session = $context['session'];
        $term = $context['term'];

        if ($settings->is_free_version) {
            return response()->json(['message' => 'This school is currently on the free version.'], 422);
        }

        if (!$session || !$term) {
            return response()->json(['message' => 'A current academic session and term must be configured before billing can start.'], 422);
        }

        if (!$quote) {
            return response()->json(['message' => 'Subscription billing has not been configured for this payment option yet.'], 422);
        }

        if ($settings->manual_status_override === SchoolSubscriptionBilling::STATUS_ACTIVE) {
            return response()->json(['message' => 'Subscription is already marked active by Super Admin.'], 422);
        }

        if (SchoolSubscriptionBilling::findActiveCoverageInvoice((int) $school->id, (int) $session->id, (int) $term->id)) {
            return response()->json(['message' => 'Subscription is already active for the current period.'], 422);
        }

        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Paystack is not configured on the server.'], 500);
        }

        $reference = SchoolSubscriptionBilling::createReference(SchoolSubscriptionBilling::CHANNEL_PAYSTACK, (int) $school->id);
        $callbackUrl = $this->resolveCallbackUrl($request);

        $invoice = SchoolSubscriptionInvoice::query()->create([
            'school_id' => (int) $school->id,
            'academic_session_id' => (int) $session->id,
            'term_id' => $cycle === SchoolSubscriptionBilling::CYCLE_TERMLY ? (int) $term->id : null,
            'billing_cycle' => $cycle,
            'reference' => $reference,
            'channels' => ['card', 'bank', 'ussd', 'qr', 'bank_transfer'],
            'status' => 'pending',
            'payment_channel' => SchoolSubscriptionBilling::CHANNEL_PAYSTACK,
            'student_count_snapshot' => (int) $quote['student_count'],
            'amount_per_student_snapshot' => (float) $quote['amount_per_student_per_term'],
            'subtotal' => (float) $quote['subtotal'],
            'tax_percent_snapshot' => (float) $quote['tax_percent'],
            'tax_amount' => (float) $quote['tax_amount'],
            'total_amount' => (float) $quote['total_amount'],
            'currency' => (string) $quote['currency'],
            'submitted_by_user_id' => (int) $request->user()->id,
            'meta' => [
                'type' => 'school_subscription',
                'session_name' => $session->session_name,
                'term_name' => $term->name,
                'school_name' => $school->name,
            ],
        ]);

        $initializePayload = [
            'email' => $school->contact_email ?: $school->email ?: $request->user()->email,
            'amount' => (int) round(((float) $quote['total_amount']) * 100),
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'type' => 'school_subscription',
                'school_id' => (int) $school->id,
                'invoice_id' => (int) $invoice->id,
                'billing_cycle' => $cycle,
                'academic_session_id' => (int) $session->id,
                'term_id' => $cycle === SchoolSubscriptionBilling::CYCLE_TERMLY ? (int) $term->id : null,
            ],
        ];

        try {
            $response = $this->sendPaystackRequest(
                $secret,
                'post',
                rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/initialize',
                $initializePayload
            );
        } catch (Throwable $e) {
            $invoice->status = 'failed';
            $invoice->paystack_gateway_response = 'initialize_exception';
            $invoice->save();

            Log::error('School subscription Paystack initialize failed.', [
                'school_id' => (int) $school->id,
                'invoice_id' => (int) $invoice->id,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to initialize payment with Paystack.',
            ], 502);
        }

        $json = $response->json();
        if (!$response->successful() || !($json['status'] ?? false)) {
            $invoice->status = 'failed';
            $invoice->paystack_status = (string) data_get($json, 'data.status', 'failed');
            $invoice->paystack_gateway_response = (string) ($json['message'] ?? 'initialize_failed');
            $invoice->save();

            return response()->json([
                'message' => $json['message'] ?? 'Failed to initialize payment with Paystack.',
            ], 502);
        }

        $authUrl = (string) data_get($json, 'data.authorization_url', '');
        $accessCode = (string) data_get($json, 'data.access_code', '');
        if ($authUrl === '' || $accessCode === '') {
            $invoice->status = 'failed';
            $invoice->paystack_gateway_response = 'invalid_paystack_response';
            $invoice->save();

            return response()->json(['message' => 'Invalid response from Paystack.'], 502);
        }

        $invoice->paystack_access_code = $accessCode;
        $invoice->paystack_authorization_url = $authUrl;
        $invoice->save();

        return response()->json([
            'message' => 'Subscription payment initialized successfully.',
            'data' => [
                'authorization_url' => $authUrl,
                'invoice' => SchoolSubscriptionBilling::invoicePayload($invoice),
                'summary' => SchoolSubscriptionBilling::buildSummary($school),
            ],
        ], 201);
    }

    public function submitBankTransfer(Request $request)
    {
        $payload = $request->validate([
            'billing_cycle' => 'required|in:termly,yearly',
            'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $school = School::query()->find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        $cycle = (string) $payload['billing_cycle'];
        $context = SchoolSubscriptionBilling::quoteForCycle($school, $cycle);
        $settings = $context['settings'];
        $quote = $context['quote'];
        $session = $context['session'];
        $term = $context['term'];

        if ($settings->is_free_version) {
            return response()->json(['message' => 'This school is currently on the free version.'], 422);
        }

        if (!$session || !$term) {
            return response()->json(['message' => 'A current academic session and term must be configured before billing can start.'], 422);
        }

        if (!$quote) {
            return response()->json(['message' => 'Subscription billing has not been configured for this payment option yet.'], 422);
        }

        /** @var UploadedFile $receipt */
        $receipt = $payload['receipt'];
        $receiptPath = $receipt->store('school-subscription-receipts/' . (int) $school->id, 'public');

        $invoice = SchoolSubscriptionInvoice::query()->create([
            'school_id' => (int) $school->id,
            'academic_session_id' => (int) $session->id,
            'term_id' => $cycle === SchoolSubscriptionBilling::CYCLE_TERMLY ? (int) $term->id : null,
            'billing_cycle' => $cycle,
            'reference' => SchoolSubscriptionBilling::createReference(SchoolSubscriptionBilling::CHANNEL_BANK, (int) $school->id),
            'status' => 'pending_manual_review',
            'payment_channel' => SchoolSubscriptionBilling::CHANNEL_BANK,
            'student_count_snapshot' => (int) $quote['student_count'],
            'amount_per_student_snapshot' => (float) $quote['amount_per_student_per_term'],
            'subtotal' => (float) $quote['subtotal'],
            'tax_percent_snapshot' => (float) $quote['tax_percent'],
            'tax_amount' => (float) $quote['tax_amount'],
            'total_amount' => (float) $quote['total_amount'],
            'currency' => (string) $quote['currency'],
            'submitted_by_user_id' => (int) $request->user()->id,
            'bank_receipt_path' => $receiptPath,
            'bank_receipt_name' => $receipt->getClientOriginalName(),
            'bank_receipt_mime_type' => $receipt->getClientMimeType() ?: $receipt->getMimeType(),
            'bank_receipt_uploaded_at' => now(),
            'meta' => [
                'type' => 'school_subscription',
                'session_name' => $session->session_name,
                'term_name' => $term->name,
                'school_name' => $school->name,
                'bank_transfer' => [
                    'submitted_at' => now()->toDateTimeString(),
                ],
            ],
        ]);

        return response()->json([
            'message' => 'Bank transfer receipt submitted for Super Admin review.',
            'data' => [
                'invoice' => SchoolSubscriptionBilling::invoicePayload($invoice),
                'bank_details' => [
                    'bank_name' => $settings->bank_name ?: SchoolSubscriptionBilling::DEFAULT_BANK_NAME,
                    'bank_account_number' => $settings->bank_account_number ?: SchoolSubscriptionBilling::DEFAULT_BANK_ACCOUNT_NUMBER,
                    'bank_account_name' => $settings->bank_account_name ?: SchoolSubscriptionBilling::DEFAULT_BANK_ACCOUNT_NAME,
                ],
                'summary' => SchoolSubscriptionBilling::buildSummary($school),
            ],
        ], 201);
    }

    public function verify(Request $request)
    {
        $payload = $request->validate([
            'reference' => 'required|string',
        ]);

        $school = School::query()->find((int) $request->user()->school_id);
        if (!$school) {
            return response()->json(['message' => 'School not found.'], 404);
        }

        $invoice = SchoolSubscriptionInvoice::query()
            ->where('school_id', (int) $school->id)
            ->where('reference', $payload['reference'])
            ->first();

        if (!$invoice) {
            return response()->json(['message' => 'Subscription reference not found.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json([
                'data' => [
                    'invoice' => SchoolSubscriptionBilling::invoicePayload($invoice),
                    'summary' => SchoolSubscriptionBilling::buildSummary($school),
                ],
            ]);
        }

        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Paystack is not configured on the server.'], 500);
        }

        try {
            $response = $this->sendPaystackRequest(
                $secret,
                'get',
                rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/verify/' . urlencode($invoice->reference)
            );
        } catch (Throwable $e) {
            $invoice->status = 'failed';
            $invoice->paystack_gateway_response = 'verification_exception';
            $invoice->save();

            Log::error('School subscription Paystack verification failed.', [
                'school_id' => (int) $school->id,
                'invoice_id' => (int) $invoice->id,
                'reference' => $invoice->reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to verify payment.',
            ], 502);
        }

        $json = $response->json();
        if (!$response->successful() || !($json['status'] ?? false)) {
            $invoice->status = 'failed';
            $invoice->paystack_status = (string) data_get($json, 'data.status', 'failed');
            $invoice->paystack_gateway_response = (string) ($json['message'] ?? 'verification_failed');
            $invoice->save();

            return response()->json([
                'message' => $json['message'] ?? 'Failed to verify payment.',
            ], 502);
        }

        $paystackStatus = (string) data_get($json, 'data.status', '');
        $gatewayResponse = (string) data_get($json, 'data.gateway_response', '');
        $channel = (string) data_get($json, 'data.channel', '');
        $amountPaid = round(((int) data_get($json, 'data.amount', 0)) / 100, 2);

        if ($paystackStatus !== 'success') {
            $invoice->status = 'failed';
            $invoice->paystack_status = $paystackStatus;
            $invoice->paystack_gateway_response = $gatewayResponse !== '' ? $gatewayResponse : 'verification_failed';
            $invoice->paystack_channel = $channel !== '' ? $channel : null;
            $invoice->save();

            return response()->json([
                'message' => 'Payment has not been confirmed yet.',
            ], 422);
        }

        if (abs($amountPaid - (float) $invoice->total_amount) > 0.01) {
            $invoice->status = 'failed';
            $invoice->paystack_status = $paystackStatus;
            $invoice->paystack_gateway_response = 'amount_mismatch';
            $invoice->paystack_channel = $channel !== '' ? $channel : null;
            $invoice->save();

            return response()->json([
                'message' => 'Amount mismatch detected during verification.',
            ], 422);
        }

        $invoice->status = 'paid';
        $invoice->payment_channel = SchoolSubscriptionBilling::CHANNEL_PAYSTACK;
        $invoice->paystack_status = $paystackStatus;
        $invoice->paystack_gateway_response = $gatewayResponse !== '' ? $gatewayResponse : 'success';
        $invoice->paystack_channel = $channel !== '' ? $channel : null;
        $invoice->paid_at = data_get($json, 'data.paid_at')
            ? Carbon::parse((string) data_get($json, 'data.paid_at'))
            : now();
        $invoice->save();

        $settings = SchoolSubscriptionBilling::getSettings($school);
        SchoolSubscriptionBilling::clearPendingOverride($settings);

        return response()->json([
            'message' => 'Subscription payment verified successfully.',
            'data' => [
                'invoice' => SchoolSubscriptionBilling::invoicePayload($invoice),
                'summary' => SchoolSubscriptionBilling::buildSummary($school),
            ],
        ]);
    }

    private function resolveCallbackUrl(Request $request): string
    {
        $origin = trim((string) $request->headers->get('origin', ''));
        if ($origin !== '') {
            return rtrim($origin, '/') . '/school/dashboard';
        }

        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer !== '') {
            $parts = parse_url($referer);
            $scheme = (string) ($parts['scheme'] ?? '');
            $host = (string) ($parts['host'] ?? '');
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';

            if ($scheme !== '' && $host !== '') {
                return $scheme . '://' . $host . $port . '/school/dashboard';
            }
        }

        $host = trim((string) $request->getSchemeAndHttpHost());
        if ($host !== '') {
            return rtrim($host, '/') . '/school/dashboard';
        }

        return rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/school/dashboard';
    }

    private function sendPaystackRequest(string $secret, string $method, string $url, array $payload = []): Response
    {
        $attempts = 3;
        $sleepMs = 1500;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = Http::withToken($secret)
                    ->connectTimeout(15)
                    ->timeout(45)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        ],
                    ]);

                return $method === 'get'
                    ? $request->get($url)
                    : $request->post($url, $payload);
            } catch (Throwable $e) {
                $lastException = $e;

                Log::warning('Paystack request attempt failed.', [
                    'url' => $url,
                    'method' => $method,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $attempts) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Paystack request failed.');
    }

    private function findMatchingInvoice(int $schoolId, int $sessionId, ?int $termId, string $cycle): ?SchoolSubscriptionInvoice
    {
        $query = SchoolSubscriptionInvoice::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $sessionId)
            ->where('billing_cycle', $cycle);

        if ($cycle === SchoolSubscriptionBilling::CYCLE_TERMLY) {
            $query->where('term_id', $termId);
        } else {
            $query->whereNull('term_id');
        }

        return $query->latest('id')->first();
    }

    private function subscriptionInvoiceStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Active',
            'pending_manual_review' => 'Pending Manual Review',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => 'Pending Payment',
        };
    }

    private function imageDataUri(?string $storagePath): ?string
    {
        if (!$storagePath || !Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($storagePath);
        $mime = @mime_content_type($fullPath) ?: 'image/png';
        $data = @file_get_contents($fullPath);
        if ($data === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private function blendHexColor(string $baseHex, string $targetHex, float $targetWeight): string
    {
        $base = $this->hexToRgb($baseHex);
        $target = $this->hexToRgb($targetHex);
        $weight = max(0, min(1, $targetWeight));

        $mixed = [
            'r' => (int) round(($base['r'] * (1 - $weight)) + ($target['r'] * $weight)),
            'g' => (int) round(($base['g'] * (1 - $weight)) + ($target['g'] * $weight)),
            'b' => (int) round(($base['b'] * (1 - $weight)) + ($target['b'] * $weight)),
        ];

        return sprintf('#%02x%02x%02x', $mixed['r'], $mixed['g'], $mixed['b']);
    }

    private function hexToRgb(string $hex): array
    {
        $normalized = ltrim(trim($hex), '#');

        if (strlen($normalized) === 3) {
            $normalized = preg_replace('/(.)/', '$1$1', $normalized) ?: '000000';
        }

        if (!preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            $normalized = '000000';
        }

        return [
            'r' => hexdec(substr($normalized, 0, 2)),
            'g' => hexdec(substr($normalized, 2, 2)),
            'b' => hexdec(substr($normalized, 4, 2)),
        ];
    }
}
