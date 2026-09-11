<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'school_code')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('school_code', 8)->nullable()->unique();
            });
        }

        DB::table('schools')
            ->whereNull('school_code')
            ->orderBy('id')
            ->eachById(function (object $school): void {
                do {
                    $code = Str::upper(Str::random(3)) . '-' . Str::upper(Str::random(4));
                } while (DB::table('schools')->where('school_code', $code)->exists());

                DB::table('schools')->where('id', $school->id)->update(['school_code' => $code]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'school_code')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropUnique(['school_code']);
                $table->dropColumn('school_code');
            });
        }
    }
};