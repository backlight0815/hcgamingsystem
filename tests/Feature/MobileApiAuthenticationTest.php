<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_login_load_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'username' => 'mobile_trader',
            'email' => 'mobile.trader@gmail.com',
            'password' => Hash::make('Password1'),
            'role_id' => 750,
            'status' => 1,
        ]);

        $login = $this->postJson('/api/mobile/v1/auth/login', [
            'login' => 'mobile_trader',
            'password' => 'Password1',
            'device_name' => 'Feature test',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', (string) $user->id)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $token = $login->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/mobile/v1/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.email', 'mobile.trader@gmail.com');

        $this->postJson('/api/mobile/v1/auth/logout', [], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_login_rejects_suspended_accounts(): void
    {
        User::factory()->create([
            'username' => 'suspended_user',
            'email' => 'suspended@gmail.com',
            'password' => Hash::make('Password1'),
            'status' => 0,
        ]);

        $this->postJson('/api/mobile/v1/auth/login', [
            'login' => 'suspended_user',
            'password' => 'Password1',
        ])->assertForbidden()->assertJsonPath('success', false);
    }
}
