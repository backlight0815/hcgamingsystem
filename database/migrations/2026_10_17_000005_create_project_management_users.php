<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_management_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('legacy_shared_auth')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('pms_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::table('pms_profiles', function (Blueprint $table): void {
            $table->string('workspace_role', 32)->nullable();
            $table->boolean('can_create_all_tickets')->default(false);
        });

        // Only an existing PMS membership proves that a shared account belongs here.
        foreach (DB::table('pms_profiles')->join('users', 'users.id', '=', 'pms_profiles.user_id')
            ->select('pms_profiles.user_id', 'pms_profiles.status', 'users.name', 'users.email', 'users.password', 'users.email_verified_at')
            ->orderBy('pms_profiles.user_id')->get() as $member) {
            DB::table('project_management_users')->insertOrIgnore([
                'user_id' => $member->user_id, 'name' => $member->name,
                'email' => strtolower($member->email), 'password' => $member->password,
                'status' => $member->status, 'legacy_shared_auth' => true,
                'email_verified_at' => $member->email_verified_at,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pms_profiles', function (Blueprint $table): void {
            $table->dropColumn(['workspace_role', 'can_create_all_tickets']);
        });
        Schema::dropIfExists('pms_password_reset_tokens');
        Schema::dropIfExists('project_management_users');
    }
};
