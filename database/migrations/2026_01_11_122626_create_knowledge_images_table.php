<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('knowledge_centre_id'); // Related knowledge centre
            $table->unsignedBigInteger('community_id')->nullable(); // Community (nullable if all)
            $table->string('image_path'); // Store uploaded image path
            $table->string('message_id')->nullable(); // Discord message ID
            $table->string('channel_id')->nullable(); // Discord channel ID
            $table->timestamps();

            // Foreign keys
            $table->foreign('knowledge_centre_id')->references('id')->on('knowledge_centres')->onDelete('cascade');
            $table->foreign('community_id')->references('id')->on('communities')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_images');
    }
};
