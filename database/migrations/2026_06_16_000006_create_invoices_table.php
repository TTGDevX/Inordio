<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('service_jobs')->nullOnDelete();

            $table->string('number')->nullable();             // INV-00001
            $table->string('status', 20)->default('draft');   // App\Enums\InvoiceStatus

            // Tax is snapshotted at issue time — rates change, but a sent invoice
            // must not (brief §7). Province is captured from the customer.
            $table->string('province', 2)->nullable();
            $table->boolean('tax_exempt')->default(false);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->json('tax_breakdown')->nullable();        // [{label, rate, amount}]

            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
