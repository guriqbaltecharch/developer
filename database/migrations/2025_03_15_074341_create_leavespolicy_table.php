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
        Schema::create('leavespolicy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // ✅ User who applied for leave
            $table->date('start_date'); // ✅ Leave start date
            $table->date('end_date');   // ✅ Leave end date
			$table->string('leave_type');   // ✅ Reason for leave
            $table->string('reason');   // ✅ Reason for leave
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending'); // ✅ Leave status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leavespolicy');
    }
};
