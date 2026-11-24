<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->decimal('ca', 5, 1)->unsigned()->default(0)->change();
            $table->decimal('exam', 5, 1)->unsigned()->default(0)->change();
        });
    }

    public function down(): void
    {
        // Keep decimal columns on rollback so recorded fractional marks are never truncated.
    }
};