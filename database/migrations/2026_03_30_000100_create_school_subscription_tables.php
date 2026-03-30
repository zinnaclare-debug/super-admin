<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_subscription_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->decimal('amount_per_student_per_term', 12, 2)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->decimal('tax_percent', 5, 2)->default(1.60);
            $table->boolean('allow_termly')->default(true);
            $table->boolean('allow_yearly')->default(true);
            $table->boolean('is_free_version')->default(true);
            $table->enum('manual_status_override', ['free', 'pending', 'active'])->nullable();
            $table->string('bank_name')->default('ECOBANK');
            $table->string('bank_account_number')->default('3680106500');
            $table->string('bank_account_name')->default('LYTEBRIDGE PROFESSIONAL SERVICE LTD');
            $table->text('notes')->nullable();
            $table->foreignId('configured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('school_id');
        });

        Schema::create('school_subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->enum('billing_cycle', ['termly', 'yearly']);
            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'pending_manual_review', 'paid', 'failed', 'cancelled'])->default('pending');
            $table->enum('payment_channel', ['paystack', 'bank', 'manual'])->nullable();
            $table->unsignedInteger('student_count_snapshot')->default(0);
            $table->decimal('amount_per_student_snapshot', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_percent_snapshot', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('paystack_access_code')->nullable();
            $table->text('paystack_authorization_url')->nullable();
            $table->string('paystack_status')->nullable();
            $table->string('paystack_gateway_response')->nullable();
            $table->string('paystack_channel')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'academic_session_id', 'term_id', 'billing_cycle'], 'ssi_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_subscription_invoices');
        Schema::dropIfExists('school_subscription_settings');
    }
};
