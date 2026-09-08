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
        Schema::table('url_submissions', function (Blueprint $table) {
            // IndexNow submission results (Bing, Yandex, Seznam)
            $table->string('indexnow_status')->nullable()->after('status');  // 'submitted' | 'failed' | null
            $table->unsignedSmallInteger('indexnow_http_status')->nullable()->after('indexnow_status');
            $table->text('indexnow_reason')->nullable()->after('indexnow_http_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('url_submissions', function (Blueprint $table) {
            $table->dropColumn(['indexnow_status', 'indexnow_http_status', 'indexnow_reason']);
        });
    }
};
