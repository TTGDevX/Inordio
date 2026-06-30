<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('to_location_id')->constrained('suppliers')->nullOnDelete();
            $table->decimal('unit_cost', 14, 2)->nullable()->after('supplier_id'); // purchase cost on receipts
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            // Running weighted-average of what on-hand stock actually cost.
            // (`cost` stays the replacement/preferred cost used for quoting.)
            $table->decimal('average_cost', 14, 2)->default(0)->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('unit_cost');
        });
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('average_cost');
        });
    }
};
