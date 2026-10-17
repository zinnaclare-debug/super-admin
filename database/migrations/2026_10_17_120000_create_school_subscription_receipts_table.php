<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_subscription_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_subscription_invoice_id')->constrained('school_subscription_invoices')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('subtotal_paid', 12, 2);
            $table->decimal('processing_fee_paid', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'school_subscription_invoice_id'], 'ssr_school_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_subscription_receipts');
    }
};