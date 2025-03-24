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
        Schema::table('clients', function (Blueprint $table) {
            //$table->string('hire_through')->nullable()->after('contact_detail'); // ✅ Add hire_through
            $table->string('hire_on_id')->nullable()->after('hire_through'); // ✅ Add hire_on_id as a string
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            //$table->dropColumn(['hire_through', 'hire_on_id']); // ✅ Remove both columns if rolled back
			$table->dropColumn(['hire_on_id']); // ✅ Remove both columns if rolled back
        });
    }
};
