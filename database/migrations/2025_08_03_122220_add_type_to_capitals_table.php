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
    Schema::table('capitals', function (Blueprint $table) {
        $table->unsignedTinyInteger('type')->default(1)->after('id'); // 1 = Deposit, 2 = Withdraw
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capitals', function (Blueprint $table) {
            //
        });
    }
};
