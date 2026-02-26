<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('schools')) {
            return;
        }

        Schema::table('schools', function (Blueprint $table) {
            if (!Schema::hasColumn('schools', 'contact_email')) {
                $table->string('contact_email')->nullable()->after('location');
            }

            if (!Schema::hasColumn('schools', 'contact_phone')) {
                $table->string('contact_phone', 30)->nullable()->after('contact_email');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('schools')) {
            return;
        }

        Schema::table('schools', function (Blueprint $table) {
            if (Schema::hasColumn('schools', 'contact_phone')) {
                $table->dropColumn('contact_phone');
            }

            if (Schema::hasColumn('schools', 'contact_email')) {
                $table->dropColumn('contact_email');
            }
        });
    }
};
