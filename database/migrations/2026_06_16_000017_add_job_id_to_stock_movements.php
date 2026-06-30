<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Ties a usage (consumption) movement to the job it was used on,
            // so we can compute per-job cost of goods.
            $table->foreignId('job_id')->nullable()->after('to_location_id')->constrained('service_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_id');
        });
    }
};
