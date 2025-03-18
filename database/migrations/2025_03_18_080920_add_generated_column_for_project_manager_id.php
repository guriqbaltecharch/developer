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
        Schema::table('projects', function (Blueprint $table) {
            // ✅ Add a generated column extracting project_manager_id from JSON
            $table->string('project_manager_id_index')->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(project_manager_id, '$[0]'))");

            // ✅ Create an index on the generated column
            $table->index('project_manager_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['project_manager_id_index']);
            $table->dropColumn('project_manager_id_index');
        });
    }
};
