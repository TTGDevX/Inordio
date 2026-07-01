<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('title');
            $table->string('cadence', 20); // monthly | quarterly | semiannual | annual
            $table->date('next_run_at');
            $table->date('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'next_run_at']);
        });

        Schema::create('service_agreement_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('service_agreement_id')->constrained('service_agreements')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'service_agreement_id']);
        });

        // Spawned jobs link back to the agreement that generated them.
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->foreignId('service_agreement_id')->nullable()->after('quote_id')
                ->constrained('service_agreements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_agreement_id');
        });
        Schema::dropIfExists('service_agreement_items');
        Schema::dropIfExists('service_agreements');
    }
};
