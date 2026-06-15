<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('serialized_asset_id')->constrained('serialized_assets')->cascadeOnDelete();

            // The parent involved in an assemble/disassemble event, if any.
            $table->foreignId('parent_asset_id')->nullable()->constrained('serialized_assets')->nullOnDelete();

            // created | assembled | disassembled | moved | retired
            $table->string('type', 20);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'serialized_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_events');
    }
};
