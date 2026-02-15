<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'paystack_subaccount_code')) {
                $table->string('paystack_subaccount_code')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'paystack_subaccount_code')) {
                $table->dropColumn('paystack_subaccount_code');
            }
        });
    }
};
