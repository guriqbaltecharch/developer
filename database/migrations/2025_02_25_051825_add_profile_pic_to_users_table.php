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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_pic')->nullable()->after('email');
            $table->string('address')->nullable()->after('profile_pic');
            $table->string('phone_num')->nullable()->after('address');
            $table->string('emergency_phone_num')->nullable()->after('phone_num');
            $table->integer('pm_id')->nullable()->after('emergency_phone_num');
            $table->unsignedBigInteger('team_id')->nullable()->after('name');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn(['profile_pic', 'team_id']);
        });
    }
};
