<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('title')->nullable();
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('compressed_size')->nullable();
            $table->string('status', 30)->default('processing');
            $table->text('processing_note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_session_id', 'term_id'], 'teaching_materials_period_idx');
            $table->index(['school_id', 'staff_user_id', 'academic_session_id', 'term_id'], 'teaching_materials_staff_period_idx');
            $table->index(['category', 'status'], 'teaching_materials_category_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_materials');
    }
};
