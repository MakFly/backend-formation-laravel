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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('formation_id');
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'refunded', 'suspended'])->default('pending');
            $table->integer('progress_percentage')->default(0)->comment('Overall progress 0-100');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->integer('access_count')->default(0)->comment('Number of times accessed');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('formation_id')->references('id')->on('formations')->onDelete('cascade');

            $table->index(['customer_id', 'formation_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['formation_id', 'status']);
            $table->index('status');
            $table->index('enrolled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
