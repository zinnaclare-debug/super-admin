<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolSubscriptionInvoice;
use App\Models\SchoolSubscriptionSetting;
use App\Support\SchoolSubscriptionBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SchoolSubscriptionController extends Controller
{
    public function show(School $school)
    {
        return response()->json([
            'data' => SchoolSubscriptionBilling::buildSummary($school),
        ]);
    }

    public function upsertSettings(Request $request, School $school)
    {
        $payload = $request->validate([
            'amount_per_student_per_term' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'allow_termly' => 'required|boolean',
            'allow_yearly' => 'required|boolean',
            'is_free_version' => 'required|boolean',
            'manual_status_override' => 'nullable|in:free,pending,active',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (!(bool) $payload['is_free_version'] && !(bool) $payload['allow_termly'] && !(bool) $payload['allow_yearly']) {
            return response()->json(['message' => 'Enable at least one payment cycle when the school is not on free version.'], 422);
        }

        if (!(bool) $payload['is_free_version'] && (float) ($payload['amount_per_student_per_term'] ?? 0) <= 0) {
            return response()->json(['message' => 'Enter a per-student term amount before enabling paid subscription billing.'], 422);
        }

        SchoolSubscriptionSetting::query()->updateOrCreate(
            ['school_id' => (int) $school->id],
            [
                'amount_per_student_per_term' => array_key_exists('amount_per_student_per_term', $payload)
                    ? round((float) ($payload['amount_per_student_per_term'] ?? 0), 2)
                    : null,
                'currency' => strtoupper(trim((string) ($payload['currency'] ?? 'NGN'))) ?: 'NGN',
                'tax_percent' => round((float) ($payload['tax_percent'] ?? SchoolSubscriptionBilling::DEFAULT_TAX_PERCENT), 2),
                'allow_termly' => (bool) $payload['allow_termly'],
                'allow_yearly' => (bool) $payload['allow_yearly'],
                'is_free_version' => (bool) $payload['is_free_version'],
                'manual_status_override' => trim((string) ($payload['manual_status_override'] ?? '')) ?: null,
                'bank_name' => trim((string) ($payload['bank_name'] ?? '')) ?: SchoolSubscriptionBilling::DEFAULT_BANK_NAME,
                'bank_account_number' => trim((string) ($payload['bank_account_number'] ?? '')) ?: SchoolSubscriptionBilling::DEFAULT_BANK_ACCOUNT_NUMBER,
                'bank_account_name' => trim((string) ($payload['bank_account_name'] ?? '')) ?: SchoolSubscriptionBilling::DEFAULT_BANK_ACCOUNT_NAME,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'configured_by_user_id' => (int) $request->user()->id,
            ]
        );

        return response()->json([
            'message' => 'School subscription settings saved successfully.',
            'data' => SchoolSubscriptionBilling::buildSummary($school),
        ]);
    }

    public function updateInvoiceStatus(Request $request, School $school, SchoolSubscriptionInvoice $invoice)
    {
        if ((int) $invoice->school_id !== (int) $school->id) {
            return response()->json(['message' => 'Invoice not found for this school.'], 404);
        }

        $payload = $request->validate([
            'status_target' => 'required|in:active,pending',
        ]);

        $meta = is_array($invoice->meta) ? $invoice->meta : [];
        $meta['super_admin_action'] = [
            'status_target' => $payload['status_target'],
            'acted_by_user_id' => (int) $request->user()->id,
            'acted_at' => now()->toDateTimeString(),
        ];
        $invoice->meta = $meta;

        if ($payload['status_target'] === 'active') {
            $invoice->status = 'paid';
            $invoice->payment_channel = $invoice->payment_channel ?: SchoolSubscriptionBilling::CHANNEL_BANK;
            $invoice->approved_by_user_id = (int) $request->user()->id;
            $invoice->paid_at = $invoice->paid_at ?: now();
        } else {
            $invoice->status = 'pending_manual_review';
            $invoice->payment_channel = $invoice->payment_channel ?: SchoolSubscriptionBilling::CHANNEL_BANK;
            $invoice->approved_by_user_id = null;
            $invoice->paid_at = null;
        }

        $invoice->save();

        $settings = SchoolSubscriptionBilling::getSettings($school);
        if ($payload['status_target'] === 'active') {
            SchoolSubscriptionBilling::clearPendingOverride($settings);
        }

        return response()->json([
            'message' => $payload['status_target'] === 'active'
                ? 'Invoice marked active successfully.'
                : 'Invoice moved back to pending review successfully.',
            'data' => [
                'invoice' => SchoolSubscriptionBilling::invoicePayload($invoice),
                'summary' => SchoolSubscriptionBilling::buildSummary($school),
            ],
        ]);
    }

    public function destroyInvoice(Request $request, School $school, SchoolSubscriptionInvoice $invoice)
    {
        if ((int) $invoice->school_id !== (int) $school->id) {
            return response()->json(['message' => 'Invoice not found for this school.'], 404);
        }

        $this->validateDeleteCode($request);

        $reference = $invoice->reference;
        if ($invoice->bank_receipt_path) {
            Storage::disk('public')->delete($invoice->bank_receipt_path);
        }
        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully.',
            'data' => [
                'deleted_reference' => $reference,
                'summary' => SchoolSubscriptionBilling::buildSummary($school),
            ],
        ]);
    }

    private function validateDeleteCode(Request $request): void
    {
        $validated = $request->validate([
            'delete_code' => ['required', 'digits:4'],
        ]);

        $expectedDeleteCode = (string) config('app.super_admin_delete_confirmation_code', '4722');

        if (!hash_equals($expectedDeleteCode, (string) $validated['delete_code'])) {
            throw ValidationException::withMessages([
                'delete_code' => ['Invalid delete confirmation code.'],
            ]);
        }
    }
}
