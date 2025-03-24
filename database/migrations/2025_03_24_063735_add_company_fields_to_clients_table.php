<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('hire_on_id'); // ✅ Added after `hire_on_id`
            $table->string('company_address')->nullable()->after('company_name'); // ✅ Added after `company_name`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('company_name');
            $table->dropColumn('company_address');
        });
    }
};
