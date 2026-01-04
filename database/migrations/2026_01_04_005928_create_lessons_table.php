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
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id')->nullable();
            $table->uuid('formation_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('thumbnail')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_published')->default(true);
            $table->integer('order')->default(0);
            $table->json('content_mdx')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('module_id')->references('id')->on('modules')->onDelete('set null');
            $table->foreign('formation_id')->references('id')->on('formations')->onDelete('cascade');
            $table->index(['module_id', 'order']);
            $table->index(['formation_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
