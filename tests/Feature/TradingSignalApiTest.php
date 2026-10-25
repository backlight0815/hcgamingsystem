<?php

namespace Tests\Feature;

use App\Events\TradingSignalPublished;
use App\Jobs\SendTradingSignalToDiscord;
use App\Models\TradingSignal;
use App\Models\TradingSignalAudit;
use App\Models\User;
use App\Services\SignalDiscordWebhookService;
use App\Services\TradingSignalAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class TradingSignalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('trading_signals.auto_publish_mobile', false);
        config()->set('trading_signals.discord_enabled', true);
        config()->set('trading_signals.discord_webhook_url', 'https://discord.example.test/webhook');
        config()->set('broadcasting.default', 'null');
    }

    public function test_authorized_mobile_user_submits_pending_signal(): void
    {
        Queue::fake();
        $user = $this->user(201);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/mobile/v1/signals', $this->payload(), [
            'X-Idempotency-Key' => 'mobile-request-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.signal.source', 'MOBILE_APP')
            ->assertJsonPath('data.signal.workflow_status', 'PENDING')
            ->assertJsonPath('data.idempotent_replay', false);

        $this->assertDatabaseHas('trading_signals', [
            'source_user_id' => $user->id,
            'client_request_id' => 'mobile-request-1',
            'workflow_status' => 'PENDING',
            'entry_price_value' => 2650.50000000,
        ]);
        $this->assertDatabaseHas('trading_signal_audits', [
            'action' => 'SIGNAL_RECEIVED_FROM_MOBILE',
            'user_id' => $user->id,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_authorized_website_user_creates_and_publishes_signal(): void
    {
        Queue::fake();
        Event::fake([TradingSignalPublished::class]);
        $user = $this->user(201);

        $this->actingAs($user)->post('/store/trading/signal', [
            'trading_pair' => 'XAUUSD',
            'immediate_action' => 'BUY',
            'entry_price' => '2650.50',
            'stop_loss' => '2635.00',
            'target_1' => '2665.00',
            'target_2' => '2680.00',
            'risk_level' => 'MEDIUM',
        ])->assertRedirect(route('all.trading.signals'));

        $signal = TradingSignal::sole();
        $this->assertSame('WEBSITE', $signal->source);
        $this->assertSame(TradingSignal::WORKFLOW_PUBLISHED, $signal->workflow_status);
        $this->assertSame('pending_entry', $signal->lifecycle_state);
        $this->assertSame('pending_order', $signal->order_type);
        $this->assertSame('2650.50000000', $signal->current_entry_price_value);
        $this->assertNotNull($signal->published_at);
        $this->assertNotNull($signal->signal_uuid);
        $this->assertDatabaseHas('trading_signal_audits', [
            'trading_signal_id' => $signal->id,
            'action' => 'SIGNAL_CREATED',
            'user_id' => $user->id,
        ]);
        Event::assertDispatched(TradingSignalPublished::class);
    }

    public function test_duplicate_mobile_request_is_idempotent(): void
    {
        $user = $this->user(201);
        Sanctum::actingAs($user);
        $headers = ['X-Idempotency-Key' => 'same-request'];

        $this->postJson('/api/mobile/v1/signals', $this->payload(), $headers)->assertCreated();
        $this->postJson('/api/mobile/v1/signals', $this->payload(), $headers)
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertDatabaseCount('trading_signals', 1);
    }

    public function test_single_mobile_target_does_not_expose_the_legacy_placeholder_as_a_second_target(): void
    {
        Sanctum::actingAs($this->user(201));
        $payload = $this->payload();
        unset($payload['take_profit_2']);

        $this->postJson('/api/mobile/v1/signals', $payload, [
            'X-Idempotency-Key' => 'single-target',
        ])->assertCreated()
            ->assertJsonCount(1, 'data.signal.targets')
            ->assertJsonPath('data.signal.target_count', 1);

        $signal = TradingSignal::sole();
        $this->assertSame('2665', $signal->target_2);
        $this->assertSame(1, $signal->target_count);
    }

    public function test_reusing_idempotency_key_for_different_payload_returns_conflict(): void
    {
        Sanctum::actingAs($this->user(201));
        $headers = ['X-Idempotency-Key' => 'conflicting-request'];
        $this->postJson('/api/mobile/v1/signals', $this->payload(), $headers)->assertCreated();

        $changed = $this->payload();
        $changed['symbol'] = 'EURUSD';
        $this->postJson('/api/mobile/v1/signals', $changed, $headers)
            ->assertConflict()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('trading_signals', 1);
    }

    public function test_unauthorized_user_cannot_submit_or_publish_signal(): void
    {
        $trader = $this->user(750);
        Sanctum::actingAs($trader);
        $this->postJson('/api/mobile/v1/signals', $this->payload())->assertForbidden();

        $signal = $this->signal(['workflow_status' => TradingSignal::WORKFLOW_PENDING]);
        $this->postJson("/api/mobile/v1/signals/{$signal->id}/publish")->assertForbidden();
    }

    public function test_invalid_signal_returns_structured_validation_errors(): void
    {
        Sanctum::actingAs($this->user(201));

        $this->postJson('/api/mobile/v1/signals', [
            'symbol' => '<script>',
            'signal_type' => 'INVALID',
            'entry_price' => -1,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['symbol', 'signal_type', 'entry_price', 'stop_loss', 'take_profit_1']]);
    }

    public function test_invalid_pending_signal_update_is_rejected(): void
    {
        $user = $this->user(201);
        Sanctum::actingAs($user);
        $signal = $this->signal([
            'workflow_status' => TradingSignal::WORKFLOW_PENDING,
            'source_user_id' => $user->id,
            'user_id' => $user->id,
        ]);

        $this->putJson("/api/mobile/v1/signals/{$signal->id}", [
            'stop_loss' => '2700.00',
            'expected_version' => 0,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['stop_loss']]);

        $this->assertSame('2635.00', $signal->fresh()->stop_loss);
    }

    public function test_admin_publishes_signal_and_dispatches_realtime_and_discord(): void
    {
        Queue::fake();
        Event::fake([TradingSignalPublished::class]);
        $admin = $this->user(1);
        Sanctum::actingAs($admin);
        $signal = $this->signal(['workflow_status' => TradingSignal::WORKFLOW_PENDING]);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.signal.workflow_status', 'PUBLISHED');

        $this->assertNotNull($signal->fresh()->published_at);
        Event::assertDispatched(TradingSignalPublished::class, fn ($event) => $event->signal->is($signal));
        Queue::assertPushed(SendTradingSignalToDiscord::class, fn ($job) => $job->signalId === $signal->id);
        $this->assertDatabaseHas('trading_signal_audits', [
            'trading_signal_id' => $signal->id,
            'action' => 'SIGNAL_PUBLISHED',
        ]);
    }

    public function test_regular_mobile_user_only_receives_published_signals_and_own_pending_signal(): void
    {
        $trader = $this->user(750);
        $published = $this->signal(['workflow_status' => TradingSignal::WORKFLOW_PUBLISHED]);
        $this->signal(['workflow_status' => TradingSignal::WORKFLOW_PENDING]);
        $ownPending = $this->signal([
            'workflow_status' => TradingSignal::WORKFLOW_PENDING,
            'source_user_id' => $trader->id,
            'user_id' => $trader->id,
        ]);
        Sanctum::actingAs($trader);

        $ids = collect($this->getJson('/api/mobile/v1/signals')->assertOk()->json('data.signals'))
            ->pluck('id')
            ->all();

        $this->assertContains((string) $published->id, $ids);
        $this->assertContains((string) $ownPending->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_signal_remains_stored_when_discord_is_unavailable(): void
    {
        Queue::fake();
        $admin = $this->user(1);
        Sanctum::actingAs($admin);
        $signal = $this->signal(['workflow_status' => TradingSignal::WORKFLOW_PENDING]);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/publish")->assertOk();

        $this->assertDatabaseHas('trading_signals', [
            'id' => $signal->id,
            'workflow_status' => TradingSignal::WORKFLOW_PUBLISHED,
            'discord_delivery_status' => 'PENDING',
        ]);
    }

    public function test_discord_job_records_success_and_has_bounded_retry_policy(): void
    {
        Http::fake([
            'discord.example.test/*' => Http::response(['id' => 'message-1', 'channel_id' => 'channel-1'], 200),
        ]);
        $signal = $this->signal([
            'workflow_status' => TradingSignal::WORKFLOW_PUBLISHED,
            'discord_delivery_status' => 'PENDING',
        ]);
        $job = new SendTradingSignalToDiscord($signal->id);

        $job->handle(app(SignalDiscordWebhookService::class), app(TradingSignalAuditService::class));

        $this->assertSame(3, $job->tries);
        $this->assertSame([15, 60, 180], $job->backoff());
        $this->assertDatabaseHas('trading_signals', [
            'id' => $signal->id,
            'discord_delivery_status' => 'SENT',
            'discord_message_id' => 'message-1',
        ]);
        $this->assertDatabaseHas('trading_signal_audits', [
            'trading_signal_id' => $signal->id,
            'action' => 'DISCORD_SEND_SUCCESS',
        ]);
    }

    public function test_discord_job_failure_never_deletes_signal(): void
    {
        Http::fake(['discord.example.test/*' => Http::response(['message' => 'down'], 503)]);
        $signal = $this->signal([
            'workflow_status' => TradingSignal::WORKFLOW_PUBLISHED,
            'discord_delivery_status' => 'PENDING',
        ]);
        $job = new SendTradingSignalToDiscord($signal->id);

        try {
            $job->handle(app(SignalDiscordWebhookService::class), app(TradingSignalAuditService::class));
            $this->fail('Expected Discord delivery to fail.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        $this->assertDatabaseHas('trading_signals', [
            'id' => $signal->id,
            'workflow_status' => TradingSignal::WORKFLOW_PUBLISHED,
            'discord_delivery_status' => 'FAILED',
        ]);
    }

    public function test_cancelled_and_pending_signals_are_not_reported_as_active(): void
    {
        $admin = $this->user(1);
        Sanctum::actingAs($admin);
        $pending = $this->signal(['workflow_status' => TradingSignal::WORKFLOW_PENDING]);

        $this->postJson("/api/mobile/v1/signals/{$pending->id}/cancel", ['reason' => 'Setup invalidated'])
            ->assertOk()
            ->assertJsonPath('data.signal.workflow_status', 'CANCELLED')
            ->assertJsonPath('data.signal.lifecycle_status', 'cancelled');

        $this->assertDatabaseHas('trading_signals', [
            'id' => $pending->id,
            'status' => 12,
            'workflow_status' => TradingSignal::WORKFLOW_CANCELLED,
        ]);
    }

    public function test_lifecycle_entry_activation_is_atomic_and_idempotent(): void
    {
        $admin = $this->user(1);
        Sanctum::actingAs($admin);
        $signal = $this->signal([
            'lifecycle_state' => 'pending_entry',
            'order_type' => 'buy_limit',
            'version' => 0,
        ]);
        $payload = [
            'action' => 'entry_triggered',
            'idempotency_key' => 'lifecycle-entry-1',
            'expected_version' => 0,
            'entry_price' => '2651.25',
        ];

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', false)
            ->assertJsonPath('data.signal.lifecycle_status', 'active')
            ->assertJsonPath('data.signal.current_entry_price', '2651.25000000')
            ->assertJsonPath('data.signal.version', 1);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.signal.version', 1);

        $this->assertDatabaseCount('trading_signal_audits', 1);
        $this->assertNotNull($signal->fresh()->activated_at);
    }

    public function test_partial_close_updates_authoritative_remaining_position(): void
    {
        Sanctum::actingAs($this->user(1));
        $signal = $this->signal([
            'lifecycle_state' => 'active',
            'percentage_remaining' => 100,
        ]);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'partial_close',
            'idempotency_key' => 'partial-close-1',
            'expected_version' => 0,
            'reason' => 'Reduce exposure before news',
            'percentage_closed' => 25,
        ])->assertOk()
            ->assertJsonPath('data.signal.lifecycle_status', 'partially_closed')
            ->assertJsonPath('data.signal.percentage_remaining', 75);

        $this->assertDatabaseHas('trading_signals', [
            'id' => $signal->id,
            'lifecycle_state' => 'partially_closed',
            'percentage_remaining' => 75,
            'version' => 1,
        ]);
    }

    public function test_target_hits_must_be_sequential_and_are_projected_to_mobile(): void
    {
        Sanctum::actingAs($this->user(1));
        $signal = $this->signal([
            'lifecycle_state' => 'active',
            'target_3' => '2695.00',
            'target_3_value' => '2695.00',
            'targets_hit' => [],
        ]);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'target_hit',
            'idempotency_key' => 'target-out-of-order',
            'expected_version' => 0,
            'target_number' => 2,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['target_number']]);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'target_hit',
            'idempotency_key' => 'target-one',
            'expected_version' => 0,
            'target_number' => 1,
        ])->assertOk()
            ->assertJsonPath('data.signal.lifecycle_status', 'partially_closed')
            ->assertJsonPath('data.signal.targets_hit.0', 1)
            ->assertJsonPath('data.signal.current_target_index', 1);
    }

    public function test_commentary_is_audited_without_changing_lifecycle_state(): void
    {
        $admin = $this->user(1);
        Sanctum::actingAs($admin);
        $signal = $this->signal(['lifecycle_state' => 'active']);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'commentary',
            'idempotency_key' => 'commentary-1',
            'expected_version' => 0,
            'primary_language' => 'en',
            'title_i18n' => ['en' => 'Trade management update'],
            'message_i18n' => ['en' => 'Price is consolidating above support.'],
        ])->assertOk()
            ->assertJsonPath('data.signal.lifecycle_status', 'active')
            ->assertJsonPath('data.signal.last_update_type', 'commentary');

        $audit = TradingSignalAudit::where('trading_signal_id', $signal->id)->sole();
        $this->assertSame('active', $audit->metadata['lifecycle_from']);
        $this->assertSame('active', $audit->metadata['lifecycle_to']);
        $this->assertSame('Price is consolidating above support.', $audit->metadata['message_i18n']['en']);
    }

    public function test_stop_loss_update_cannot_increase_risk_without_manager_correction(): void
    {
        $owner = $this->user(201);
        Sanctum::actingAs($owner);
        $signal = $this->signal([
            'lifecycle_state' => 'active',
            'user_id' => $owner->id,
            'source_user_id' => $owner->id,
            'current_stop_loss_value' => '2635.00',
        ]);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'update_stop_loss',
            'idempotency_key' => 'risk-increase-1',
            'expected_version' => 0,
            'reason' => 'Give the trade more room',
            'stop_loss' => '2625.00',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['stop_loss']]);

        $this->assertSame('2635.00000000', $signal->fresh()->current_stop_loss_value);
        $this->assertSame(0, $signal->fresh()->version);
    }

    public function test_only_managers_can_archive_restore_or_correct_lifecycle(): void
    {
        $owner = $this->user(201);
        $signal = $this->signal([
            'lifecycle_state' => 'completed',
            'workflow_status' => TradingSignal::WORKFLOW_CLOSED,
            'user_id' => $owner->id,
            'source_user_id' => $owner->id,
            'is_done' => 1,
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'archived',
            'idempotency_key' => 'archive-forbidden',
            'expected_version' => 0,
        ])->assertForbidden();

        Sanctum::actingAs($this->user(1));
        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'archived',
            'idempotency_key' => 'archive-1',
            'expected_version' => 0,
        ])->assertOk()
            ->assertJsonPath('data.signal.lifecycle_status', 'archived')
            ->assertJsonPath('data.signal.archived', true);

        $this->postJson("/api/mobile/v1/signals/{$signal->id}/lifecycle", [
            'action' => 'restored',
            'idempotency_key' => 'restore-1',
            'expected_version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.signal.lifecycle_status', 'completed')
            ->assertJsonPath('data.signal.archived', false);
    }

    public function test_legacy_website_status_changes_update_mobile_lifecycle_projection(): void
    {
        $admin = $this->user(1);
        $this->actingAs($admin);
        $signal = $this->signal([
            'status' => 0,
            'lifecycle_state' => 'pending_entry',
            'version' => 0,
            'targets_hit' => [],
        ]);

        $signal->update(['status' => 1]);
        $signal->refresh();
        $this->assertSame('active', $signal->lifecycle_state);
        $this->assertSame('entry_triggered', $signal->last_update_type);
        $this->assertSame(1, $signal->version);
        $this->assertNotNull($signal->activated_at);

        $signal->update(['status' => 2]);
        $signal->refresh();
        $this->assertSame('partially_closed', $signal->lifecycle_state);
        $this->assertSame([1], $signal->targets_hit);
        $this->assertSame(1, $signal->current_target_index);
        $this->assertSame(2, $signal->version);
    }

    public function test_missing_or_expired_authentication_returns_401(): void
    {
        config()->set('trading_signals.firebase_web_api_key', null);

        $this->getJson('/api/mobile/v1/signals', [
            'Authorization' => 'Bearer expired-token',
        ])->assertUnauthorized()
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_firebase_identity_token_authenticates_matching_laravel_user(): void
    {
        config()->set('trading_signals.firebase_web_api_key', 'firebase-web-key');
        $user = User::factory()->create([
            'email' => 'signal.trader@example.test',
            'role_id' => 201,
            'status' => 1,
        ]);
        $this->signal([
            'source_user_id' => $user->id,
            'user_id' => $user->id,
        ]);
        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response([
                'users' => [[
                    'localId' => 'firebase-user-42',
                    'email' => 'signal.trader@example.test',
                ]],
            ]),
        ]);

        $this->getJson('/api/mobile/v1/signals', [
            'Authorization' => 'Bearer valid-firebase-token',
        ])->assertOk()
            ->assertJsonPath('data.signals.0.provider_id', 'firebase-user-42');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'accounts:lookup'));
    }

    public function test_signal_write_rate_limit_is_enforced(): void
    {
        $user = $this->user(201);
        Sanctum::actingAs($user);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/mobile/v1/signals', $this->payload(), [
                'X-Idempotency-Key' => 'rate-'.$attempt,
            ])->assertCreated();
        }

        $this->postJson('/api/mobile/v1/signals', $this->payload(), [
            'X-Idempotency-Key' => 'rate-11',
        ])->assertTooManyRequests();
    }

    private function user(int $roleId): User
    {
        return User::factory()->create(['role_id' => $roleId, 'status' => 1]);
    }

    private function payload(): array
    {
        return [
            'symbol' => 'XAUUSD',
            'market' => 'FOREX',
            'signal_type' => 'BUY',
            'entry_price' => '2650.50',
            'stop_loss' => '2635.00',
            'take_profit_1' => '2665.00',
            'take_profit_2' => '2680.00',
            'timeframe' => 'H1',
            'risk_level' => 'MEDIUM',
            'strategy' => 'Breakout',
            'description' => 'Potential breakout setup',
        ];
    }

    private function signal(array $overrides = []): TradingSignal
    {
        return TradingSignal::create(array_merge([
            'signal_uuid' => 'SIG-'.Str::upper(Str::random(12)),
            'signal_code' => Str::upper(Str::random(6)),
            'signal_title' => 'Breakout',
            'trading_pair' => 'XAUUSD',
            'market' => 'FOREX',
            'immediate_action' => 'BUY',
            'entry_price' => '2650.50',
            'entry_price_value' => '2650.50',
            'stop_loss' => '2635.00',
            'stop_loss_value' => '2635.00',
            'target_1' => '2665.00',
            'target_1_value' => '2665.00',
            'target_2' => '2680.00',
            'target_2_value' => '2680.00',
            'risk_level' => 'MEDIUM',
            'source' => 'WEBSITE',
            'workflow_status' => TradingSignal::WORKFLOW_PUBLISHED,
            'published_at' => now(),
            'status' => 0,
        ], $overrides));
    }
}
