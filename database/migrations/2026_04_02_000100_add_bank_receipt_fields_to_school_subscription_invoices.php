<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_subscription_invoices', function (Blueprint $table) {
            $table->string('bank_receipt_path')->nullable()->after('paid_at');
            $table->string('bank_receipt_name')->nullable()->after('bank_receipt_path');
            $table->string('bank_receipt_mime_type')->nullable()->after('bank_receipt_name');
            $table->timestamp('bank_receipt_uploaded_at')->nullable()->after('bank_receipt_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('school_subscription_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'bank_receipt_path',
                'bank_receipt_name',
                'bank_receipt_mime_type',
                'bank_receipt_uploaded_at',
            ]);
        });
    }
};
