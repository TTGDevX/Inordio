<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('legal_name')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('province', 2)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();      // GST/HST registration #
            $table->string('payment_terms')->nullable();   // e.g. "Net 15"
            $table->text('invoice_footer')->nullable();
            $table->string('accent_color', 7)->nullable(); // hex, e.g. #4f46e5
            $table->string('logo_path')->nullable();
            $table->timestamps();

            // One settings row per tenant.
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
