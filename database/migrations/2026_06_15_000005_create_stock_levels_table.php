<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();

            // Decimal so we can hold 300.5 ft of cable as well as whole units.
            $table->decimal('quantity', 14, 2)->default(0);

            // Reorder point is per location: a truck and the warehouse want
            // different minimums for the same item. Null = no alert configured.
            $table->decimal('min_quantity', 14, 2)->nullable();

            $table->timestamps();

            // One stock row per item per location within a tenant.
            $table->unique(['tenant_id', 'inventory_item_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
