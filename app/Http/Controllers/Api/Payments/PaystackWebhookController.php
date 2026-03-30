<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolFeePayment;
use App\Models\SchoolSubscriptionInvoice;
use App\Support\SchoolSubscriptionBilling;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return response()->json(['message' => 'Paystack key not configured'], 500);
        }

        $rawBody = (string) $request->getContent();
        $signature = (string) $request->header('x-paystack-signature', '');
        $expected = hash_hmac('sha512', $rawBody, $secret);

        if ($signature === '' || !hash_equals($expected, $signature)) {
            Log::warning('Paystack webhook rejected: invalid signature', [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->header('user-agent', ''),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $event = strtolower(trim((string) ($payload['event'] ?? '')));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = trim((string) ($data['reference'] ?? ''));

        if ($reference === '') {
            return response()->json(['message' => 'ok']);
        }

        DB::transaction(function () use ($reference, $event, $data, $payload) {
            $feePayment = SchoolFeePayment::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($feePayment) {
                $this->processSchoolFeePayment($feePayment, $event, $data, $payload);
                return;
            }

            $subscriptionInvoice = SchoolSubscriptionInvoice::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($subscriptionInvoice) {
                $this->processSubscriptionInvoice($subscriptionInvoice, $event, $data, $payload);
                return;
            }

            Log::info('Paystack webhook: reference not found', [
                'reference' => $reference,
                'event' => $event,
            ]);
        });

        return response()->json(['message' => 'ok']);
    }

    private function processSchoolFeePayment(SchoolFeePayment $payment, string $event, array $data, array $payload): void
    {
        if ($event === 'charge.success') {
            $this->applyFeeSuccess($payment, $data, $event);
            return;
        }

        if (str_starts_with($event, 'charge.')) {
            $this->applyFeeFailure($payment, $data, $event);
            return;
        }

        $this->attachMeta($payment, [
            'last_event' => $event,
            'last_event_id' => data_get($payload, 'data.id'),
            'last_event_received_at' => now()->toIso8601String(),
        ]);
        $payment->save();
    }

    private function processSubscriptionInvoice(SchoolSubscriptionInvoice $invoice, string $event, array $data, array $payload): void
    {
        if ($event === 'charge.success') {
            $this->applySubscriptionSuccess($invoice, $data, $event);
            return;
        }

        if (str_starts_with($event, 'charge.')) {
            $this->applySubscriptionFailure($invoice, $data, $event);
            return;
        }

        $this->attachMeta($invoice, [
            'last_event' => $event,
            'last_event_id' => data_get($payload, 'data.id'),
            'last_event_received_at' => now()->toIso8601String(),
        ]);
        $invoice->save();
    }

    private function applyFeeSuccess(SchoolFeePayment $payment, array $data, string $event): void
    {
        if ($payment->status === 'success') {
            $this->attachMeta($payment, [
                'last_event' => $event,
                'last_event_id' => data_get($data, 'id'),
                'last_event_received_at' => now()->toIso8601String(),
                'idempotent' => true,
            ]);
            $payment->save();
            return;
        }

        $status = strtolower(trim((string) data_get($data, 'status', '')));
        $gatewayResponse = (string) data_get($data, 'gateway_response', '');
        $channel = (string) data_get($data, 'channel', '');
        $currency = strtoupper(trim((string) data_get($data, 'currency', 'NGN')));
        $amountPaid = round(((int) data_get($data, 'amount', 0)) / 100, 2);

        if ($status !== 'success') {
            $this->applyFeeFailure($payment, $data, $event);
            return;
        }

        if (abs($amountPaid - (float) $payment->amount_paid) > 0.01) {
            Log::warning('Paystack webhook amount mismatch', [
                'reference' => $payment->reference,
                'expected' => (float) $payment->amount_paid,
                'received' => $amountPaid,
            ]);

            $payment->status = 'failed';
            $payment->paystack_status = $status;
            $payment->paystack_gateway_response = 'amount_mismatch';
            $payment->paystack_channel = $channel !== '' ? $channel : null;
            $this->attachMeta($payment, [
                'last_event' => $event,
                'last_event_id' => data_get($data, 'id'),
                'last_event_received_at' => now()->toIso8601String(),
                'amount_mismatch' => [
                    'expected' => (float) $payment->amount_paid,
                    'received' => $amountPaid,
                ],
            ]);
            $payment->save();
            return;
        }

        $payment->status = 'success';
        $payment->paystack_status = $status;
        $payment->paystack_gateway_response = $gatewayResponse !== '' ? $gatewayResponse : 'success';
        $payment->paystack_channel = $channel !== '' ? $channel : null;
        $payment->paid_at = data_get($data, 'paid_at')
            ? Carbon::parse((string) data_get($data, 'paid_at'))
            : now();
        $this->attachMeta($payment, [
            'last_event' => $event,
            'last_event_id' => data_get($data, 'id'),
            'last_event_received_at' => now()->toIso8601String(),
            'currency' => $currency,
        ]);
        $payment->save();
    }

    private function applyFeeFailure(SchoolFeePayment $payment, array $data, string $event): void
    {
        if ($payment->status === 'success') {
            $this->attachMeta($payment, [
                'last_event' => $event,
                'last_event_id' => data_get($data, 'id'),
                'last_event_received_at' => now()->toIso8601String(),
                'ignored' => 'already_success',
            ]);
            $payment->save();
            return;
        }

        $status = strtolower(trim((string) data_get($data, 'status', 'failed')));
        $gatewayResponse = (string) data_get($data, 'gateway_response', '');
        $channel = (string) data_get($data, 'channel', '');

        $payment->status = 'failed';
        $payment->paystack_status = $status !== '' ? $status : 'failed';
        $payment->paystack_gateway_response = $gatewayResponse !== '' ? $gatewayResponse : $event;
        $payment->paystack_channel = $channel !== '' ? $channel : null;
        $this->attachMeta($payment, [
            'last_event' => $event,
            'last_event_id' => data_get($data, 'id'),
            'last_event_received_at' => now()->toIso8601String(),
        ]);
        $payment->save();
    }

    private function applySubscriptionSuccess(SchoolSubscriptionInvoice $invoice, array $data, string $event): void
    {
        if ($invoice->status === 'paid') {
            $this->attachMeta($invoice, [
                'last_event' => $event,
                'last_event_id' => data_get($data, 'id'),
                'last_event_received_at' => now()->toIso8601String(),
                'idempotent' => true,
            ]);
            $invoice->save();
            return;
        }

        $status = strtolower(trim((string) data_get($data, 'status', '')));
        $gatewayResponse = (string) data_get($data, 'gateway_response', '');
        $channel = (string) data_get($data, 'channel', '');
        $amountPaid = round(((int) data_get($data, 'amount', 0)) / 100, 2);

        if ($status !== 'success') {
            $this->applySubscriptionFailure($invoice, $data, $event);
            return;
        }

        if (abs($amountPaid - (float) $invoice->total_amount) > 0.01) {
            Log::warning('Subscription webhook amount mismatch', [
                'reference' => $invoice->reference,
                'expected' => (float) $invoice->total_amount,
                'received' => $amountPaid,
            ]);

            $invoice->status = 'failed';
            $invoice->paystack_status = $status;
            $invoice->paystack_gateway_response = 'amount_mismatch';
            $invoice->paystack_channel = $channel !== '' ? $channel : null;
            $this->attachMeta($invoice, [
                'last_event' => $event,
                'last_event_id' => data_get($data, 'id'),
                'last_event_received_at' => now()->toIso8601String(),
                'amount_mismatch' => [
                    'expected' => (float) $invoice->total_amount,
                    'received' => $amountPaid,
                ],
            ]);
            $invoice->save();
            return;
        }

        $invoice->status = 'paid';
        $invoice->payment_channel = SchoolSubscriptionBilling::CHANNEL_PAYSTACK;
        $invoice->paystack_status = $status;
        $invoice->paystack_gateway_response = $gatewayResponse !== '' ? $gatewayResponse : 'success';
        $invoice->paystack_channel = $channel !== '' ? $channel : null;
        $invoice->paid_at = data_get($data, 'paid_at')
            ? Carbon::parse((string) data_get($data, 'paid_at'))
            : now();
        $this->attachMeta($invoice, [
            'last_event' => $event,
            'last_event_id' => data_get($data, 'id'),
            'last_event_received_at' => now()->toIso8601String(),
        ]);
        $invoice->save();

        $school = School::query()->find((int) $invoice->school_id);
        if ($school) {
            $settings = SchoolSubscriptionBilling::getSettings($school);
            SchoolSubscriptionBilling::clearPendingOverride($settings);
        }
    }

    private function applySubscriptionFailure(SchoolSubscriptionInvoice $invoice, array $data, string $event): void
    {
        if ($invoice->status === 'paid') {
            $this->attachMeta($invoice, [
                'last_event' => $event,
                'last_event_id' => data_get($data, 'id'),
                'last_event_received_at' => now()->toIso8601String(),
                'ignored' => 'already_paid',
            ]);
            $invoice->save();
            return;
        }

        $status = strtolower(trim((string) data_get($data, 'status', 'failed')));
        $gatewayResponse = (string) data_get($data, 'gateway_response', '');
        $channel = (string) data_get($data, 'channel', '');

        $invoice->status = 'failed';
        $invoice->paystack_status = $status !== '' ? $status : 'failed';
        $invoice->paystack_gateway_response = $gatewayResponse !== '' ? $gatewayResponse : $event;
        $invoice->paystack_channel = $channel !== '' ? $channel : null;
        $this->attachMeta($invoice, [
            'last_event' => $event,
            'last_event_id' => data_get($data, 'id'),
            'last_event_received_at' => now()->toIso8601String(),
        ]);
        $invoice->save();
    }

    private function attachMeta(object $record, array $values): void
    {
        $meta = is_array($record->meta ?? null) ? $record->meta : [];
        $meta['paystack_webhook'] = array_merge(
            is_array($meta['paystack_webhook'] ?? null) ? $meta['paystack_webhook'] : [],
            $values
        );
        $record->meta = $meta;
    }
}
