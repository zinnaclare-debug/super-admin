<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (!Schema::hasColumn('results', 'ca_breakdown')) {
                $table->json('ca_breakdown')->nullable()->after('ca');
            }
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'ca_breakdown')) {
                $table->dropColumn('ca_breakdown');
            }
        });
    }
};

