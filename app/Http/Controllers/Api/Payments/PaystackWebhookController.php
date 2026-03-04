<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\SchoolFeePayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    // POST /api/payments/paystack/webhook
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

        // Always acknowledge unknown payloads to avoid noisy retries.
        if ($reference === '') {
            return response()->json(['message' => 'ok']);
        }

        DB::transaction(function () use ($reference, $event, $data, $payload) {
            $payment = SchoolFeePayment::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                Log::info('Paystack webhook: reference not found', [
                    'reference' => $reference,
                    'event' => $event,
                ]);
                return;
            }

            if ($event === 'charge.success') {
                $this->applySuccess($payment, $data, $event);
                return;
            }

            if (str_starts_with($event, 'charge.')) {
                $this->applyFailure($payment, $data, $event);
                return;
            }

            // Non-charge events are acknowledged but not processed.
            $this->attachWebhookMeta($payment, [
                'last_event' => $event,
                'last_event_id' => data_get($payload, 'data.id'),
                'last_event_received_at' => now()->toIso8601String(),
            ]);
            $payment->save();
        });

        return response()->json(['message' => 'ok']);
    }

    private function applySuccess(SchoolFeePayment $payment, array $data, string $event): void
    {
        // Keep success idempotent.
        if ($payment->status === 'success') {
            $this->attachWebhookMeta($payment, [
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
            $this->applyFailure($payment, $data, $event);
            return;
        }

        // Protect against accidental/malicious mismatch.
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
            $this->attachWebhookMeta($payment, [
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

        $paidAt = data_get($data, 'paid_at')
            ? Carbon::parse((string) data_get($data, 'paid_at'))
            : now();

        $payment->status = 'success';
        $payment->paystack_status = $status;
        $payment->paystack_gateway_response = $gatewayResponse !== '' ? $gatewayResponse : 'success';
        $payment->paystack_channel = $channel !== '' ? $channel : null;
        $payment->paid_at = $paidAt;
        $this->attachWebhookMeta($payment, [
            'last_event' => $event,
            'last_event_id' => data_get($data, 'id'),
            'last_event_received_at' => now()->toIso8601String(),
            'currency' => $currency,
        ]);
        $payment->save();
    }

    private function applyFailure(SchoolFeePayment $payment, array $data, string $event): void
    {
        // Never downgrade a successful payment.
        if ($payment->status === 'success') {
            $this->attachWebhookMeta($payment, [
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
        $this->attachWebhookMeta($payment, [
            'last_event' => $event,
            'last_event_id' => data_get($data, 'id'),
            'last_event_received_at' => now()->toIso8601String(),
        ]);
        $payment->save();
    }

    private function attachWebhookMeta(SchoolFeePayment $payment, array $values): void
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['paystack_webhook'] = array_merge(
            is_array($meta['paystack_webhook'] ?? null) ? $meta['paystack_webhook'] : [],
            $values
        );
        $payment->meta = $meta;
    }
}

