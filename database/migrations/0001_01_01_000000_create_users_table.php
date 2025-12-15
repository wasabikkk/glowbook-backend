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
        $table->string('name')->nullable(); // we’ll mostly use first/last name below
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();

        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');

        // NEW: role + super admin flag
        $table->enum('role', ['admin', 'aesthetician', 'client'])->default('client');
        $table->boolean('is_super_admin')->default(false);

        // Profile stuff (optional but nice to have)
        $table->string('phone')->nullable();
        $table->string('avatar')->nullable(); // we’ll store filename/path

        $table->rememberToken();
        $table->timestamps();
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
