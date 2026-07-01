<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('invoice_prefix', 12)->default('INV-')->after('logo_path');
            $table->unsignedInteger('invoice_next_number')->default(1)->after('invoice_prefix');
            $table->string('quote_prefix', 12)->default('Q-')->after('invoice_next_number');
            $table->unsignedInteger('quote_next_number')->default(1)->after('quote_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['invoice_prefix', 'invoice_next_number', 'quote_prefix', 'quote_next_number']);
        });
    }
};
