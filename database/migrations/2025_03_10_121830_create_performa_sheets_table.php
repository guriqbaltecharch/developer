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
        Schema::create('performa_sheets', function (Blueprint $table) {
            $table->id(); // Auto-increment ID
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User ID reference
            $table->json('data'); // Store other fields as JSON
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('performa_sheets');
    }
};
