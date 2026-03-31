<?php

namespace App\Support;

use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolSubscriptionInvoice;
use App\Models\SchoolSubscriptionSetting;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SchoolSubscriptionBilling
{
    public const STATUS_FREE = 'free';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';

    public const CYCLE_TERMLY = 'termly';
    public const CYCLE_YEARLY = 'yearly';

    public const CHANNEL_PAYSTACK = 'paystack';
    public const CHANNEL_BANK = 'bank';
    public const CHANNEL_MANUAL = 'manual';

    public const DEFAULT_TAX_PERCENT = 1.60;
    public const DEFAULT_BANK_NAME = 'ECOBANK';
    public const DEFAULT_BANK_ACCOUNT_NUMBER = '3680106500';
    public const DEFAULT_BANK_ACCOUNT_NAME = 'LYTEBRIDGE PROFESSIONAL SERVICE LTD';

    public static function defaultSettingAttributes(int $schoolId): array
    {
        return [
            'school_id' => $schoolId,
            'amount_per_student_per_term' => null,
            'currency' => 'NGN',
            'tax_percent' => self::DEFAULT_TAX_PERCENT,
            'allow_termly' => true,
            'allow_yearly' => true,
            'is_free_version' => true,
            'manual_status_override' => null,
            'bank_name' => self::DEFAULT_BANK_NAME,
            'bank_account_number' => self::DEFAULT_BANK_ACCOUNT_NUMBER,
            'bank_account_name' => self::DEFAULT_BANK_ACCOUNT_NAME,
            'notes' => null,
            'configured_by_user_id' => null,
        ];
    }

    public static function getSettings(School $school): SchoolSubscriptionSetting
    {
        $settings = SchoolSubscriptionSetting::query()
            ->where('school_id', (int) $school->id)
            ->first();

        if ($settings) {
            return $settings;
        }

        return new SchoolSubscriptionSetting(self::defaultSettingAttributes((int) $school->id));
    }

    public static function buildSummary(School $school): array
    {
        $settings = self::getSettings($school);
        [$session, $term] = self::resolveCurrentSessionAndTerm((int) $school->id);
        $studentCount = self::countBillableStudents((int) $school->id);
        $termlyQuote = self::buildQuote($settings, $studentCount, self::CYCLE_TERMLY);
        $yearlyQuote = self::buildQuote($settings, $studentCount, self::CYCLE_YEARLY);
        $activeInvoice = self::findActiveCoverageInvoice((int) $school->id, $session?->id, $term?->id);
        $pendingInvoice = self::findLatestPendingInvoice((int) $school->id, $session?->id, $term?->id);

        $status = self::deriveStatus($settings, $activeInvoice);

        return [
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'status_tone' => self::statusTone($status),
            'status_reason' => self::statusReason($status, $settings, $activeInvoice, $pendingInvoice),
            'current_session' => $session ? [
                'id' => (int) $session->id,
                'session_name' => $session->session_name,
                'academic_year' => $session->academic_year,
            ] : null,
            'current_term' => $term ? [
                'id' => (int) $term->id,
                'name' => $term->name,
            ] : null,
            'student_count' => $studentCount,
            'settings' => [
                'amount_per_student_per_term' => self::money($settings->amount_per_student_per_term),
                'currency' => $settings->currency ?: 'NGN',
                'tax_percent' => self::decimal($settings->tax_percent),
                'allow_termly' => (bool) $settings->allow_termly,
                'allow_yearly' => (bool) $settings->allow_yearly,
                'is_free_version' => (bool) $settings->is_free_version,
                'manual_status_override' => $settings->manual_status_override ?: null,
                'bank_name' => $settings->bank_name ?: self::DEFAULT_BANK_NAME,
                'bank_account_number' => $settings->bank_account_number ?: self::DEFAULT_BANK_ACCOUNT_NUMBER,
                'bank_account_name' => $settings->bank_account_name ?: self::DEFAULT_BANK_ACCOUNT_NAME,
                'notes' => $settings->notes,
            ],
            'quotes' => [
                self::CYCLE_TERMLY => $termlyQuote,
                self::CYCLE_YEARLY => $yearlyQuote,
            ],
            'active_invoice' => $activeInvoice ? self::invoicePayload($activeInvoice) : null,
            'latest_pending_invoice' => $pendingInvoice ? self::invoicePayload($pendingInvoice) : null,
            'recent_invoices' => self::recentInvoicesPayload((int) $school->id),
        ];
    }

    public static function buildQuote(SchoolSubscriptionSetting $settings, int $studentCount, string $cycle): ?array
    {
        $cycle = strtolower(trim($cycle));
        if (!in_array($cycle, [self::CYCLE_TERMLY, self::CYCLE_YEARLY], true)) {
            return null;
        }

        if ($cycle === self::CYCLE_TERMLY && !$settings->allow_termly) {
            return null;
        }
        if ($cycle === self::CYCLE_YEARLY && !$settings->allow_yearly) {
            return null;
        }

        $rate = self::money($settings->amount_per_student_per_term);
        if ($rate <= 0 || $studentCount <= 0) {
            return null;
        }

        $multiplier = $cycle === self::CYCLE_YEARLY ? 3 : 1;
        $subtotal = round($rate * $studentCount * $multiplier, 2);
        $taxPercent = self::decimal($settings->tax_percent);
        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'billing_cycle' => $cycle,
            'label' => $cycle === self::CYCLE_YEARLY ? 'Pay Yearly' : 'Pay Termly',
            'terms_covered' => $multiplier,
            'student_count' => $studentCount,
            'amount_per_student_per_term' => $rate,
            'subtotal' => $subtotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'currency' => $settings->currency ?: 'NGN',
        ];
    }

    public static function quoteForCycle(School $school, string $cycle): array
    {
        $settings = self::getSettings($school);
        [$session, $term] = self::resolveCurrentSessionAndTerm((int) $school->id);
        $studentCount = self::countBillableStudents((int) $school->id);
        $quote = self::buildQuote($settings, $studentCount, $cycle);

        return [
            'settings' => $settings,
            'session' => $session,
            'term' => $term,
            'student_count' => $studentCount,
            'quote' => $quote,
        ];
    }

    public static function createReference(string $channel, int $schoolId): string
    {
        $prefix = match (strtolower(trim($channel))) {
            self::CHANNEL_BANK => 'SSB-BANK',
            self::CHANNEL_MANUAL => 'SSB-MAN',
            default => 'SSB',
        };

        do {
            $reference = $prefix . '-' . $schoolId . '-' . Str::upper(Str::random(12));
        } while (SchoolSubscriptionInvoice::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public static function clearPendingOverride(SchoolSubscriptionSetting $settings): void
    {
        if (!$settings->exists) {
            return;
        }

        if (in_array($settings->manual_status_override, [self::STATUS_PENDING, self::STATUS_ACTIVE], true)) {
            $settings->manual_status_override = null;
            $settings->save();
        }
    }

    public static function resolveCurrentSessionAndTerm(int $schoolId): array
    {
        $session = AcademicSession::query()
            ->where('school_id', $schoolId)
            ->where('status', 'current')
            ->latest('id')
            ->first();

        if (!$session) {
            return [null, null];
        }

        $termQuery = Term::query()
            ->where('school_id', $schoolId)
            ->where('academic_session_id', (int) $session->id);

        if (Schema::hasColumn('terms', 'is_current')) {
            $termQuery->where('is_current', true);
        }

        $term = $termQuery->latest('id')->first();

        return [$session, $term];
    }

    public static function countBillableStudents(int $schoolId): int
    {
        $query = User::query()
            ->where('school_id', $schoolId)
            ->where('role', 'student');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        return (int) $query->count();
    }

    public static function findActiveCoverageInvoice(int $schoolId, ?int $sessionId, ?int $termId): ?SchoolSubscriptionInvoice
    {
        if (!$sessionId) {
            return null;
        }

        return SchoolSubscriptionInvoice::query()
            ->where('school_id', $schoolId)
            ->where('status', 'paid')
            ->where('academic_session_id', $sessionId)
            ->where(function ($query) use ($termId) {
                $query->where('billing_cycle', self::CYCLE_YEARLY);
                if ($termId) {
                    $query->orWhere(function ($inner) use ($termId) {
                        $inner->where('billing_cycle', self::CYCLE_TERMLY)
                            ->where('term_id', $termId);
                    });
                }
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function findLatestPendingInvoice(int $schoolId, ?int $sessionId, ?int $termId): ?SchoolSubscriptionInvoice
    {
        $query = SchoolSubscriptionInvoice::query()
            ->where('school_id', $schoolId)
            ->whereIn('status', ['pending', 'pending_manual_review']);

        if ($sessionId) {
            $query->where('academic_session_id', $sessionId);
        }

        if ($termId) {
            $query->where(function ($inner) use ($termId) {
                $inner->where('billing_cycle', self::CYCLE_YEARLY)
                    ->orWhere(function ($scope) use ($termId) {
                        $scope->where('billing_cycle', self::CYCLE_TERMLY)
                            ->where('term_id', $termId);
                    });
            });
        }

        return $query->latest('id')->first();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'ACTIVE',
            self::STATUS_PENDING => 'PENDING PAYMENT',
            default => 'FREE VERSION',
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_PENDING => 'red',
            default => 'yellow',
        };
    }

    public static function invoicePayload(SchoolSubscriptionInvoice $invoice): array
    {
        $meta = is_array($invoice->meta) ? $invoice->meta : [];

        return [
            'id' => (int) $invoice->id,
            'billing_cycle' => $invoice->billing_cycle,
            'billing_cycle_label' => $invoice->billing_cycle === self::CYCLE_YEARLY ? 'Yearly' : 'Termly',
            'reference' => $invoice->reference,
            'status' => $invoice->status,
            'status_label' => match ($invoice->status) {
                'paid' => 'Active',
                'pending_manual_review' => 'Pending Manual Review',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
                default => 'Pending Payment',
            },
            'payment_channel' => $invoice->payment_channel,
            'student_count_snapshot' => (int) $invoice->student_count_snapshot,
            'amount_per_student_snapshot' => self::money($invoice->amount_per_student_snapshot),
            'subtotal' => self::money($invoice->subtotal),
            'tax_percent' => self::decimal($invoice->tax_percent_snapshot),
            'tax_amount' => self::money($invoice->tax_amount),
            'total_amount' => self::money($invoice->total_amount),
            'currency' => $invoice->currency ?: 'NGN',
            'paystack_authorization_url' => $invoice->paystack_authorization_url,
            'paystack_status' => $invoice->paystack_status,
            'paystack_gateway_response' => $invoice->paystack_gateway_response,
            'academic_session_id' => $invoice->academic_session_id ? (int) $invoice->academic_session_id : null,
            'term_id' => $invoice->term_id ? (int) $invoice->term_id : null,
            'paid_at' => $invoice->paid_at?->toDateTimeString(),
            'created_at' => $invoice->created_at?->toDateTimeString(),
            'meta' => $meta,
        ];
    }

    private static function deriveStatus(SchoolSubscriptionSetting $settings, ?SchoolSubscriptionInvoice $activeInvoice): string
    {
        if (in_array($settings->manual_status_override, [self::STATUS_FREE, self::STATUS_PENDING, self::STATUS_ACTIVE], true)) {
            return $settings->manual_status_override;
        }

        if ($settings->is_free_version || self::money($settings->amount_per_student_per_term) <= 0) {
            return self::STATUS_FREE;
        }

        if ($activeInvoice) {
            return self::STATUS_ACTIVE;
        }

        return self::STATUS_PENDING;
    }

    private static function statusReason(
        string $status,
        SchoolSubscriptionSetting $settings,
        ?SchoolSubscriptionInvoice $activeInvoice,
        ?SchoolSubscriptionInvoice $pendingInvoice
    ): string {
        if ($settings->manual_status_override) {
            return 'Status is currently controlled manually by Super Admin.';
        }

        return match ($status) {
            self::STATUS_ACTIVE => $activeInvoice && $activeInvoice->billing_cycle === self::CYCLE_YEARLY
                ? 'A yearly subscription has been paid for the current academic session.'
                : 'A termly subscription has been paid for the current school term.',
            self::STATUS_PENDING => $pendingInvoice && $pendingInvoice->status === 'pending_manual_review'
                ? 'A bank transfer submission is waiting for Super Admin approval.'
                : 'Payment is required before subscription access becomes active.',
            default => '',
        };
    }

    private static function recentInvoicesPayload(int $schoolId): array
    {
        return SchoolSubscriptionInvoice::query()
            ->where('school_id', $schoolId)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (SchoolSubscriptionInvoice $invoice) => self::invoicePayload($invoice))
            ->all();
    }

    private static function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    private static function decimal(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}

