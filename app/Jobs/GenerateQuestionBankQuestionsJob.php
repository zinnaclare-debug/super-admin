<?php

namespace App\Jobs;

use App\Http\Controllers\Api\Staff\QuestionBankController;
use App\Models\QuestionBankGenerationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateQuestionBankQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(public int $generationJobId)
    {
    }

    public function handle(QuestionBankController $questionBank): void
    {
        $job = QuestionBankGenerationJob::query()->find($this->generationJobId);
        if (!$job) {
            return;
        }

        $job->forceFill([
            'status' => QuestionBankGenerationJob::STATUS_PROCESSING,
            'error_message' => null,
        ])->save();

        try {
            $result = $questionBank->generateQuestionsForQueue(
                (int) $job->school_id,
                (int) $job->teacher_user_id,
                (int) $job->subject_id,
                (string) $job->prompt,
                (int) $job->question_count,
                (bool) $job->import_to_bank
            );

            $job->forceFill([
                'status' => QuestionBankGenerationJob::STATUS_COMPLETED,
                'generated_count' => count($result['data'] ?? []),
                'imported_count' => (int) ($result['imported_count'] ?? 0),
                'result_json' => $result,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $job->forceFill([
                'status' => QuestionBankGenerationJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $e;
        }
    }
}
