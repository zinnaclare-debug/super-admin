<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::table('classes', function (Blueprint $table) {
    // Fast filters
    $table->index(['school_id', 'academic_session_id', 'level']);

    // Prevent duplicates per session
    $table->unique(['school_id', 'academic_session_id', 'level', 'name']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('classes', function (Blueprint $table) {
    $table->dropUnique(['school_id', 'academic_session_id', 'level', 'name']);
    $table->dropIndex(['classes_school_id_academic_session_id_level_index']);
});

    }
};
