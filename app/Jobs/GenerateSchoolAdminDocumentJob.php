<?php

namespace App\Jobs;

use App\Http\Controllers\Api\SchoolAdmin\ReportsController;
use App\Http\Controllers\Api\SchoolAdmin\TranscriptController;
use App\Models\GeneratedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateSchoolAdminDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 240;

    public function __construct(public int $generatedDocumentId)
    {
    }

    public function handle(TranscriptController $transcriptController, ReportsController $reportsController): void
    {
        $document = GeneratedDocument::query()->find($this->generatedDocumentId);
        if (!$document) {
            return;
        }

        $document->forceFill([
            'status' => GeneratedDocument::STATUS_PROCESSING,
            'error_message' => null,
            'started_at' => now(),
        ])->save();

        try {
            $payload = is_array($document->payload) ? $document->payload : [];
            $generated = match ((string) $document->type) {
                'school_admin_transcript_pdf' => $transcriptController->generateTranscriptPdfDocumentForJob(
                    (int) $document->requested_by_user_id,
                    (int) $document->school_id,
                    (string) ($payload['email'] ?? '')
                ),
                'school_admin_broadsheet_pdf' => $reportsController->generateBroadsheetPdfDocumentForJob(
                    (int) $document->requested_by_user_id,
                    (int) $document->school_id,
                    $payload
                ),
                'school_admin_teacher_report_pdf' => $reportsController->generateTeacherReportPdfDocumentForJob(
                    (int) $document->requested_by_user_id,
                    (int) $document->school_id,
                    $payload
                ),
                'school_admin_student_report_pdf' => $reportsController->generateStudentReportPdfDocumentForJob(
                    (int) $document->requested_by_user_id,
                    (int) $document->school_id,
                    $payload
                ),
                'school_admin_student_result_pdf' => $reportsController->generateStudentResultPdfDocumentForJob(
                    (int) $document->requested_by_user_id,
                    (int) $document->school_id,
                    $payload
                ),
                default => throw new \RuntimeException('Unsupported generated document type.'),
            };

            $safeName = trim((string) ($generated['file_name'] ?? 'generated_document.pdf')) ?: 'generated_document.pdf';
            $path = 'generated-documents/school-' . $document->school_id
                . '/admin-documents/' . Str::slug((string) $document->type)
                . '/' . Str::uuid() . '-' . $safeName;

            Storage::disk('local')->put($path, $generated['pdf_output']);

            $document->forceFill([
                'status' => GeneratedDocument::STATUS_COMPLETED,
                'disk' => 'local',
                'file_path' => $path,
                'file_name' => $safeName,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $e) {
            $document->forceFill([
                'status' => GeneratedDocument::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $e;
        }
    }
}
