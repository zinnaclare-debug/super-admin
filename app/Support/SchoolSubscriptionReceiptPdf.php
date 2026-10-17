<?php

namespace App\Support;

use App\Models\School;
use App\Models\SchoolSubscriptionInvoice;
use App\Models\SchoolSubscriptionReceipt;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SchoolSubscriptionReceiptPdf
{
    public static function download(School $school, SchoolSubscriptionInvoice $invoice, SchoolSubscriptionReceipt $receipt)
    {
        $website = SchoolPublicWebsiteData::normalizeWebsiteContent($school->website_content, $school);
        $summary = SchoolSubscriptionBilling::receiptSummary($invoice);
        $meta = is_array($invoice->meta) ? $invoice->meta : [];

        try {
            $html = view('pdf.school_subscription_receipt', [
                'school' => $school,
                'invoice' => $invoice,
                'receipt' => $receipt,
                'summary' => $summary,
                'sessionName' => data_get($meta, 'session_name', '-'),
                'termName' => data_get($meta, 'term_name', $invoice->billing_cycle === SchoolSubscriptionBilling::CYCLE_YEARLY ? 'All Terms' : '-'),
                'issuerName' => config('app.name', 'LyteBridge Professional Service Ltd'),
                'issuerEmail' => config('mail.from.address', 'support@lyt.com.ng'),
                'primaryColor' => $website['primary_color'] ?? '#164e77',
                'logoDataUri' => self::imageDataUri($school->logo_path),
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
                'Content-Disposition' => 'attachment; filename="school_subscription_receipt_' . $receipt->receipt_number . '.pdf"',
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to generate school subscription receipt PDF.', ['school_id' => (int) $school->id, 'invoice_id' => (int) $invoice->id, 'receipt_id' => (int) $receipt->id, 'error' => $exception->getMessage()]);
            return response()->json(['message' => 'Unable to generate the subscription receipt right now.'], 500);
        }
    }

    private static function imageDataUri(?string $storagePath): ?string
    {
        if (!$storagePath || !Storage::disk('public')->exists($storagePath)) return null;
        $path = Storage::disk('public')->path($storagePath);
        $contents = @file_get_contents($path);
        if ($contents === false) return null;
        $mime = @mime_content_type($path) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}