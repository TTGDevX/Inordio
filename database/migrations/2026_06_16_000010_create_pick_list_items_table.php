<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_list_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('pick_list_id')->constrained('pick_lists')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 14, 2)->default(1);
            $table->boolean('picked')->default(false);
            // Where the item was pulled from (set when picked).
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'pick_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_items');
    }
};
