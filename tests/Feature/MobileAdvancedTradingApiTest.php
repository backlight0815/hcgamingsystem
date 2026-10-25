<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TradingAccount;
use App\Models\TradingJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAdvancedTradingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_trader_can_manage_multiple_accounts_while_other_users_are_isolated(): void
    {
        $trader = User::factory()->create(['role_id' => 750, 'status' => 1]);
        Sanctum::actingAs($trader);

        $first = $this->postJson('/api/mobile/v1/trading-accounts', [
            'name' => 'Primary MT5',
            'broker' => 'Example Broker',
            'account_number' => '10001',
            'platform' => 'MT5',
            'account_type' => 'live',
            'currency' => 'USD',
            'starting_balance' => 10000,
            'current_equity' => 10250,
            'leverage' => '1:100',
            'status' => 'active',
            'is_primary' => false,
        ])->assertCreated()->json('data');

        $this->assertTrue((bool) $first['is_primary']);

        $second = $this->postJson('/api/mobile/v1/trading-accounts', [
            'name' => 'Prop challenge',
            'broker' => 'Funding Desk',
            'account_number' => 'PF-22',
            'platform' => 'cTrader',
            'account_type' => 'prop_firm',
            'currency' => 'USD',
            'starting_balance' => 50000,
            'status' => 'active',
            'is_primary' => true,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('trading_accounts', [
            'id' => $first['id'],
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('trading_accounts', [
            'id' => $second['id'],
            'is_primary' => true,
        ]);

        $this->getJson('/api/mobile/v1/trading-accounts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second['id']);

        $other = User::factory()->create(['role_id' => 750, 'status' => 1]);
        $otherAccount = TradingAccount::create([
            'user_id' => $other->id,
            'name' => 'Private account',
            'broker' => 'Other Broker',
            'platform' => 'MT5',
            'account_type' => 'live',
            'currency' => 'USD',
            'starting_balance' => 100,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->deleteJson('/api/mobile/v1/trading-accounts/'.$otherAccount->id)
            ->assertNotFound();
        $this->assertDatabaseHas('trading_accounts', ['id' => $otherAccount->id, 'deleted_at' => null]);
    }

    public function test_trader_can_upload_and_delete_an_owned_journal_attachment(): void
    {
        Storage::fake('public');
        $trader = User::factory()->create(['role_id' => 750, 'status' => 1]);
        Sanctum::actingAs($trader);
        $journal = $this->journalFor($trader);

        $attachment = $this->post('/api/mobile/v1/journals/'.$journal->id.'/attachments', [
            'attachment' => UploadedFile::fake()->create('trade-chart.png', 120, 'image/png'),
        ])->assertCreated()
            ->assertJsonPath('data.original_name', 'trade-chart.png')
            ->json('data');

        Storage::disk('public')->assertExists($attachment['path']);
        $this->assertDatabaseHas('trading_journal_attachments', [
            'id' => $attachment['id'],
            'user_id' => $trader->id,
            'trading_journal_id' => $journal->id,
        ]);

        $this->getJson('/api/mobile/v1/trading-data')
            ->assertOk()
            ->assertJsonPath('data.journals.0.attachments.0.original_name', 'trade-chart.png');

        $this->deleteJson('/api/mobile/v1/journals/'.$journal->id.'/attachments/'.$attachment['id'])
            ->assertOk();
        Storage::disk('public')->assertMissing($attachment['path']);
        $this->assertDatabaseMissing('trading_journal_attachments', ['id' => $attachment['id']]);
    }

    public function test_only_administrators_can_search_and_update_mobile_users(): void
    {
        Role::create(['id' => 1, 'name' => 'Super Admin']);
        Role::create(['id' => 2, 'name' => 'Admin']);
        Role::create(['id' => 750, 'name' => 'Trader']);

        $trader = User::factory()->create(['name' => 'Target Trader', 'role_id' => 750, 'status' => 1]);
        Sanctum::actingAs($trader);
        $this->getJson('/api/mobile/v1/admin/users')->assertForbidden();

        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/mobile/v1/admin/users?search=Target')
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $trader->id)
            ->assertJsonPath('data.pagination.total', 1);

        $this->patchJson('/api/mobile/v1/admin/users/'.$trader->id, [
            'name' => 'Updated Trader',
            'role_id' => 750,
            'status' => 0,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Trader')
            ->assertJsonPath('data.status', '0');

        $this->assertDatabaseHas('users', [
            'id' => $trader->id,
            'name' => 'Updated Trader',
            'status' => 0,
        ]);
    }

    private function journalFor(User $user): TradingJournal
    {
        return TradingJournal::create([
            'user_id' => $user->id,
            'type' => 'trade',
            'status' => 'closed',
            'open_date' => now()->subHour(),
            'close_date' => now(),
            'pair' => 'EURUSD',
            'direction' => 1,
            'entry_price' => 1.1,
            'exit_price' => 1.101,
            'lot_size' => 0.1,
            'pips' => 10,
            'profit_loss' => 10,
            'result' => 1,
        ]);
    }
}
