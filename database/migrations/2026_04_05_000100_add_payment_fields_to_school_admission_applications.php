<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_admission_applications', function (Blueprint $table) {
            if (Schema::hasColumn('school_admission_applications', 'application_number')) {
                $table->string('application_number')->nullable()->unique()->change();
            }

            if (! Schema::hasColumn('school_admission_applications', 'payment_status')) {
                $table->string('payment_status', 30)->default('pending')->after('applying_for_class');
            }

            if (! Schema::hasColumn('school_admission_applications', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('school_admission_applications', 'amount_due')) {
                $table->decimal('amount_due', 12, 2)->nullable()->after('payment_reference');
            }

            if (! Schema::hasColumn('school_admission_applications', 'tax_rate')) {
                $table->decimal('tax_rate', 6, 2)->nullable()->after('amount_due');
            }

            if (! Schema::hasColumn('school_admission_applications', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->nullable()->after('tax_rate');
            }

            if (! Schema::hasColumn('school_admission_applications', 'amount_total')) {
                $table->decimal('amount_total', 12, 2)->nullable()->after('tax_amount');
            }

            if (! Schema::hasColumn('school_admission_applications', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->nullable()->after('amount_total');
            }

            if (! Schema::hasColumn('school_admission_applications', 'paystack_access_code')) {
                $table->string('paystack_access_code')->nullable()->after('amount_paid');
            }

            if (! Schema::hasColumn('school_admission_applications', 'paystack_authorization_url')) {
                $table->text('paystack_authorization_url')->nullable()->after('paystack_access_code');
            }

            if (! Schema::hasColumn('school_admission_applications', 'paystack_status')) {
                $table->string('paystack_status')->nullable()->after('paystack_authorization_url');
            }

            if (! Schema::hasColumn('school_admission_applications', 'paystack_gateway_response')) {
                $table->string('paystack_gateway_response')->nullable()->after('paystack_status');
            }

            if (! Schema::hasColumn('school_admission_applications', 'paystack_channel')) {
                $table->string('paystack_channel')->nullable()->after('paystack_gateway_response');
            }

            if (! Schema::hasColumn('school_admission_applications', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('paystack_channel');
            }

            if (! Schema::hasColumn('school_admission_applications', 'admin_result_status')) {
                $table->string('admin_result_status', 20)->nullable()->after('score');
            }

            $table->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('school_admission_applications', function (Blueprint $table) {
            if (Schema::hasColumn('school_admission_applications', 'admin_result_status')) {
                $table->dropColumn('admin_result_status');
            }
            if (Schema::hasColumn('school_admission_applications', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('school_admission_applications', 'paystack_channel')) {
                $table->dropColumn('paystack_channel');
            }
            if (Schema::hasColumn('school_admission_applications', 'paystack_gateway_response')) {
                $table->dropColumn('paystack_gateway_response');
            }
            if (Schema::hasColumn('school_admission_applications', 'paystack_status')) {
                $table->dropColumn('paystack_status');
            }
            if (Schema::hasColumn('school_admission_applications', 'paystack_authorization_url')) {
                $table->dropColumn('paystack_authorization_url');
            }
            if (Schema::hasColumn('school_admission_applications', 'paystack_access_code')) {
                $table->dropColumn('paystack_access_code');
            }
            if (Schema::hasColumn('school_admission_applications', 'amount_paid')) {
                $table->dropColumn('amount_paid');
            }
            if (Schema::hasColumn('school_admission_applications', 'amount_total')) {
                $table->dropColumn('amount_total');
            }
            if (Schema::hasColumn('school_admission_applications', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('school_admission_applications', 'tax_rate')) {
                $table->dropColumn('tax_rate');
            }
            if (Schema::hasColumn('school_admission_applications', 'amount_due')) {
                $table->dropColumn('amount_due');
            }
            if (Schema::hasColumn('school_admission_applications', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
            if (Schema::hasColumn('school_admission_applications', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('school_admission_applications', 'application_number')) {
                $table->string('application_number')->nullable(false)->unique()->change();
            }
        });
    }
};
