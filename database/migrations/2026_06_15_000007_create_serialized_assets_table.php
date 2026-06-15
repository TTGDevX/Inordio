<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serialized_assets', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // The product type this unit is an instance of (optional — some
            // assets are ad hoc and not catalogued as an inventory item).
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            // Arbitrary-depth nesting (the LEGO model, §5). A null parent means
            // this is a top-level asset. On parent deletion children float up to
            // root rather than being destroyed.
            $table->foreignId('parent_id')->nullable()->constrained('serialized_assets')->nullOnDelete();

            $table->string('serial_number');

            // Location is only meaningful on root assets; nested assets inherit
            // their effective location from the topmost parent (resolved in the
            // model). Stored here so a root asset has a home.
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            // in_stock | deployed | retired
            $table->string('status', 20)->default('in_stock');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'serial_number']);
            $table->index(['tenant_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serialized_assets');
    }
};
