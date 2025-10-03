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
        Schema::create('gdpr_delete_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('request_id')->unique();
            $table->string('email');
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('scheduled_deletion_at')->nullable(); // Grace period
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable(); // Additional data about the request
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('request_id');
            $table->index('email');
            $table->index('scheduled_deletion_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdpr_delete_requests');
    }
};
