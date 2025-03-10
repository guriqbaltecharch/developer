<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['sales_team_id']);
            
            $table->unsignedBigInteger('sales_team_id')->change();
        });
    }

    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            // sales_team_id wapas foreign key banayen (teams table se)
            $table->foreignId('sales_team_id')->constrained('teams')->onDelete('cascade')->change();
        });
    }
};

