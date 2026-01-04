<?php

declare(strict_types=1);

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
        Schema::create('certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrollment_id');
            $table->uuid('customer_id');
            $table->uuid('formation_id');

            // Certificate details
            $table->string('certificate_number')->unique();
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->string('verification_code')->unique();

            // Certificate content
            $table->string('student_name');
            $table->string('formation_title');
            $table->string('instructor_name')->nullable();
            $table->date('completion_date');

            // PDF storage
            $table->string('pdf_path')->nullable();
            $table->integer('pdf_size_bytes')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('enrollment_id');
            $table->index('customer_id');
            $table->index('formation_id');
            $table->index('status');
            $table->index('verification_code');
            $table->index('certificate_number');

            // Foreign keys
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('formation_id')->references('id')->on('formations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
