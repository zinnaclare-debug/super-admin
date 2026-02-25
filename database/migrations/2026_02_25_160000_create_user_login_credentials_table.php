<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->longText('password_encrypted')->nullable();
            $table->timestamp('last_password_set_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['school_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_credentials');
    }
};

