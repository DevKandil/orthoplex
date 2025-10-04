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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events')->nullable(); // Array of event types to listen for
            $table->string('secret')->nullable(); // HMAC secret for signature verification
            $table->boolean('active')->default(true);
            $table->json('headers')->nullable(); // Additional headers to send
            $table->integer('max_retries')->default(3);
            $table->integer('retry_delay')->default(60); // seconds
            $table->timestamp('last_triggered_at')->nullable();
            $table->json('metadata')->nullable(); // Additional webhook metadata
            $table->timestamps();

            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
