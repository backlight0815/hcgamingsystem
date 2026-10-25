<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_defect_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        DB::table('pm_defect_types')->insert(collect([
            'Functional bug', 'UI/UX issue', 'API issue', 'Backend issue', 'Integration issue',
            'Database issue', 'Performance issue', 'Security issue', 'Compatibility issue',
            'Regression issue', 'Environment issue', 'Other testing issue',
        ])->map(fn ($name) => ['name' => $name, 'active' => true, 'created_at' => now(), 'updated_at' => now()])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_defect_types');
    }
};
