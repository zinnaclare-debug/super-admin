<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('virtual_classes', function (Blueprint $table) {
            $table->string('class_type', 20)->default('virtual')->after('term_subject_id');
            $table->string('provider', 40)->default('external')->after('description');
            $table->string('status', 20)->default('live')->after('provider');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->timestamp('live_started_at')->nullable()->after('ends_at');
            $table->timestamp('live_ended_at')->nullable()->after('live_started_at');

            $table->index(['school_id', 'class_type', 'status'], 'virtual_classes_school_type_status_idx');
            $table->index(['school_id', 'term_subject_id', 'status'], 'virtual_classes_school_term_status_idx');
        });

        DB::table('virtual_classes')->update([
            'class_type' => 'virtual',
            'provider' => 'zoom',
            'status' => 'live',
            'live_started_at' => DB::raw('COALESCE(starts_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('virtual_classes', function (Blueprint $table) {
            $table->dropIndex('virtual_classes_school_type_status_idx');
            $table->dropIndex('virtual_classes_school_term_status_idx');
            $table->dropColumn([
                'class_type',
                'provider',
                'status',
                'ends_at',
                'live_started_at',
                'live_ended_at',
            ]);
        });
    }
};
