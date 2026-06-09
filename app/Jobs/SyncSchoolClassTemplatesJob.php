<?php

namespace App\Jobs;

use App\Http\Controllers\Api\SuperAdmin\SchoolController;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSchoolClassTemplatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public int $schoolId,
        public array $templates,
        public array $departmentTemplateMapByClass,
        public array $previousTemplates,
        public array $previousDepartmentTemplateMapByClass
    ) {
    }

    public function handle(SchoolController $schoolController): void
    {
        $school = School::query()->find($this->schoolId);
        if (!$school) {
            return;
        }

        $schoolController->syncClassTemplatesToExistingSessions(
            $school,
            $this->templates,
            $this->departmentTemplateMapByClass,
            $this->previousTemplates,
            $this->previousDepartmentTemplateMapByClass
        );
    }
}
