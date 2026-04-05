<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_admission_applications', function (Blueprint $table) {
            $table->dateTime('exam_rescheduled_for')->nullable()->after('exam_submitted_at');
            $table->dateTime('exam_reset_at')->nullable()->after('exam_rescheduled_for');
        });
    }

    public function down(): void
    {
        Schema::table('school_admission_applications', function (Blueprint $table) {
            $table->dropColumn(['exam_rescheduled_for', 'exam_reset_at']);
        });
    }
};
