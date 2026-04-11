<?php

namespace App\Jobs;

use App\Http\Controllers\Api\Student\ResultsController;
use App\Models\GeneratedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateStudentResultPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $generatedDocumentId)
    {
    }

    public function handle(ResultsController $resultsController): void
    {
        $document = GeneratedDocument::query()->find($this->generatedDocumentId);
        if (! $document) {
            return;
        }

        $document->forceFill([
            'status' => GeneratedDocument::STATUS_PROCESSING,
            'error_message' => null,
            'started_at' => now(),
        ])->save();

        try {
            $payload = is_array($document->payload) ? $document->payload : [];
            $generated = $resultsController->generateStudentResultPdfDocumentForJob(
                (int) $document->requested_by_user_id,
                (int) $document->school_id,
                (int) ($payload['class_id'] ?? 0),
                (int) ($payload['term_id'] ?? 0),
            );

            $safeName = trim((string) ($generated['file_name'] ?? 'student_result.pdf')) ?: 'student_result.pdf';
            $path = 'generated-documents/school-' . $document->school_id
                . '/student-results/user-' . $document->requested_by_user_id
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
