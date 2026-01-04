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
        Schema::create('lesson_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lesson_id');
            $table->string('title');
            $table->enum('type', ['pdf', 'video', 'audio', 'image', 'document', 'archive', 'link', 'code', 'attachment']);
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable()->comment('Size in bytes');
            $table->integer('duration')->nullable()->comment('Duration in seconds for video/audio');
            $table->text('description')->nullable();
            $table->boolean('is_downloadable')->default(true);
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
            $table->index(['lesson_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_resources');
    }
};
