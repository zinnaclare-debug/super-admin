<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements') || !Schema::hasColumn('announcements', 'level')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE announcements MODIFY level VARCHAR(50) NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE announcements ALTER COLUMN level TYPE VARCHAR(50)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcements') || !Schema::hasColumn('announcements', 'level')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE announcements MODIFY level ENUM('nursery','primary','secondary') NULL");
        }
    }
};
