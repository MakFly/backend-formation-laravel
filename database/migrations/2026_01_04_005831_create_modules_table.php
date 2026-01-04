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
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('formation_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['video', 'text', 'interactive', 'quiz', 'assignment', 'mixed'])->default('mixed');
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_free')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('formation_id')->references('id')->on('formations')->onDelete('cascade');
            $table->index(['formation_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
