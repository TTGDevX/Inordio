<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('method', 20);          // App\Enums\PaymentMethod
            $table->string('reference')->nullable(); // cheque #, e-transfer ref, etc.
            $table->date('paid_at')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
