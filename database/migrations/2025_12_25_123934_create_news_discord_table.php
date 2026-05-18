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
        Schema::create('news_discord', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('community_id');
            $table->unsignedBigInteger('news_id');

            // Discord message info
            $table->string('message_id')->nullable();
            $table->string('channel_id')->nullable();

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('community_id')
                ->references('id')
                ->on('communities')
                ->onDelete('cascade');

            $table->foreign('news_id')
                ->references('id')
                ->on('news')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_discord');
    }
};
