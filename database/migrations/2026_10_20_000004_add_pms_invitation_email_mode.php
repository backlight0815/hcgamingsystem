<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pms_invitations', function (Blueprint $table): void {
            // Existing personal invitations retain their original email restriction.
            $table->boolean('email_restricted')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('pms_invitations', fn (Blueprint $table) => $table->dropColumn('email_restricted'));
    }
};
