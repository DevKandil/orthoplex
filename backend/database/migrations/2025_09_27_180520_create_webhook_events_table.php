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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // user.created, user.updated, login.successful, etc.
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('payload_schema')->nullable(); // JSON schema for the event payload
            $table->integer('delivery_attempts')->default(3); // Max retry attempts
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
