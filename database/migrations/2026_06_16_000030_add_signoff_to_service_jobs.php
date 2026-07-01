<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('notes');
            $table->string('signed_by_name')->nullable()->after('signature_path');
            $table->timestamp('signed_at')->nullable()->after('signed_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropColumn(['signature_path', 'signed_by_name', 'signed_at']);
        });
    }
};
