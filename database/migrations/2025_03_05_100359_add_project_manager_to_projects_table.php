<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->onDelete('set null')->after('client_id');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null')->after('project_manager_id');
        });
    }

    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_manager_id']);
            $table->dropForeign(['assigned_by']);
            $table->dropColumn(['project_manager_id', 'assigned_by']);
        });
    }
};

