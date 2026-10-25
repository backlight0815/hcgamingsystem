<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles') || DB::getDriverName() === 'sqlite') {
            return;
        }

        // Change the ID column: drop auto increment, keep as primary key.
        DB::statement('ALTER TABLE roles MODIFY id BIGINT UNSIGNED NOT NULL;');
        DB::statement('ALTER TABLE roles DROP PRIMARY KEY, ADD PRIMARY KEY (id);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
