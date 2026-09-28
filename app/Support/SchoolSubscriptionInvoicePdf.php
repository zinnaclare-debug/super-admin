<?php

namespace App\Support;

use App\Models\School;
use App\Models\SchoolSubscriptionInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SchoolSubscriptionInvoicePdf
{
    public static function download(School $school, string $cycle)
    {
        $context = SchoolSubscriptionBilling::quoteForCycle($school, $cycle);
        $settings = $context['settings'];
        $quote = $context['quote'];
        $session = $context['session'];
        $term = $context['term'];

        if ($settings->is_free_version) return response()->json(['message' => 'This school is currently on the free version.'], 422);
        if (!$session || !$term) return response()->json(['message' => 'A current academic session and term must be configured before billing can start.'], 422);
        if (!$quote) return response()->json(['message' => 'Subscription billing has not been configured for this payment option yet.'], 422);

        $websiteContent = SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);
        $existingInvoice = self::findMatchingInvoice((int) $school->id, (int) $session->id, $cycle === SchoolSubscriptionBilling::CYCLE_TERMLY ? (int) $term->id : null, $cycle);
        $statusLabel = $existingInvoice ? self::statusLabel((string) $existingInvoice->status) : 'Pending Payment';
        $invoiceNumber = $existingInvoice?->reference ?: sprintf('SSB-QUOTE-%d-%s-%s', (int) $school->id, strtoupper($cycle === SchoolSubscriptionBilling::CYCLE_YEARLY ? 'Y' : 'T'), now()->format('YmdHis'));

        try {
            @set_time_limit(120);
            @ini_set('memory_limit', '512M');
            $html = view('pdf.school_subscription_invoice', [
                'school' => $school,
                'logoDataUri' => self::imageDataUri($school->logo_path),
                'invoiceNumber' => $invoiceNumber,
                'generatedAt' => now()->toDateTimeString(),
                'statusLabel' => $statusLabel,
                'billingLabel' => $cycle === SchoolSubscriptionBilling::CYCLE_YEARLY ? 'Yearly School Billing Invoice' : 'Termly School Billing Invoice',
                'primaryColor' => $websiteContent['primary_color'] ?? '#0f172a',
                'accentColor' => $websiteContent['accent_color'] ?? '#0f766e',
                'primaryTint' => self::blendHexColor((string) ($websiteContent['primary_color'] ?? '#0f172a'), '#ffffff', 0.84),
                'accentTint' => self::blendHexColor((string) ($websiteContent['accent_color'] ?? '#0f766e'), '#ffffff', 0.82),
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

            $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dompdf';
            if (!is_dir($tempDir)) @mkdir($tempDir, 0775, true);
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('tempDir', $tempDir);
            $options->set('fontDir', $tempDir);
            $options->set('fontCache', $tempDir);
            $options->set('chroot', base_path());
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="school_subscription_invoice_' . $cycle . '.pdf"',
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to generate school subscription invoice PDF.', ['school_id' => (int) $school->id, 'billing_cycle' => $cycle, 'error' => $exception->getMessage()]);
            return response()->json(['message' => 'Unable to generate the school subscription invoice right now.'], 500);
        }
    }

    private static function findMatchingInvoice(int $schoolId, int $sessionId, ?int $termId, string $cycle): ?SchoolSubscriptionInvoice
    {
        $query = SchoolSubscriptionInvoice::query()->where('school_id', $schoolId)->where('academic_session_id', $sessionId)->where('billing_cycle', $cycle);
        if ($cycle === SchoolSubscriptionBilling::CYCLE_TERMLY) $query->where('term_id', $termId); else $query->whereNull('term_id');
        return $query->latest('id')->first();
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Active', 'pending_manual_review' => 'Pending Manual Review', 'failed' => 'Failed', 'cancelled' => 'Cancelled', default => 'Pending Payment',
        };
    }

    private static function imageDataUri(?string $storagePath): ?string
    {
        if (!$storagePath || !Storage::disk('public')->exists($storagePath)) return null;
        $fullPath = Storage::disk('public')->path($storagePath);
        $mime = @mime_content_type($fullPath) ?: 'image/png';
        $data = @file_get_contents($fullPath);
        return $data === false ? null : 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private static function blendHexColor(string $baseHex, string $targetHex, float $targetWeight): string
    {
        $base = self::hexToRgb($baseHex);
        $target = self::hexToRgb($targetHex);
        $weight = max(0, min(1, $targetWeight));
        return sprintf('#%02x%02x%02x', (int) round(($base['r'] * (1 - $weight)) + ($target['r'] * $weight)), (int) round(($base['g'] * (1 - $weight)) + ($target['g'] * $weight)), (int) round(($base['b'] * (1 - $weight)) + ($target['b'] * $weight)));
    }

    private static function hexToRgb(string $hex): array
    {
        $normalized = ltrim(trim($hex), '#');
        if (strlen($normalized) === 3) $normalized = preg_replace('/(.)/', '$1$1', $normalized) ?: '000000';
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) $normalized = '000000';
        return ['r' => hexdec(substr($normalized, 0, 2)), 'g' => hexdec(substr($normalized, 2, 2)), 'b' => hexdec(substr($normalized, 4, 2))];
    }
}