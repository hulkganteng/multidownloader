<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->text('source_url');
            $table->string('platform')->index(); // youtube, tiktok, direct
            $table->string('title')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();

            $table->string('format')->default('original'); // mp3, mp4, original
            $table->string('quality')->nullable(); // 1080, 720
            $table->string('bitrate')->nullable(); // 128k, 192k

            $table->string('status')->default('queued')->index(); // queued, processing, finished, failed
            $table->integer('progress')->default(0);

            $table->string('output_path')->nullable(); // relative to storage/app
            $table->string('output_filename')->nullable();
            $table->string('output_mime')->nullable();
            $table->bigInteger('output_size_bytes')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_tasks');
    }
};
