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
        Schema::create('url_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('notification_type')->default('URL_UPDATED');
            // pending -> processing -> submitted|failed|error
            $table->string('status')->default('pending');
            // Raw HTTP status code returned by Google (token endpoint or indexing endpoint).
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->json('response_body')->nullable();
            // Human-readable failure reason extracted from response_body, when applicable.
            $table->text('failure_reason')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('url_submissions');
    }
};
