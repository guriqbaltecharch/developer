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
        Schema::table('project_user', function (Blueprint $table) {
            $table->unsignedBigInteger('project_manager_id')->nullable()->after('user_id'); // ✅ Add column after user_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('project_manager_id'); // ✅ Rollback support
        });
    }
};
