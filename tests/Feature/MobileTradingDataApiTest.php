<?php

namespace Tests\Feature;

use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\TradingPair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileTradingDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_and_capital_actions_use_authenticated_laravel_records(): void
    {
        $user = User::factory()->create(['role_id' => 750, 'status' => 1]);
        Sanctum::actingAs($user);

        TradingPair::create([
            'symbol' => 'EURUSD',
            'description' => 'Euro / US Dollar',
            'pip_factor' => 0.0001,
            'pip_decimal' => 1,
        ]);
        Capital::create([
            'user_id' => $user->id,
            'type' => 1,
            'amount' => 1000,
            'deposit_date' => '2026-06-01',
        ]);

        $this->postJson('/api/mobile/v1/journals', [
            'status' => 'closed',
            'close_handling' => 'manual_close',
            'time_input_timezone' => 'malaysia',
            'time_input_offset_minutes' => 180,
            'open_date' => '2026-06-18 10:00:00',
            'close_date' => '2026-06-18 11:00:00',
            'pair' => 'EURUSD',
            'direction' => 1,
            'entry_price' => 1.1000,
            'exit_price' => 1.1010,
            'lot_size' => 0.1,
            'result' => 1,
            'notes' => 'Mobile API trade',
        ])->assertCreated()->assertJsonPath('success', true);

        $journal = TradingJournal::where('user_id', $user->id)->sole();
        $this->assertSame(10.0, (float) $journal->pips);
        $this->assertSame(10.0, (float) $journal->profit_loss);

        $this->getJson('/api/mobile/v1/trading-data')
            ->assertOk()
            ->assertJsonPath('data.summary.balance', 1010)
            ->assertJsonCount(1, 'data.journals');

        $otherUser = User::factory()->create();
        $otherJournal = TradingJournal::create([
            'user_id' => $otherUser->id,
            'type' => 'trade',
            'open_date' => now(),
            'close_date' => now()->addHour(),
            'pair' => 'EURUSD',
            'direction' => 1,
            'entry_price' => 1,
            'exit_price' => 1.1,
            'lot_size' => 0.1,
            'pips' => 1,
            'profit_loss' => 1,
            'result' => 1,
        ]);

        $this->deleteJson('/api/mobile/v1/journals/'.$otherJournal->id)
            ->assertNotFound();
        $this->assertDatabaseHas('trading_journals', ['id' => $otherJournal->id]);
    }
}
