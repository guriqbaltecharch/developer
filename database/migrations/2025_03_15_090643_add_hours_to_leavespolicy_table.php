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
        Schema::table('leavespolicy', function (Blueprint $table) {
            $table->integer('hours')->nullable()->after('end_date'); // ✅ Add hours column for Short Leave
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leavespolicy', function (Blueprint $table) {
            //
        });
    }
};
