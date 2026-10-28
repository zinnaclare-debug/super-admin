<?php

namespace App\Jobs;

use App\Http\Controllers\Api\SchoolAdmin\RegistrationController;
use App\Models\BulkRegistrationImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessBulkRegistrationImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $bulkRegistrationImportId)
    {
    }

    public function handle(RegistrationController $registration): void
    {
        $registration->processBulkImportChunk($this->bulkRegistrationImportId);
    }

    public function failed(Throwable $exception): void
    {
        BulkRegistrationImport::query()->whereKey($this->bulkRegistrationImportId)->update([
            'status' => BulkRegistrationImport::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}