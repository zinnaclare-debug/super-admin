<?php

namespace App\Http\Controllers\Api\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolSubscriptionInvoice;
use App\Support\SchoolSubscriptionBilling;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
}
