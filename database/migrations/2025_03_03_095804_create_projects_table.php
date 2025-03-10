<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_team_id')->constrained('teams')->onDelete('cascade'); // Sales Team assigned to the project
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade'); // Client for whom the project is created
            $table->string('project_name');
            $table->text('requirements')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
};

