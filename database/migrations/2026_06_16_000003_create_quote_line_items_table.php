<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            // Optional link to the catalogue; description/price are snapshotted
            // onto the line so later catalogue edits don't rewrite past quotes.
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'quote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_items');
    }
};
