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
        Schema::create('community_tp_settings', function (Blueprint $table) {
            $table->id();

            // Community linked
            $table->unsignedBigInteger('community_id');

            // TP level (1 - 10)
            $table->unsignedTinyInteger('tp_level');

            // Whether this community should receive this TP notification
            $table->boolean('enabled')->default(1);

            $table->timestamps();

            // Foreign key (if you want)
            $table->foreign('community_id')
                ->references('id')
                ->on('communities')
                ->onDelete('cascade');

            // Prevent duplicated settings e.g. community_id + tp_level
            $table->unique(['community_id', 'tp_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_tp_settings');
    }
};
