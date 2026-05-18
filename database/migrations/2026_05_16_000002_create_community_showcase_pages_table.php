<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_showcase_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('hero_kicker')->nullable();
            $table->string('hero_title');
            $table->string('hero_subtitle')->nullable();
            $table->text('hero_intro')->nullable();
            $table->string('poster_image')->nullable();
            $table->string('primary_cta_label')->nullable();
            $table->string('primary_cta_url')->nullable();
            $table->string('secondary_cta_label')->nullable();
            $table->string('secondary_cta_url')->nullable();
            $table->json('entry_requirements')->nullable();
            $table->json('core_services')->nullable();
            $table->json('secondary_services')->nullable();
            $table->text('service_principle')->nullable();
            $table->text('risk_disclaimer')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_showcase_pages');
    }
};
