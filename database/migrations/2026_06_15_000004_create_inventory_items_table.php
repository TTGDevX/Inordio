<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Dual SKU system (explicit Scott requirement, PROJECT-BRIEF.md §5).
            $table->string('internal_sku');           // our own product code, e.g. TTG-CAT6-BLU-1000
            $table->string('vendor_sku')->nullable();  // the supplier's product code

            $table->string('barcode')->nullable();
            $table->string('unit_of_measure', 32)->default('each');

            // Cost (what we pay) vs price (what we charge) — both required.
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);

            // Whether stock is tracked as fungible quantity. Serialized assets
            // reference an item but carry their own identity (see §5 LEGO model).
            $table->boolean('is_serialized')->default(false);

            $table->string('photo_path')->nullable();
            $table->timestamps();

            // Internal SKU is unique within a tenant.
            $table->unique(['tenant_id', 'internal_sku']);
            $table->index(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
