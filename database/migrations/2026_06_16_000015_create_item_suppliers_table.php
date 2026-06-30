<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();

            $table->string('vendor_sku')->nullable();      // this wholesaler's part number
            $table->decimal('cost', 14, 2)->default(0);     // this wholesaler's price to us
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            // One offering per supplier per item.
            $table->unique(['tenant_id', 'inventory_item_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_suppliers');
    }
};
