<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->decimal('amount_due_snapshot', 12, 2);
            $table->decimal('amount_paid', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 20)->default('pending'); // pending|success|failed
            $table->string('reference')->unique();
            $table->string('paystack_access_code')->nullable();
            $table->string('paystack_authorization_url')->nullable();
            $table->string('paystack_status')->nullable();
            $table->string('paystack_gateway_response')->nullable();
            $table->string('paystack_channel', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_session_id', 'term_id']);
            $table->index(['school_id', 'student_id', 'term_id']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_fee_payments');
    }
};
