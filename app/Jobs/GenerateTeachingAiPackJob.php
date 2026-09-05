<?php

namespace App\Jobs;

use App\Http\Controllers\Api\Staff\TeachingAiPlannerController;
use App\Models\TeachingAiGenerationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateTeachingAiPackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // The local CPU-only Ollama service generates documents gradually.
    public int $tries = 1;
    public int $timeout = 900;
    public bool $failOnTimeout = true;

    public function __construct(public int $generationJobId)
    {
    }

    public function failed(Throwable $exception): void
    {
        TeachingAiGenerationJob::query()->whereKey($this->generationJobId)->update([
            'status' => TeachingAiGenerationJob::STATUS_FAILED,
            'progress' => 100,
            'error_message' => 'Generation failed: ' . mb_strimwidth($exception->getMessage(), 0, 220, '...'),
            'completed_at' => now(),
        ]);
    }

    public function handle(TeachingAiPlannerController $planner): void
    {
        $job = TeachingAiGenerationJob::query()->find($this->generationJobId);
        if (!$job) {
            return;
        }

        $job->forceFill([
            'status' => TeachingAiGenerationJob::STATUS_PROCESSING,
            'progress' => 10,
            'error_message' => null,
        ])->save();

        try {
            $planner->generateForQueue($job);
        } catch (Throwable $exception) {
            $job->forceFill([
                'status' => TeachingAiGenerationJob::STATUS_FAILED,
                'progress' => 100,
                'error_message' => 'Generation failed: ' . mb_strimwidth($exception->getMessage(), 0, 220, '...'),
                'completed_at' => now(),
            ])->save();

            report($exception);
            throw $exception;
        }
    }
}
