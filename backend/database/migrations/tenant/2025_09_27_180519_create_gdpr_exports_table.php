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
        Schema::create('gdpr_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('export_id')->unique();
            $table->string('email');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('format', ['json', 'csv', 'pdf'])->default('json');
            $table->boolean('include_deleted')->default(false);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable(); // in bytes
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Download expiry
            $table->text('error_message')->nullable();
            $table->json('export_options')->nullable(); // What data to include
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('export_id');
            $table->index('email');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdpr_exports');
    }
};
