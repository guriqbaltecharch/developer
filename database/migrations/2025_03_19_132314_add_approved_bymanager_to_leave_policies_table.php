<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Add approved_bymanager column to leave_policies table
        Schema::table('leavespolicy', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_bymanager')->nullable()->after('status'); // Add the manager's ID who approved the leave
        });
    }

    public function down()
    {
        // Rollback: drop the approved_bymanager column if the migration is rolled back
        Schema::table('leavespolicy', function (Blueprint $table) {
            $table->dropColumn('approved_bymanager');
        });
    }
};
