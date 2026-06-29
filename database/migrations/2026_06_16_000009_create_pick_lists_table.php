<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_lists', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
            // The truck (or location) parts are being picked to.
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->string('status', 20)->default('open'); // App\Enums\PickListStatus
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // One pick list per job (per tenant).
            $table->unique(['tenant_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_lists');
    }
};
