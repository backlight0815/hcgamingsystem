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
        Schema::create('feature_toggles', function (Blueprint $table) {
            $table->id();
            $table->string('feature_name')->unique();  // e.g. 'ewallet_topup'
            $table->boolean('enabled')->default(true); // on/off flag

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_toggles');
    }
};
