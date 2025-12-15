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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // Client who made the booking
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Aesthetician assigned to this booking (optional at first)
            $table->foreignId('aesthetician_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Service being booked
            $table->foreignId('service_id')
                ->constrained()
                ->onDelete('cascade');

            // Schedule
            $table->date('appointment_date');
            $table->time('appointment_time');

            // pending, approved, rejected, cancelled, completed, expired
            $table->string('status')->default('pending');

            // Optional notes (e.g., skin concerns)
            $table->text('notes')->nullable();

            $table->timestamps();

            // For faster lookups when we check conflicts/expiry
            $table->index(['appointment_date', 'appointment_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
