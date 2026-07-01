<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_list_items', function (Blueprint $table) {
            // How much was actually picked, and how much is short (back-ordered).
            $table->decimal('picked_quantity', 14, 2)->nullable()->after('picked');
            $table->decimal('short_quantity', 14, 2)->default(0)->after('picked_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('pick_list_items', function (Blueprint $table) {
            $table->dropColumn(['picked_quantity', 'short_quantity']);
        });
    }
};
