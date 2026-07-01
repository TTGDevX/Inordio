<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_time_entries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('hours', 8, 2);
            $table->decimal('rate', 14, 2)->default(0); // snapshot of the hourly rate
            $table->date('performed_on')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'job_id']);
        });

        // Default hourly rate to prefill new labour entries.
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('default_labour_rate', 14, 2)->nullable()->after('mail_from_name');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('default_labour_rate');
        });
        Schema::dropIfExists('job_time_entries');
    }
};
