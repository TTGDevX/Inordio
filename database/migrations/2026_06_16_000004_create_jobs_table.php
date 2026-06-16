<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: Laravel's queue "jobs" table is named "jobs" too, but this app
        // uses the sync/database queue without that table in tests; the domain
        // Job model points explicitly at "service_jobs" to avoid any clash.
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->nullable();           // J-00001
            $table->string('title');
            $table->string('status', 20)->default('scheduled'); // App\Enums\JobStatus
            $table->dateTime('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
