<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'website_content')) {
                $table->json('website_content')->nullable()->after('class_templates');
            }

            if (! Schema::hasColumn('schools', 'entrance_exam_config')) {
                $table->json('entrance_exam_config')->nullable()->after('website_content');
            }
        });

        if (! Schema::hasTable('school_admission_applications')) {
            Schema::create('school_admission_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->string('application_number')->unique();
                $table->string('full_name');
                $table->string('phone', 30);
                $table->string('email');
                $table->string('applying_for_class', 80);
                $table->string('exam_status', 30)->default('pending');
                $table->unsignedInteger('score')->nullable();
                $table->string('result_status', 30)->nullable();
                $table->timestamp('exam_submitted_at')->nullable();
                $table->json('exam_answers')->nullable();
                $table->json('exam_result')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'applying_for_class']);
                $table->index(['school_id', 'email']);
                $table->index(['school_id', 'phone']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('school_admission_applications')) {
            Schema::dropIfExists('school_admission_applications');
        }

        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'entrance_exam_config')) {
                $table->dropColumn('entrance_exam_config');
            }

            if (Schema::hasColumn('schools', 'website_content')) {
                $table->dropColumn('website_content');
            }
        });
    }
};
