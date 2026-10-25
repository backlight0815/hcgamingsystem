<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pm_priorities')->where('code', 'P3')->update(['label' => 'Normal']);
    }

    public function down(): void
    {
        DB::table('pm_priorities')->where('code', 'P3')->update(['label' => 'Medium']);
    }
};
