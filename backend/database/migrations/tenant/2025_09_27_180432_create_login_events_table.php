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
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->string('login_method')->default('password'); // password, magic_link, 2fa
            $table->boolean('successful')->default(true);
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('attempted_at');
            $table->timestamps();

            // Indexes for analytics
            $table->index(['user_id', 'attempted_at']);
            $table->index(['attempted_at']);
            $table->index(['successful']);
            $table->index(['ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};