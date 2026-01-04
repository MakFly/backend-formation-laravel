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
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrollment_id');
            $table->uuid('lesson_id');
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->integer('progress_percentage')->default(0)->comment('Lesson progress 0-100');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('time_spent_seconds')->default(0)->comment('Time spent on lesson in seconds');
            $table->integer('access_count')->default(0)->comment('Number of times lesson was accessed');
            $table->integer('current_position')->default(0)->comment('Video position in seconds');
            $table->boolean('is_favorite')->default(false);
            $table->json('metadata')->nullable();
            $table->json('notes')->nullable()->comment('Student notes on lesson');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');

            $table->unique(['enrollment_id', 'lesson_id']);
            $table->index(['enrollment_id', 'status']);
            $table->index(['lesson_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
