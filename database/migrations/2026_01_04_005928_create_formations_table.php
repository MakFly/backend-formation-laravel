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
        Schema::create('formations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->enum('pricing_tier', ['free', 'basic', 'standard', 'premium', 'enterprise'])->default('free');
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('mode', ['online', 'in-person', 'hybrid'])->default('online');
            $table->string('thumbnail')->nullable();
            $table->string('video_trailer')->nullable();
            $table->json('tags')->nullable();
            $table->json('objectives')->nullable();
            $table->json('requirements')->nullable();
            $table->json('target_audience')->nullable();
            $table->string('language')->default('fr');
            $table->json('subtitles')->nullable();
            $table->string('difficulty_level')->default('beginner');
            $table->integer('duration_hours')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('instructor_name')->nullable();
            $table->string('instructor_title')->nullable();
            $table->string('instructor_avatar')->nullable();
            $table->text('instructor_bio')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->integer('enrollment_count')->default(0);
            $table->decimal('average_rating', 2, 1)->default(0);
            $table->integer('review_count')->default(0);
            $table->json('content_mdx')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index(['category_id', 'is_published']);
            $table->index(['is_published', 'published_at']);
            $table->index('pricing_tier');
            $table->index('mode');
            $table->index('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
