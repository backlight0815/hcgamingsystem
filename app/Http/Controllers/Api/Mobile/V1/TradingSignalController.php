<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Events\TradingSignalPublished;
use App\Http\Requests\Api\StoreTradingSignalRequest;
use App\Http\Requests\Api\UpdateTradingSignalRequest;
use App\Http\Resources\TradingSignalResource;
use App\Jobs\SendTradingSignalPushNotification;
use App\Jobs\SendTradingSignalToDiscord;
use App\Models\TradingSignal;
use App\Models\TradingSignalAudit;
use App\Services\AppNotificationService;
use App\Services\TradingSignalAuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradingSignalController extends MobileController
{
    private const LIFECYCLE_ACTIONS = [
        'published', 'details_updated', 'entry_triggered', 'entry_price_updated',
        'entry_range_updated', 'update_stop_loss', 'move_sl_to_be',
        'update_break_even', 'trailing_break_even', 'trailing_stop_loss',
        'lock_profit', 'reduce_risk', 'remove_pending_order', 'partial_close',
        'partial_profit_taken', 'target_hit', 'stop_loss_hit', 'break_even_hit',
        'manually_closed', 'closed_in_profit', 'closed_in_loss', 'cancelled',
        'expired', 'invalidated', 'completed', 'commentary',
        'market_commentary', 'warning', 'chart', 'analysis', 'archived',
        'restored', 'correction',
    ];

    private const COMMENTARY_ACTIONS = [
        'commentary', 'market_commentary', 'warning', 'chart', 'analysis',
    ];

    private const TERMINAL_LIFECYCLE_STATES = [
        'completed', 'stop_loss_hit', 'break_even_hit', 'cancelled', 'expired',
    ];

    public function __construct(private readonly TradingSignalAuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TradingSignal::class);

        $perPage = min(max((int) $request->integer('per_page', 30), 1), 100);
        $query = TradingSignal::query()->with(['user:id,name,username', 'sourceUser:id,name,username']);

        if (! $request->user()->can('manage', TradingSignal::class)) {
            $userId = (int) $request->user()->id;
            $query->where(function ($query) use ($userId): void {
                $query->published()
                    ->orWhere('source_user_id', $userId)
                    ->orWhere('user_id', $userId);
            });
        }

        if ($request->filled('status')) {
            $query->where('workflow_status', strtoupper((string) $request->input('status')));
        }
        if ($request->filled('source')) {
            $query->where('source', strtoupper((string) $request->input('source')));
        }
        if ($request->filled('symbol')) {
            $query->where('trading_pair', strtoupper((string) $request->input('symbol')));
        }
        if ($request->filled('since')) {
            $request->validate(['since' => ['date']]);
            $query->where('updated_at', '>', $request->date('since'));
        }

        $paginator = $query
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->success([
            'signals' => TradingSignalResource::collection($paginator->items())->resolve($request),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'synced_at' => now()->toIso8601String(),
        ], 'Trading signals loaded.');
    }

    public function show(Request $request, TradingSignal $signal): JsonResponse
    {
        Gate::authorize('view', $signal);
        $signal->load(['user:id,name,username', 'sourceUser:id,name,username']);

        return $this->success([
            'signal' => (new TradingSignalResource($signal))->resolve($request),
        ], 'Trading signal loaded.');
    }

    public function store(StoreTradingSignalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $requestId = trim((string) ($data['client_request_id'] ?? ''));

        if ($requestId !== '') {
            $existing = TradingSignal::where('source_user_id', $request->user()->id)
                ->where('client_request_id', $requestId)
                ->first();
            if ($existing) {
                if (! $this->sameSubmission($existing, $data)) {
                    return $this->failure(
                        'This idempotency key was already used for a different signal.',
                        [],
                        409
                    );
                }

                return $this->success([
                    'signal' => (new TradingSignalResource($existing))->resolve($request),
                    'idempotent_replay' => true,
                ], 'Signal submission already received.');
            }
        }

        $autoPublish = (bool) config('trading_signals.auto_publish_mobile')
            && $request->user()->can('publish', TradingSignal::class);
        $publishedLifecycle = ($data['order_type'] ?? 'market_execution') === 'market_execution'
            ? 'active'
            : 'pending_entry';
        try {
            $signal = DB::transaction(function () use ($request, $data, $requestId, $autoPublish, $publishedLifecycle): TradingSignal {
                $signal = TradingSignal::create($this->attributesFromApi($data) + [
                    'target_1' => '',
                    'target_2' => '',
                    'signal_code' => 'MOB-'.strtoupper(Str::random(8)),
                    'source' => 'MOBILE_APP',
                    'source_user_id' => $request->user()->id,
                    'user_id' => $request->user()->id,
                    'client_request_id' => $requestId !== '' ? $requestId : null,
                    'workflow_status' => $autoPublish
                        ? TradingSignal::WORKFLOW_PUBLISHED
                        : TradingSignal::WORKFLOW_PENDING,
                    'published_at' => $autoPublish ? now() : null,
                    'lifecycle_state' => $autoPublish ? $publishedLifecycle : 'draft',
                    'activated_at' => $autoPublish && $publishedLifecycle === 'active' ? now() : null,
                    'targets_hit' => [],
                    'target_count' => $this->inputTargetCount($data),
                    'percentage_remaining' => 100,
                    'last_update_type' => $autoPublish ? 'published' : 'submitted',
                    'last_updated_by' => (string) $request->user()->id,
                    'discord_delivery_status' => $autoPublish && $this->discordConfigured()
                        ? 'PENDING'
                        : 'NOT_REQUESTED',
                    'status' => $autoPublish && $publishedLifecycle === 'active' ? 1 : 0,
                    'version' => 0,
                ]);

                $signal->forceFill([
                    'signal_uuid' => sprintf('SIG-%s-%d', now()->format('Ymd'), $signal->id),
                ])->saveQuietly();

                $this->audit->record(
                    $signal,
                    'SIGNAL_RECEIVED_FROM_MOBILE',
                    $request->user(),
                    ['workflow_status' => $signal->workflow_status],
                    $requestId !== '' ? $requestId : null
                );

                return $signal;
            });
        } catch (QueryException $exception) {
            if ($requestId === '' || (string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $signal = TradingSignal::where('source_user_id', $request->user()->id)
                ->where('client_request_id', $requestId)
                ->first();
            if (! $signal) {
                throw $exception;
            }
            if (! $this->sameSubmission($signal, $data)) {
                return $this->failure('This idempotency key was already used for a different signal.', [], 409);
            }

            return $this->success([
                'signal' => (new TradingSignalResource($signal))->resolve($request),
                'idempotent_replay' => true,
            ], 'Signal submission already received.');
        }

        if ($autoPublish) {
            $this->afterPublished($signal, $request->user());
        } else {
            try {
                AppNotificationService::notifyAdmins(
                    'Mobile trading signal submitted',
                    $signal->trading_pair.' '.strtoupper($signal->immediate_action).' is awaiting approval.',
                    route('view.trading.signal', $signal->id),
                    'trading_signal',
                    $request->user()->id
                );
            } catch (\Throwable $exception) {
                Log::warning('Mobile signal admin notification failed.', [
                    'signal_id' => $signal->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        Log::info('Trading signal received through mobile API.', [
            'signal_id' => $signal->id,
            'user_id' => $request->user()->id,
            'workflow_status' => $signal->workflow_status,
        ]);

        return $this->success([
            'signal' => (new TradingSignalResource($signal->fresh()))->resolve($request),
            'idempotent_replay' => false,
        ], $autoPublish ? 'Signal published successfully.' : 'Signal submitted for approval.', 201);
    }

    public function update(UpdateTradingSignalRequest $request, TradingSignal $signal): JsonResponse
    {
        $data = $request->validated();
        $result = DB::transaction(function () use ($signal, $data, $request): array {
            $locked = TradingSignal::query()->lockForUpdate()->findOrFail($signal->id);
            if (isset($data['expected_version']) && (int) $data['expected_version'] !== (int) $locked->version) {
                return ['conflict' => true];
            }

            $locked->fill($this->attributesFromApi($data));
            foreach (range(1, 10) as $target) {
                if (filled($data["take_profit_{$target}"] ?? null)) {
                    $locked->target_count = max((int) $locked->target_count, $target);
                }
            }
            $locked->version = (int) $locked->version + 1;
            $locked->last_update_type = 'details_updated';
            $locked->last_updated_by = (string) $request->user()->id;
            $locked->saveQuietly();
            $this->audit->record($locked, 'SIGNAL_UPDATED', $request->user(), [
                'fields' => array_keys($data),
                'version' => $locked->version,
            ]);

            return ['signal' => $locked];
        }, 3);

        if (isset($result['conflict'])) {
            return $this->failure('The signal was changed by another user. Refresh and retry.', [], 409);
        }

        return $this->success([
            'signal' => (new TradingSignalResource($result['signal']->fresh()))->resolve($request),
        ], 'Signal updated successfully.');
    }

    public function publish(Request $request, TradingSignal $signal): JsonResponse
    {
        Gate::authorize('publish', $signal);
        $result = DB::transaction(function () use ($signal, $request): array {
            $locked = TradingSignal::query()->lockForUpdate()->findOrFail($signal->id);
            if ($locked->workflow_status === TradingSignal::WORKFLOW_PUBLISHED) {
                return ['signal' => $locked, 'published' => false];
            }
            if (! in_array($locked->workflow_status, [TradingSignal::WORKFLOW_DRAFT, TradingSignal::WORKFLOW_PENDING], true)) {
                return ['error' => 'This signal cannot be published from its current status.'];
            }

            $lifecycle = $this->publishedLifecycleState($locked);
            $locked->forceFill([
                'workflow_status' => TradingSignal::WORKFLOW_PUBLISHED,
                'published_at' => now(),
                'lifecycle_state' => $lifecycle,
                'status' => $lifecycle === 'active' ? 1 : 0,
                'activated_at' => $lifecycle === 'active'
                    ? ($locked->activated_at ?? now())
                    : $locked->activated_at,
                'last_update_type' => 'published',
                'last_updated_by' => (string) $request->user()->id,
                'discord_delivery_status' => $this->discordConfigured() ? 'PENDING' : 'NOT_REQUESTED',
                'version' => (int) $locked->version + 1,
            ])->saveQuietly();

            return ['signal' => $locked, 'published' => true];
        }, 3);

        if (isset($result['error'])) {
            return $this->failure($result['error'], [], 409);
        }

        $signal = $result['signal'];
        if ($result['published']) {
            $this->afterPublished($signal, $request->user());
        }

        return $this->success([
            'signal' => (new TradingSignalResource($signal->fresh()))->resolve($request),
        ], $result['published'] ? 'Signal published successfully.' : 'Signal is already published.');
    }

    public function cancel(Request $request, TradingSignal $signal): JsonResponse
    {
        Gate::authorize('cancel', $signal);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        if (in_array($signal->workflow_status, [TradingSignal::WORKFLOW_CANCELLED, TradingSignal::WORKFLOW_CLOSED], true)) {
            return $this->failure('This signal is already in a terminal state.', [], 409);
        }

        $signal->forceFill([
            'workflow_status' => TradingSignal::WORKFLOW_CANCELLED,
            'lifecycle_state' => 'cancelled',
            'status' => 12,
            'cancel_reason' => $data['reason'] ?? null,
            'cancelled_at' => now(),
            'last_update_type' => 'cancelled',
            'last_updated_by' => (string) $request->user()->id,
            'version' => (int) $signal->version + 1,
        ])->saveQuietly();
        $this->audit->record($signal, 'SIGNAL_CANCELLED', $request->user(), ['reason' => $data['reason'] ?? null]);

        return $this->success([
            'signal' => (new TradingSignalResource($signal->fresh()))->resolve($request),
        ], 'Signal cancelled successfully.');
    }

    public function close(Request $request, TradingSignal $signal): JsonResponse
    {
        Gate::authorize('close', $signal);
        if ($signal->workflow_status !== TradingSignal::WORKFLOW_PUBLISHED) {
            return $this->failure('Only published signals can be closed.', [], 409);
        }

        $signal->forceFill([
            'workflow_status' => TradingSignal::WORKFLOW_CLOSED,
            'lifecycle_state' => 'completed',
            'status' => 14,
            'IsDone' => 1,
            'is_done' => 1,
            'closed_at' => now(),
            'completed_at' => now(),
            'percentage_remaining' => 0,
            'last_update_type' => 'completed',
            'last_updated_by' => (string) $request->user()->id,
            'version' => (int) $signal->version + 1,
        ])->saveQuietly();
        $this->audit->record($signal, 'SIGNAL_CLOSED', $request->user());

        return $this->success([
            'signal' => (new TradingSignalResource($signal->fresh()))->resolve($request),
        ], 'Signal closed successfully.');
    }

    public function lifecycle(Request $request, TradingSignal $signal): JsonResponse
    {
        Gate::authorize('close', $signal);
        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(self::LIFECYCLE_ACTIONS)],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:500'],
            'message' => ['nullable', 'string', 'max:4000'],
            'title_i18n' => ['nullable', 'array', 'max:5'],
            'title_i18n.*' => ['nullable', 'string', 'max:500'],
            'message_i18n' => ['nullable', 'array', 'max:5'],
            'message_i18n.*' => ['nullable', 'string', 'max:4000'],
            'primary_language' => ['nullable', 'string', 'max:10'],
            'entry_price' => ['nullable', 'numeric', 'gt:0'],
            'entry_price_min' => ['nullable', 'numeric', 'gt:0'],
            'entry_price_max' => ['nullable', 'numeric', 'gt:0'],
            'stop_loss' => ['nullable', 'numeric', 'gt:0'],
            'break_even_price' => ['nullable', 'numeric', 'gt:0'],
            'target_number' => ['nullable', 'integer', 'between:1,10'],
            'target_price' => ['nullable', 'numeric', 'gt:0'],
            'percentage_closed' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'percentage_remaining' => ['nullable', 'numeric', 'between:0,100'],
            'realised_pips' => ['nullable', 'numeric'],
            'realised_percentage' => ['nullable', 'numeric'],
            'profit_lock_offset' => ['nullable', 'numeric', 'gt:0'],
            'entry_was_triggered' => ['nullable', 'boolean'],
            'attachment_urls' => ['nullable', 'array', 'max:10'],
            'attachment_urls.*' => ['url:http,https', 'max:2048'],
            'effective_at' => ['nullable', 'date'],
            'notification_requested' => ['nullable', 'boolean'],
            'is_correction' => ['nullable', 'boolean'],
            'corrected_update_id' => ['nullable', 'string', 'max:120'],
            'lifecycle_override' => ['nullable', Rule::in([
                'draft', 'scheduled', 'published', 'pending_entry', 'active',
                'partially_closed', 'break_even', 'trailing', 'completed',
                'stop_loss_hit', 'break_even_hit', 'cancelled', 'expired', 'archived',
            ])],
            'timeframe' => ['nullable', 'string', 'max:30'],
            'risk_level' => ['nullable', 'string', 'max:20'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $action = strtolower($data['action']);
        $canManage = $request->user()->can('manage', TradingSignal::class);
        $result = DB::transaction(function () use ($signal, $data, $action, $request, $canManage): array {
            $locked = TradingSignal::query()->lockForUpdate()->findOrFail($signal->id);
            $existing = TradingSignalAudit::where('trading_signal_id', $locked->id)
                ->where('request_id', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return ['signal' => $locked, 'replay' => true, 'published' => false];
            }
            if (isset($data['expected_version']) && (int) $data['expected_version'] !== (int) $locked->version) {
                return [
                    'error' => 'The signal was changed by another user. Refresh and retry.',
                    'errors' => ['expected_version' => ['The expected version does not match the current signal version.']],
                    'status' => 409,
                ];
            }

            $validationError = $this->lifecycleValidationError($locked, $action, $data, $canManage);
            if ($validationError !== null) {
                return $validationError;
            }

            $from = $locked->mobile_lifecycle_status;
            $changes = $this->lifecycleChanges($locked, $action, $data, $request->user()->id);
            $wasPublished = $locked->workflow_status === TradingSignal::WORKFLOW_PUBLISHED;
            $locked->forceFill($changes + ['version' => (int) $locked->version + 1])->saveQuietly();

            TradingSignalAudit::create([
                'trading_signal_id' => $locked->id,
                'user_id' => $request->user()->id,
                'action' => 'SIGNAL_'.strtoupper($action),
                'source' => $locked->source,
                'request_id' => $data['idempotency_key'],
                'metadata' => $this->lifecycleAuditMetadata(
                    $data,
                    $from,
                    $locked->mobile_lifecycle_status,
                    (int) $locked->version
                ),
            ]);

            return [
                'signal' => $locked,
                'replay' => false,
                'published' => ! $wasPublished && $locked->workflow_status === TradingSignal::WORKFLOW_PUBLISHED,
            ];
        }, 3);

        if (isset($result['error'])) {
            return $this->failure($result['error'], $result['errors'] ?? [], $result['status'] ?? 422);
        }

        $signal = $result['signal'];

        if ($result['published']) {
            $this->afterPublished($signal, $request->user());
        }

        return $this->success([
            'signal' => (new TradingSignalResource($signal->fresh()))->resolve($request),
            'idempotent_replay' => $result['replay'],
        ], $result['replay'] ? 'Signal update already processed.' : 'Signal lifecycle updated.');
    }

    public function stream(Request $request): StreamedResponse
    {
        abort_unless(config('trading_signals.realtime_enabled'), 503, 'Realtime signal updates are disabled.');
        Gate::authorize('viewAny', TradingSignal::class);

        return response()->stream(function (): void {
            $lastVersion = '';
            echo "retry: 3000\n";
            echo "event: ready\n";
            echo 'data: '.json_encode(['event' => 'ready', 'server_time' => now()->toIso8601String()])."\n\n";
            @ob_flush();
            flush();

            for ($second = 0; $second < 25 && ! connection_aborted(); $second++) {
                $latest = TradingSignal::published()->latest('updated_at')->latest('id')->first([
                    'id', 'signal_uuid', 'published_at', 'updated_at',
                ]);
                $version = $latest ? $latest->id.':'.$latest->updated_at?->format('U.u') : 'none';
                if ($lastVersion !== '' && $version !== $lastVersion && $latest) {
                    echo "event: trading.signal.published\n";
                    echo 'data: '.json_encode([
                        'event' => 'trading.signal.published',
                        'signal_id' => $latest->id,
                        'signal_uuid' => $latest->signal_uuid,
                        'published_at' => $latest->published_at?->toIso8601String(),
                    ])."\n\n";
                } elseif ($second > 0 && $second % 10 === 0) {
                    echo ': keep-alive '.now()->timestamp."\n\n";
                }
                $lastVersion = $version;
                @ob_flush();
                flush();
                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function attributesFromApi(array $data): array
    {
        $attributes = [];
        $map = [
            'symbol' => 'trading_pair',
            'market' => 'market',
            'signal_type' => 'immediate_action',
            'order_type' => 'order_type',
            'entry_price_min' => 'entry_price_min',
            'entry_price_max' => 'entry_price_max',
            'risk_level' => 'risk_level',
            'timeframe' => 'timeframe',
            'strategy' => 'strategy',
            'description' => 'description',
            'notes' => 'notes',
            'link' => 'link',
            'chart_image_url' => 'signal_image',
            'expires_at' => 'expires_at',
        ];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }

        if (array_key_exists('entry_price', $data)) {
            $attributes['entry_price'] = $this->decimalString($data['entry_price']);
            $attributes['entry_price_value'] = $data['entry_price'];
            $attributes['current_entry_price_value'] = $data['entry_price'];
        }
        if (array_key_exists('stop_loss', $data)) {
            $attributes['stop_loss'] = $this->decimalString($data['stop_loss']);
            $attributes['stop_loss_value'] = $data['stop_loss'];
            $attributes['current_stop_loss_value'] = $data['stop_loss'];
        }
        for ($target = 1; $target <= 10; $target++) {
            $input = "take_profit_{$target}";
            if (array_key_exists($input, $data)) {
                $attributes["target_{$target}"] = $data[$input] === null ? null : $this->decimalString($data[$input]);
                $attributes["target_{$target}_value"] = $data[$input];
            }
        }
        if (array_key_exists('take_profit_1', $data) && ! array_key_exists('take_profit_2', $data)) {
            // The legacy schema requires target_2. Keep compatibility while the API exposes only supplied targets.
            $attributes['target_2'] = $attributes['target_1'];
            $attributes['target_2_value'] = $attributes['target_1_value'];
        }

        if (isset($attributes['strategy']) || isset($attributes['trading_pair'])) {
            $attributes['signal_title'] = $attributes['strategy']
                ?? trim(($attributes['trading_pair'] ?? '').' '.($attributes['immediate_action'] ?? ''));
        }
        if (array_key_exists('description', $attributes)) {
            $attributes['disclaimer'] = $attributes['description'];
        }

        return $attributes;
    }

    private function sameSubmission(TradingSignal $signal, array $data): bool
    {
        $incoming = $this->attributesFromApi($data);
        foreach ([
            'trading_pair', 'market', 'immediate_action', 'order_type', 'entry_price',
            'stop_loss', 'risk_level', 'timeframe', 'strategy', 'description',
            'notes', 'link', 'signal_image',
        ] as $field) {
            if (array_key_exists($field, $incoming) && (string) $signal->{$field} !== (string) $incoming[$field]) {
                return false;
            }
        }
        foreach (['entry_price_min', 'entry_price_max'] as $field) {
            if (array_key_exists($field, $incoming)
                && $this->decimalString($signal->{$field}) !== $this->decimalString($incoming[$field])) {
                return false;
            }
        }
        for ($target = 1; $target <= 10; $target++) {
            $field = "target_{$target}";
            if (array_key_exists($field, $incoming) && (string) $signal->{$field} !== (string) $incoming[$field]) {
                return false;
            }
        }

        return true;
    }

    private function lifecycleValidationError(
        TradingSignal $signal,
        string $action,
        array $data,
        bool $canManage
    ): ?array {
        $invalid = static fn (string $message, string $field = 'action', int $status = 422): array => [
            'error' => $message,
            'errors' => [$field => [$message]],
            'status' => $status,
        ];
        $state = $signal->mobile_lifecycle_status;

        if (in_array($action, ['correction', 'archived', 'restored'], true) && ! $canManage) {
            return $invalid('Only a signal manager can perform this action.', 'action', 403);
        }
        if (! in_array($action, $this->allowedLifecycleActions($state, $canManage), true)) {
            return $invalid("The {$action} action is unavailable while the signal is {$state}.", 'action', 409);
        }
        if (($data['is_correction'] ?? false) && ! $canManage) {
            return $invalid('Only a signal manager can submit a correction.', 'is_correction', 403);
        }

        $requiresReason = [
            'update_stop_loss', 'move_sl_to_be', 'update_break_even',
            'trailing_break_even', 'trailing_stop_loss', 'lock_profit',
            'reduce_risk', 'remove_pending_order', 'partial_close',
            'partial_profit_taken', 'cancelled', 'invalidated',
            'manually_closed', 'closed_in_profit', 'closed_in_loss', 'correction',
        ];
        if (in_array($action, $requiresReason, true) && blank($data['reason'] ?? null)) {
            return $invalid('A reason is required for this signal update.', 'reason');
        }

        if (in_array($action, self::COMMENTARY_ACTIONS, true)
            && blank($data['title'] ?? null)
            && blank($data['message'] ?? null)
            && ! $this->hasLocalizedContent($data['title_i18n'] ?? [])
            && ! $this->hasLocalizedContent($data['message_i18n'] ?? [])) {
            return $invalid('Enter an update in at least one language.', 'message');
        }

        if ($action === 'details_updated'
            && blank($data['title'] ?? null)
            && ! $this->hasLocalizedContent($data['title_i18n'] ?? [])
            && blank($data['timeframe'] ?? null)
            && blank($data['risk_level'] ?? null)
            && ! array_key_exists('expires_at', $data)) {
            return $invalid('Change at least one signal detail.', 'action');
        }

        if (in_array($action, ['entry_triggered', 'entry_price_updated'], true)
            && ! isset($data['entry_price'])) {
            return $invalid('Entry price is required for this update.', 'entry_price');
        }

        if ($action === 'entry_range_updated') {
            if (! isset($data['entry_price_min'], $data['entry_price_max'])) {
                return $invalid('Entry price minimum and maximum are required.', 'entry_price_min');
            }
            if ((float) $data['entry_price_min'] > (float) $data['entry_price_max']) {
                return $invalid('Entry price minimum cannot exceed its maximum.', 'entry_price_min');
            }
        }

        if (in_array($action, ['update_stop_loss', 'trailing_stop_loss', 'lock_profit', 'reduce_risk'], true)) {
            if (! isset($data['stop_loss'])) {
                return $invalid('A new stop loss is required.', 'stop_loss');
            }
            $previousStop = $signal->current_stop_loss_value ?? $signal->stop_loss_value ?? $signal->stop_loss;
            $direction = strtoupper((string) $signal->immediate_action);
            $increasesRisk = $previousStop !== null && (
                (in_array($direction, ['BUY', 'LONG'], true) && (float) $data['stop_loss'] < (float) $previousStop)
                || (in_array($direction, ['SELL', 'SHORT'], true) && (float) $data['stop_loss'] > (float) $previousStop)
            );
            if ($increasesRisk && ! ($canManage && ($data['is_correction'] ?? false))) {
                return $invalid(
                    'The new stop loss increases risk. Use the manager correction flow.',
                    'stop_loss'
                );
            }
        }

        if (in_array($action, ['move_sl_to_be', 'update_break_even', 'trailing_break_even'], true)
            && ! isset($data['break_even_price'])) {
            return $invalid('A break-even price is required.', 'break_even_price');
        }

        if ($action === 'target_hit') {
            $targetNumber = (int) ($data['target_number'] ?? 0);
            $targetCount = $this->targetCount($signal);
            $targetsHit = array_map('intval', $signal->targets_hit ?? []);
            if ($targetNumber < 1 || $targetNumber > $targetCount) {
                return $invalid('Choose a valid target number.', 'target_number');
            }
            if (in_array($targetNumber, $targetsHit, true)) {
                return $invalid('This target is already marked hit.', 'target_number', 409);
            }
            $nextTarget = 1;
            while (in_array($nextTarget, $targetsHit, true)) {
                $nextTarget++;
            }
            if ($targetNumber !== $nextTarget && ! ($canManage && ($data['is_correction'] ?? false))) {
                return $invalid("TP{$nextTarget} must be recorded before TP{$targetNumber}.", 'target_number');
            }
        }

        if (in_array($action, ['partial_close', 'partial_profit_taken'], true)) {
            if (! isset($data['percentage_closed']) && ! isset($data['percentage_remaining'])) {
                return $invalid('Closed or remaining position percentage is required.', 'percentage_closed');
            }
            $remaining = (float) ($signal->percentage_remaining ?? 100);
            if (isset($data['percentage_closed']) && (float) $data['percentage_closed'] > $remaining) {
                return $invalid('Closed percentage cannot exceed the remaining position.', 'percentage_closed');
            }
            if (isset($data['percentage_remaining'])
                && (float) $data['percentage_remaining'] > $remaining
                && ! ($canManage && ($data['is_correction'] ?? false))) {
                return $invalid('Remaining position cannot increase without a manager correction.', 'percentage_remaining');
            }
        }

        if ($action === 'correction' && blank($data['lifecycle_override'] ?? null)) {
            return $invalid('Choose the corrected lifecycle status.', 'lifecycle_override');
        }

        return null;
    }

    private function allowedLifecycleActions(string $state, bool $canManage): array
    {
        $actions = match ($state) {
            'draft', 'scheduled' => ['published', 'cancelled'],
            'published', 'pending_entry' => [
                'entry_triggered', 'entry_price_updated', 'entry_range_updated',
                'cancelled', 'remove_pending_order', 'expired',
            ],
            'active', 'partially_closed', 'break_even', 'trailing' => [
                'update_stop_loss', 'move_sl_to_be', 'update_break_even',
                'trailing_break_even', 'trailing_stop_loss', 'lock_profit',
                'reduce_risk', 'target_hit', 'partial_close',
                'partial_profit_taken', 'stop_loss_hit', 'break_even_hit',
                'manually_closed', 'closed_in_profit', 'closed_in_loss',
                'cancelled', 'completed',
            ],
            default => [],
        };

        if ($state !== 'archived') {
            $actions = [...$actions, ...self::COMMENTARY_ACTIONS];
        }
        if (! in_array($state, [...self::TERMINAL_LIFECYCLE_STATES, 'archived'], true)) {
            $actions[] = 'details_updated';
        }
        if ($canManage) {
            $actions[] = 'correction';
            if (in_array($state, self::TERMINAL_LIFECYCLE_STATES, true)) {
                $actions[] = 'archived';
            }
            if ($state === 'archived') {
                $actions[] = 'restored';
            }
        }

        return array_values(array_unique($actions));
    }

    private function lifecycleChanges(TradingSignal $signal, string $action, array $data, int $userId): array
    {
        $next = $this->nextLifecycleState($signal, $action, $data);
        $changes = [
            'lifecycle_state' => $next,
            'last_update_type' => $action,
            'last_updated_by' => (string) $userId,
        ];

        if (isset($data['entry_price'])) {
            $changes['entry_price'] = $this->decimalString($data['entry_price']);
            $changes['entry_price_value'] = $data['entry_price'];
            $changes['current_entry_price_value'] = $data['entry_price'];
        }
        if (isset($data['entry_price_min'])) {
            $changes['entry_price_min'] = $data['entry_price_min'];
        }
        if (isset($data['entry_price_max'])) {
            $changes['entry_price_max'] = $data['entry_price_max'];
        }
        if (isset($data['stop_loss'])) {
            $changes['stop_loss'] = $this->decimalString($data['stop_loss']);
            $changes['stop_loss_value'] = $data['stop_loss'];
            $changes['current_stop_loss_value'] = $data['stop_loss'];
        }
        if (isset($data['break_even_price'])) {
            $changes['break_even_price_value'] = $data['break_even_price'];
            $changes['IsSetBE'] = 1;
            if (in_array($action, ['move_sl_to_be', 'trailing_break_even'], true)) {
                $changes['stop_loss'] = $this->decimalString($data['break_even_price']);
                $changes['stop_loss_value'] = $data['break_even_price'];
                $changes['current_stop_loss_value'] = $data['break_even_price'];
            }
        }
        if (isset($data['target_number'], $data['target_price'])) {
            $changes['target_'.$data['target_number']] = $this->decimalString($data['target_price']);
            $changes['target_'.$data['target_number'].'_value'] = $data['target_price'];
        }
        foreach (['timeframe', 'risk_level', 'expires_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field];
            }
        }

        if ($action === 'details_updated') {
            $title = $this->localizedText($data, 'title');
            if ($title !== null) {
                $changes['signal_title'] = $title;
            }
        }

        if ($action === 'target_hit') {
            $targetNumber = (int) $data['target_number'];
            $targetsHit = array_values(array_unique([
                ...array_map('intval', $signal->targets_hit ?? []),
                $targetNumber,
            ]));
            sort($targetsHit);
            $changes['targets_hit'] = $targetsHit;
            $changes['current_target_index'] = $targetNumber;
        }

        $remaining = (float) ($signal->percentage_remaining ?? 100);
        if (isset($data['percentage_remaining'])) {
            $remaining = (float) $data['percentage_remaining'];
        } elseif (isset($data['percentage_closed'])) {
            $remaining = max(0, $remaining - (float) $data['percentage_closed']);
        }
        $changes['percentage_remaining'] = $remaining;

        $changes['status'] = $this->legacyStatusForLifecycle($next, $signal, $data);
        if ($next === 'archived') {
            $changes['archived'] = true;
            $changes['archived_at'] = now();
            $changes['status_before_archive'] = $signal->mobile_lifecycle_status;
        } else {
            $changes['archived'] = false;
        }
        if ($next !== 'archived' && ($signal->archived || in_array($action, ['restored', 'correction'], true))) {
            $changes['archived_at'] = null;
            $changes['status_before_archive'] = null;
        }

        if (in_array($next, ['draft', 'scheduled'], true)) {
            $changes['workflow_status'] = $next === 'draft'
                ? TradingSignal::WORKFLOW_DRAFT
                : TradingSignal::WORKFLOW_PENDING;
            $changes['published_at'] = null;
        } elseif (in_array($next, ['published', 'pending_entry', 'active', 'partially_closed', 'break_even', 'trailing'], true)) {
            $changes['workflow_status'] = TradingSignal::WORKFLOW_PUBLISHED;
            $changes['published_at'] = $signal->published_at ?? now();
        } elseif ($next === 'cancelled') {
            $changes['workflow_status'] = TradingSignal::WORKFLOW_CANCELLED;
            $changes['cancel_reason'] = $data['reason'] ?? $signal->cancel_reason;
            $changes['cancelled_at'] = now();
        } elseif ($next === 'expired') {
            $changes['workflow_status'] = TradingSignal::WORKFLOW_EXPIRED;
            $changes['expired_at'] = now();
        } elseif (in_array($next, ['completed', 'stop_loss_hit', 'break_even_hit'], true)) {
            $changes['workflow_status'] = TradingSignal::WORKFLOW_CLOSED;
            $changes['closed_at'] = $signal->closed_at ?? now();
            $changes['completed_at'] = $signal->completed_at ?? now();
            $changes['percentage_remaining'] = 0;
        }

        if ($action === 'entry_triggered') {
            $changes['activated_at'] = $data['effective_at'] ?? now();
        }
        if (in_array($next, ['completed', 'stop_loss_hit', 'break_even_hit'], true)) {
            $changes['IsDone'] = 1;
            $changes['is_done'] = 1;
        } elseif ($action === 'correction' || $action === 'restored') {
            $changes['IsDone'] = 0;
            $changes['is_done'] = 0;
            $changes['closed_at'] = null;
            $changes['completed_at'] = null;
        }
        if ($next === 'break_even_hit') {
            $changes['IsBE'] = 1;
            $changes['IsSetBE'] = 1;
        } elseif ($action === 'correction' || $action === 'restored') {
            $changes['IsBE'] = 0;
            $changes['IsSetBE'] = in_array($next, ['break_even', 'trailing'], true) ? 1 : 0;
        }
        if ($action === 'correction' || $action === 'restored') {
            if ($next !== 'cancelled') {
                $changes['cancelled_at'] = null;
                $changes['cancel_reason'] = null;
            }
            if ($next !== 'expired') {
                $changes['expired_at'] = null;
            }
        }

        return $changes;
    }

    private function nextLifecycleState(TradingSignal $signal, string $action, array $data): string
    {
        $current = $signal->mobile_lifecycle_status;
        if ($action === 'correction') {
            return (string) $data['lifecycle_override'];
        }
        if ($action === 'restored') {
            return $signal->status_before_archive ?: 'draft';
        }

        return match ($action) {
            'published' => $this->publishedLifecycleState($signal),
            'entry_triggered' => 'active',
            'move_sl_to_be', 'update_break_even' => $current === 'trailing' ? 'trailing' : 'break_even',
            'trailing_break_even', 'trailing_stop_loss', 'lock_profit' => 'trailing',
            'partial_close', 'partial_profit_taken' => 'partially_closed',
            'target_hit' => (int) $data['target_number'] === $this->targetCount($signal)
                || (float) ($data['percentage_remaining'] ?? 1) === 0.0
                    ? 'completed'
                    : 'partially_closed',
            'stop_loss_hit' => 'stop_loss_hit',
            'break_even_hit' => 'break_even_hit',
            'manually_closed', 'closed_in_profit', 'closed_in_loss', 'completed' => 'completed',
            'cancelled', 'remove_pending_order', 'invalidated' => 'cancelled',
            'expired' => 'expired',
            'archived' => 'archived',
            default => $current,
        };
    }

    private function legacyStatusForLifecycle(string $state, TradingSignal $signal, array $data): int
    {
        return match ($state) {
            'draft', 'scheduled', 'published', 'pending_entry' => 0,
            'active', 'break_even', 'trailing' => 1,
            'partially_closed' => min(11, max(
                2,
                (int) ($data['target_number'] ?? $signal->current_target_index ?? 1) + 1
            )),
            'cancelled', 'expired' => 12,
            'stop_loss_hit' => 13,
            'completed' => 14,
            'break_even_hit' => 15,
            'archived' => (int) $signal->status,
            default => (int) $signal->status,
        };
    }

    private function publishedLifecycleState(TradingSignal $signal): string
    {
        return ($signal->order_type ?: 'market_execution') === 'market_execution'
            ? 'active'
            : 'pending_entry';
    }

    private function targetCount(TradingSignal $signal): int
    {
        if ((int) $signal->target_count > 0) {
            return (int) $signal->target_count;
        }

        $count = 0;
        for ($target = 1; $target <= 10; $target++) {
            if (filled($signal->{"target_{$target}"})) {
                $count++;
            }
        }

        return $count;
    }

    private function inputTargetCount(array $data): int
    {
        $count = 0;
        for ($target = 1; $target <= 10; $target++) {
            if (filled($data["take_profit_{$target}"] ?? null)) {
                $count = $target;
            }
        }

        return $count;
    }

    private function hasLocalizedContent(array $values): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function localizedText(array $data, string $field): ?string
    {
        if (filled($data[$field] ?? null)) {
            return trim((string) $data[$field]);
        }

        $localized = $data[$field.'_i18n'] ?? [];
        $primary = strtolower((string) ($data['primary_language'] ?? 'en'));
        $value = $localized[$primary]
            ?? $localized[str_starts_with($primary, 'zh') ? 'zh' : 'en']
            ?? collect($localized)->first(fn ($item) => filled($item));

        return filled($value) ? trim((string) $value) : null;
    }

    private function lifecycleAuditMetadata(array $data, string $from, string $to, int $version): array
    {
        $safeKeys = [
            'reason', 'title', 'message', 'title_i18n', 'message_i18n',
            'primary_language', 'entry_price', 'entry_price_min', 'entry_price_max',
            'stop_loss', 'break_even_price', 'target_number', 'target_price',
            'percentage_closed', 'percentage_remaining', 'realised_pips',
            'realised_percentage', 'profit_lock_offset', 'entry_was_triggered',
            'attachment_urls', 'effective_at', 'notification_requested',
            'is_correction', 'corrected_update_id', 'lifecycle_override',
            'timeframe', 'risk_level', 'expires_at',
        ];
        $metadata = array_intersect_key($data, array_flip($safeKeys));

        return $metadata + [
            'lifecycle_from' => $from,
            'lifecycle_to' => $to,
            'version' => $version,
        ];
    }

    private function afterPublished(TradingSignal $signal, $user): void
    {
        $signal = $signal->fresh();
        $this->audit->record($signal, 'SIGNAL_PUBLISHED', $user);
        try {
            TradingSignalPublished::dispatch($signal);
        } catch (\Throwable $exception) {
            Log::warning('Trading signal broadcast dispatch failed.', [
                'signal_id' => $signal->id,
                'exception' => $exception::class,
            ]);
        }

        if ($this->discordConfigured()) {
            try {
                SendTradingSignalToDiscord::dispatch($signal->id)->afterCommit();
            } catch (\Throwable $exception) {
                $signal->forceFill([
                    'discord_delivery_status' => 'FAILED',
                    'discord_last_error' => Str::limit($exception->getMessage(), 1000),
                ])->saveQuietly();
                Log::error('Trading signal Discord job dispatch failed.', [
                    'signal_id' => $signal->id,
                    'exception' => $exception::class,
                ]);
            }
        }
        if (app(\App\Services\FirebasePushNotificationService::class)->configured()) {
            try {
                SendTradingSignalPushNotification::dispatch($signal->id)->afterCommit();
            } catch (\Throwable $exception) {
                Log::error('Trading signal push job dispatch failed.', [
                    'signal_id' => $signal->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        try {
            AppNotificationService::notifyRoles(
                [750, 760, 770],
                'New trading signal — '.$signal->trading_pair,
                strtoupper($signal->immediate_action).' '.$signal->trading_pair.' @ '.$signal->entry_price,
                route('member.signals.view', $signal->id),
                'trading_signal',
                $user?->id
            );
        } catch (\Throwable $exception) {
            Log::warning('Published signal in-app notification failed.', [
                'signal_id' => $signal->id,
                'exception' => $exception::class,
            ]);
        }

        Log::info('Trading signal published.', [
            'signal_id' => $signal->id,
            'source' => $signal->source,
            'user_id' => $user?->id,
        ]);
    }

    private function discordConfigured(): bool
    {
        return (bool) config('trading_signals.discord_enabled')
            && filled(config('trading_signals.discord_webhook_url'));
    }

    private function decimalString(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');
    }
}
