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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('enrollment_id')->nullable();
            $table->uuid('formation_id')->nullable();

            // Payment details
            $table->enum('type', ['enrollment', 'subscription', 'renewal'])->default('enrollment');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();

            // Amounts
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('amount_refunded', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_method_type')->nullable(); // card, sepa_debit, etc.

            // Metadata
            $table->string('description')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('failure_code')->nullable();
            $table->json('metadata')->nullable();
            $table->json('stripe_response')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('customer_id');
            $table->index('enrollment_id');
            $table->index('formation_id');
            $table->index('status');
            $table->index('type');
            $table->index('stripe_payment_intent_id');
            $table->index('stripe_checkout_session_id');
            $table->index('paid_at');
            $table->index('created_at');

            // Foreign keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('set null');
            $table->foreign('formation_id')->references('id')->on('formations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
