<?php

namespace Tests\Feature;

use App\Models\TradingJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TradingJournalSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_after_lost_create_acknowledgement_cannot_discard_a_newer_edit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'lost-ack', 'fields' => $this->fields(),
        ])->assertCreated();
        $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'lost-ack', 'fields' => [...$this->fields(), 'notes' => 'Newer unsent edit'],
        ])->assertStatus(409)->assertJsonPath('data.status', 'conflict');
        $this->assertDatabaseCount('trading_journals', 1);
        $this->assertSame('Original note', TradingJournal::sole()->notes);
    }

    public function test_wall_clock_dates_round_trip_and_zero_can_be_explicitly_cleared(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $record = $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'dates-null', 'fields' => [...$this->fields(), 'pips' => 0],
        ])->assertCreated()->assertJsonPath('data.record.open_date', '2026-10-01T08:00:00')->json('data.record');
        $this->patchJson('/api/mobile/v1/journal-sync/'.$record['sync_id'], [
            'mobile_id' => 'dates-null', 'base_revision' => $record['revision'],
            'fields' => ['open_date' => '2026-10-01T08:00:00.000'],
        ])->assertOk()->assertJsonPath('data.status', 'unchanged');
        $this->patchJson('/api/mobile/v1/journal-sync/'.$record['sync_id'], [
            'mobile_id' => 'dates-null', 'base_revision' => $record['revision'], 'fields' => ['pips' => null],
        ])->assertOk()->assertJsonPath('data.record.pips', null);
        $this->assertNull(TradingJournal::sole()->pips);
    }

    public function test_provider_outage_is_not_reported_as_expired_credentials(): void
    {
        config()->set('trading_signals.firebase_web_api_key', 'test-key');
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response([], 503)]);
        $this->withToken('test-token')->getJson('/api/mobile/v1/journal-sync/changes')
            ->assertStatus(503)->assertJsonPath('error_code', 'AUTH_PROVIDER_UNAVAILABLE');
    }

    public function test_foreign_sync_id_on_create_cannot_create_a_replacement(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'foreign', 'sync_id' => 999999, 'fields' => $this->fields(),
        ])->assertNotFound();
        $this->assertDatabaseCount('trading_journals', 0);
    }

    public function test_mobile_account_metadata_remains_separate_from_website_account(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'account-a', 'fields' => [...$this->fields(), 'mobile_trading_account_id' => 'firestore-account'],
        ])->assertCreated()->assertJsonPath('data.record.mobile_trading_account_id', 'firestore-account');
        $this->assertNull(TradingJournal::sole()->trading_account_id);
    }

    public function test_closed_trade_without_realized_amount_does_not_become_breakeven(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        TradingJournal::create(['user_id' => $user->id, 'type' => 'trade',
            ...array_intersect_key($this->fields(), array_flip((new TradingJournal)->getFillable())),
            'profit_loss' => null]);
        $this->getJson('/api/mobile/v1/journal-sync/statistics')->assertOk()
            ->assertJsonPath('data.total_trades', 1)->assertJsonPath('data.closed_trades', 0)
            ->assertJsonPath('data.breakeven_trades', 0)->assertJsonPath('data.average_trade_profit_loss', null);
    }

    private function fields(string $pair = 'XAUUSD'): array
    {
        return [
            'status' => 'closed', 'open_date' => '2026-10-01 08:00:00',
            'close_date' => '2026-10-01 09:00:00', 'pair' => $pair,
            'direction' => 1, 'entry_price' => '2350.50', 'exit_price' => '2365.00',
            'lot_size' => '1.00', 'pips' => '14.50', 'profit_loss' => '145.00',
            'result' => 1, 'notes' => 'Original note', 'strategy_name' => 'Breakout',
        ];
    }

    public function test_website_records_download_and_owner_isolation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        TradingJournal::create(['user_id' => $user->id, 'type' => 'trade', ...array_intersect_key($this->fields(), array_flip((new TradingJournal)->getFillable()))]);
        TradingJournal::create(['user_id' => $other->id, 'type' => 'trade', ...array_intersect_key($this->fields('EURUSD'), array_flip((new TradingJournal)->getFillable()))]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/v1/journal-sync/changes?cursor=0');
        $response->assertOk()->assertJsonCount(1, 'data.changes')
            ->assertJsonPath('data.changes.0.record.pair', 'XAUUSD');
        $otherSync = \DB::table('trading_journal_sync_records')->where('user_id', $other->id)->value('id');
        $this->getJson('/api/mobile/v1/journal-sync/'.$otherSync)->assertNotFound();
        $this->patchJson('/api/mobile/v1/journal-sync/'.$otherSync, [
            'mobile_id' => 'unauthorized-doc', 'base_revision' => 1,
            'fields' => ['notes' => 'Unauthorized edit'],
        ])->assertNotFound();
        $this->deleteJson('/api/mobile/v1/journal-sync/'.$otherSync, [
            'mobile_id' => 'unauthorized-doc', 'base_revision' => 1,
        ])->assertNotFound();
    }

    public function test_mobile_upload_is_idempotent_and_preserves_omitted_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $operation = ['type' => 'create', 'mobile_id' => 'firestore-a', 'fields' => $this->fields()];
        $first = $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [$operation]])
            ->assertOk()->assertJsonPath('data.results.0.status', 'created');
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [$operation]])
            ->assertOk()->assertJsonPath('data.results.0.status', 'unchanged');
        $this->assertSame(1, TradingJournal::where('user_id', $user->id)->count());
        $record = $first->json('data.results.0.record');
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'update', 'mobile_id' => 'firestore-a', 'sync_id' => $record['sync_id'],
            'base_revision' => $record['revision'], 'fields' => ['entry_price' => '2351.00'],
        ]]])->assertOk()->assertJsonPath('data.results.0.status', 'updated');
        $this->assertSame('Original note', TradingJournal::sole()->notes);
        $current = $this->getJson('/api/mobile/v1/journal-sync/'.$record['sync_id'])->json('data');
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'update', 'mobile_id' => 'firestore-a', 'sync_id' => $record['sync_id'],
            'base_revision' => $current['revision'], 'fields' => ['notes' => null],
        ]]])->assertJsonPath('data.results.0.status', 'updated');
        $this->assertNull(TradingJournal::sole()->notes);
        $this->getJson('/api/mobile/v1/journal-sync/'.$record['sync_id'])
            ->assertJsonPath('data.strategy_name', 'Breakout');
    }

    public function test_matching_legacy_trade_requires_reconciliation_without_creating_a_duplicate(): void
    {
        $user = User::factory()->create();
        $journal = TradingJournal::create(['user_id' => $user->id, 'type' => 'trade',
            ...array_intersect_key($this->fields(), array_flip((new TradingJournal)->getFillable()))]);
        Sanctum::actingAs($user);
        $fields = $this->fields();
        $fields['open_date'] = '2026-10-01T08:00:00.000';
        $fields['notes'] = 'Different mobile note';
        $result = $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'create', 'mobile_id' => 'similar-local-trade', 'fields' => $fields,
        ]]])->assertOk()->assertJsonPath('data.results.0.status', 'reconciliation_required');
        $this->assertSame([$journal->id], TradingJournal::where('user_id', $user->id)->pluck('id')->all());
        $candidate = $result->json('data.results.0.candidate_sync_ids.0');
        $this->assertNotNull($candidate);
        $website = $this->getJson('/api/mobile/v1/journal-sync/'.$candidate)
            ->assertOk()->assertJsonPath('data.website_record_id', $journal->id)->json('data');
        $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'similar-local-trade', 'sync_id' => $candidate,
            'base_revision' => $website['revision'], 'fields' => $fields,
        ])->assertStatus(409);
        $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'similar-local-trade', 'sync_id' => $candidate,
            'base_revision' => $website['revision'], 'confirm_match' => true,
            'fields' => $fields,
        ])->assertOk()->assertJsonPath('data.status', 'linked')
            ->assertJsonPath('data.record.notes', 'Original note');
        $this->assertDatabaseCount('trading_journals', 1);
    }

    public function test_unique_exact_legacy_trade_links_to_existing_website_record(): void
    {
        $user = User::factory()->create();
        $journal = TradingJournal::create(['user_id' => $user->id, 'type' => 'trade',
            ...array_intersect_key($this->fields(), array_flip((new TradingJournal)->getFillable()))]);
        Sanctum::actingAs($user);
        $cursor = $this->getJson('/api/mobile/v1/journal-sync/changes?cursor=0')->json('data.next_cursor');
        $fields = $this->fields();
        $fields['open_date'] = '2026-10-01T08:00:00.000';
        $result = $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'create', 'mobile_id' => 'same-local-trade', 'fields' => $fields,
        ]]])->assertOk()->assertJsonPath('data.results.0.status', 'linked')
            ->assertJsonPath('data.results.0.record.website_record_id', $journal->id)
            ->assertJsonPath('data.results.0.record.strategy_name', 'Breakout');
        $this->getJson('/api/mobile/v1/journal-sync/changes?cursor='.$cursor)
            ->assertJsonPath('data.changes.0.operation', 'update')
            ->assertJsonPath('data.changes.0.record.mobile_id', 'same-local-trade');
        $this->assertDatabaseCount('trading_journals', 1);
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'create', 'mobile_id' => 'same-local-trade', 'fields' => $fields,
        ]]])->assertJsonPath('data.results.0.status', 'unchanged');
        $this->assertSame('same-local-trade', $result->json('data.results.0.record.mobile_id'));
    }

    public function test_concurrent_same_field_edit_conflicts_and_delete_never_resurrects(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $record = $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'firestore-b', 'fields' => $this->fields(),
        ])->assertCreated()->json('data.record');
        TradingJournal::sole()->update(['notes' => 'Website edit']);
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'update', 'mobile_id' => 'firestore-b', 'sync_id' => $record['sync_id'],
            'base_revision' => $record['revision'], 'base' => ['notes' => 'Original note'],
            'fields' => ['notes' => 'Mobile edit'],
        ]]])->assertJsonPath('data.results.0.status', 'conflict');
        $this->assertSame('Website edit', TradingJournal::sole()->notes);
        $current = $this->getJson('/api/mobile/v1/journal-sync/'.$record['sync_id'])->json('data');
        $this->deleteJson('/api/mobile/v1/journal-sync/'.$record['sync_id'], [
            'mobile_id' => 'firestore-b', 'base_revision' => $current['revision'],
        ])->assertJsonPath('data.status', 'deleted');
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'create', 'mobile_id' => 'firestore-b', 'fields' => $this->fields(),
        ]]])->assertJsonPath('data.results.0.status', 'conflict');
        $this->assertDatabaseCount('trading_journals', 0);
    }

    public function test_verified_firebase_identity_maps_to_exactly_one_website_account(): void
    {
        $user = User::factory()->create(['email' => 'Member@Example.test']);
        config()->set('trading_signals.firebase_web_api_key', 'test-key');
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response([
            'users' => [['localId' => 'firebase-uid', 'email' => 'member@example.test', 'emailVerified' => true]],
        ])]);
        $this->withToken('firebase-test-token')->getJson('/api/mobile/v1/journal-sync/changes')
            ->assertOk();
        $this->assertDatabaseHas('trading_journal_identities', ['user_id' => $user->id, 'firebase_uid' => 'firebase-uid']);
    }

    public function test_unverified_firebase_email_cannot_sync(): void
    {
        User::factory()->create(['email' => 'member@example.test']);
        config()->set('trading_signals.firebase_web_api_key', 'test-key');
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response([
            'users' => [['localId' => 'another-uid', 'email' => 'member@example.test', 'emailVerified' => false]],
        ])]);
        $this->withToken('unverified-token')->getJson('/api/mobile/v1/journal-sync/changes')
            ->assertForbidden()->assertJsonPath('error_code', 'EMAIL_VERIFICATION_REQUIRED');
    }

    public function test_missing_token_and_verified_unmapped_account_have_distinct_errors(): void
    {
        $this->getJson('/api/mobile/v1/journal-sync/changes')
            ->assertUnauthorized()->assertJsonPath('error_code', 'UNAUTHENTICATED');
        config()->set('trading_signals.firebase_web_api_key', 'test-key');
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response([
            'users' => [['localId' => 'new-firebase-uid', 'email' => 'unmapped@example.test', 'emailVerified' => true]],
        ])]);
        $this->withToken('valid-unmapped-token')->getJson('/api/mobile/v1/journal-sync/changes')
            ->assertStatus(409)->assertJsonPath('error_code', 'JOURNAL_ACCOUNT_MAPPING_UNAVAILABLE');
    }

    public function test_existing_uid_link_survives_email_change_without_cross_user_access(): void
    {
        $owner = User::factory()->create(['email' => 'old@example.test']);
        $other = User::factory()->create(['email' => 'new@example.test']);
        \DB::table('trading_journal_identities')->insert([
            'user_id' => $owner->id, 'firebase_uid' => 'stable-uid',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        TradingJournal::create(['user_id' => $owner->id, 'type' => 'trade',
            ...array_intersect_key($this->fields(), array_flip((new TradingJournal)->getFillable()))]);
        TradingJournal::create(['user_id' => $other->id, 'type' => 'trade',
            ...array_intersect_key($this->fields('EURUSD'), array_flip((new TradingJournal)->getFillable()))]);
        config()->set('trading_signals.firebase_web_api_key', 'test-key');
        Http::fake(['identitytoolkit.googleapis.com/*' => Http::response([
            'users' => [['localId' => 'stable-uid', 'email' => 'new@example.test', 'emailVerified' => true]],
        ])]);
        $this->withToken('stable-token')->getJson('/api/mobile/v1/journal-sync/changes')
            ->assertOk()->assertJsonCount(1, 'data.changes')
            ->assertJsonPath('data.changes.0.record.pair', 'XAUUSD');
    }

    public function test_mobile_spreadsheet_source_remains_in_manual_journal(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $record = $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'spreadsheet-1',
            'fields' => [...$this->fields(), 'source' => 'excel_import'],
        ])->assertCreated()->assertJsonPath('data.record.source', 'excel_import')
            ->json('data.record');
        $this->getJson('/api/mobile/v1/journal-sync/'.$record['sync_id'])
            ->assertOk()->assertJsonPath('data.source', 'excel_import');
        $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'mt5-1',
            'fields' => [...$this->fields('EURUSD'), 'source' => 'mt5_sync'],
        ])->assertUnprocessable();
    }

    public function test_statistics_exclude_other_users_and_open_trades(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/mobile/v1/journal-sync/statistics')->assertJsonPath('data.total_trades', 0)
            ->assertJsonPath('data.win_rate', null);
        TradingJournal::create(['user_id' => $user->id, 'type' => 'trade', ...array_intersect_key($this->fields(), array_flip((new TradingJournal)->getFillable()))]);
        TradingJournal::create(['user_id' => $other->id, 'type' => 'trade', ...array_intersect_key($this->fields('EURUSD'), array_flip((new TradingJournal)->getFillable()))]);
        $this->getJson('/api/mobile/v1/journal-sync/statistics')
            ->assertJsonPath('data.total_trades', 1)
            ->assertJsonPath('data.winning_trades', 1)
            ->assertJsonPath('data.total_net_profit_loss', '145.00');
    }

    public function test_website_edit_and_delete_emit_incremental_events(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $journal = TradingJournal::create(['user_id' => $user->id, 'type' => 'trade',
            ...array_intersect_key($this->fields(), array_flip((new TradingJournal)->getFillable()))]);
        $first = $this->getJson('/api/mobile/v1/journal-sync/changes?cursor=0')
            ->assertJsonPath('data.changes.0.operation', 'create');
        $cursor = $first->json('data.next_cursor');
        $journal->update(['notes' => 'Website change']);
        $second = $this->getJson('/api/mobile/v1/journal-sync/changes?cursor='.$cursor)
            ->assertJsonPath('data.changes.0.operation', 'update')
            ->assertJsonPath('data.changes.0.record.notes', 'Website change');
        $journal->delete();
        $this->getJson('/api/mobile/v1/journal-sync/changes?cursor='.$second->json('data.next_cursor'))
            ->assertJsonPath('data.changes.0.operation', 'delete')
            ->assertJsonPath('data.changes.0.record.deleted_at', fn ($value) => $value !== null);
    }

    public function test_non_overlapping_concurrent_changes_merge_and_website_only_data_survives(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $record = $this->postJson('/api/mobile/v1/journal-sync', [
            'mobile_id' => 'merge-a', 'fields' => $this->fields(),
        ])->json('data.record');
        TradingJournal::sole()->update(['notes' => 'Website note']);
        \DB::table('trading_journals')->where('user_id', $user->id)->update(['capital' => '500.00']);
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [[
            'type' => 'update', 'mobile_id' => 'merge-a', 'sync_id' => $record['sync_id'],
            'base_revision' => $record['revision'], 'base' => ['entry_price' => $record['entry_price']],
            'fields' => ['entry_price' => '2352.00'],
        ]]])->assertJsonPath('data.results.0.status', 'updated');
        $journal = TradingJournal::sole();
        $this->assertSame('Website note', $journal->notes);
        $this->assertEquals(500, $journal->capital);
    }

    public function test_partial_batch_keeps_valid_result_and_reports_invalid_item(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/mobile/v1/journal-sync/batch', ['operations' => [
            ['type' => 'create', 'mobile_id' => 'valid-a', 'fields' => $this->fields()],
            ['type' => 'create', 'mobile_id' => 'invalid-a', 'fields' => ['pair' => 'XAUUSD']],
        ]])->assertJsonPath('data.results.0.status', 'created')
            ->assertJsonPath('data.results.1.status', 'failed');
        $this->assertDatabaseCount('trading_journals', 1);
    }

    public function test_cursor_pagination_covers_history_without_duplicates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        for ($index = 0; $index < 7; $index++) {
            TradingJournal::create(['user_id' => $user->id, 'type' => 'trade',
                ...array_intersect_key($this->fields('PAIR'.$index), array_flip((new TradingJournal)->getFillable()))]);
        }
        $cursor = 0;
        $ids = [];
        do {
            $page = $this->getJson('/api/mobile/v1/journal-sync/changes?cursor='.$cursor.'&limit=3')->assertOk()->json('data');
            foreach ($page['changes'] as $change) $ids[] = $change['record']['sync_id'];
            $cursor = $page['next_cursor'];
        } while ($page['has_more']);
        $this->assertCount(7, $ids);
        $this->assertCount(7, array_unique($ids));
    }
}
