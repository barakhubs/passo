<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone'); // Removed unique() from here
            $table->boolean('is_verified')->default(false);
            $table->string('email')->nullable()->unique();
            $table->string('country_code')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('inactive');
            $table->enum('role', ['admin', 'manager'])->default('manager');
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Add composite unique index
            $table->unique(['country_code', 'phone']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
