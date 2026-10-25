<?php

namespace App\Services;

use App\Models\TradingJournal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TradingJournalSyncService
{
    private const JOURNAL_FIELDS = [
        'status', 'close_handling', 'time_input_timezone', 'time_input_offset_minutes',
        'open_date', 'close_date', 'pair', 'direction', 'entry_price', 'exit_price',
        'lot_size', 'pips', 'profit_loss', 'result', 'notes', 'emotion_before_entry',
        'emotion_during_trade', 'emotion_after_trade', 'confidence_level',
        'discipline_rating', 'mistake_tags', 'psychology_notes',
    ];

    private const MOBILE_FIELDS = [
        'mobile_trading_account_id',
        'source', 'gross_profit', 'commission', 'swap', 'fees', 'stop_loss', 'take_profit',
        'initial_risk_amount', 'initial_risk_percent', 'setup_name', 'session',
        'source_ticket', 'source_order_id', 'source_position_id', 'currency',
        'followed_trading_plan', 'trading_plan_id', 'trading_plan_name',
        'trading_plan_checked_at', 'strategy_id', 'strategy_name',
        'plan_compliance_checklist', 'strategy_compliance_checklist',
        'followed_risk_rules', 'avoided_fomo', 'followed_session_rules',
        'avoided_restricted_news', 'stopped_after_max_loss',
        'followed_strategy_rules', 'followed_sl_rule', 'followed_tp_rule',
        'followed_invalidation_rule',
    ];

    public function __construct(private readonly TradingJournalSyncLedger $ledger) {}

    public function page(int $userId, int $cursor, int $limit): array
    {
        $this->ledger->ensureLegacyRows($userId);
        $events = DB::table('trading_journal_sync_events')
            ->where('user_id', $userId)->where('id', '>', $cursor)
            ->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $events->count() > $limit;
        $events = $events->take($limit);
        $rows = $events->map(function ($event) use ($userId): array {
            $sync = DB::table('trading_journal_sync_records')
                ->where('id', $event->sync_record_id)->where('user_id', $userId)->first();
            return [
                'cursor' => $event->id,
                'operation' => $sync?->deleted_at ? 'delete' : $event->operation,
                'record' => $sync ? $this->serialize($sync) : null,
            ];
        })->all();

        return [
            'changes' => $rows,
            'next_cursor' => $events->last()?->id ?? $cursor,
            'has_more' => $hasMore,
        ];
    }

    public function item(int $userId, int $syncId): ?array
    {
        $sync = DB::table('trading_journal_sync_records')
            ->where('id', $syncId)->where('user_id', $userId)->first();
        return $sync ? $this->serialize($sync) : null;
    }

    public function itemByJournal(int $userId, int $journalId): ?array
    {
        $sync = DB::table('trading_journal_sync_records')->where('user_id', $userId)
            ->where('journal_id', $journalId)->first();
        return $sync ? $this->serialize($sync) : null;
    }

    public function apply(int $userId, array $operation): array
    {
        return DB::transaction(function () use ($userId, $operation): array {
            $kind = $operation['type'] ?? null;
            if (! in_array($kind, ['create', 'update', 'delete'], true)) {
                throw ValidationException::withMessages(['type' => 'Invalid journal operation.']);
            }
            $mobileId = trim((string) ($operation['mobile_id'] ?? ''));
            if ($mobileId === '' || strlen($mobileId) > 191 || preg_match('/[\x00-\x1f\/]/', $mobileId)) {
                throw ValidationException::withMessages(['mobile_id' => 'A stable mobile record ID is required.']);
            }
            $sync = DB::table('trading_journal_sync_records')
                ->where('user_id', $userId)->where('mobile_id', $mobileId)->lockForUpdate()->first();
            if (! $sync && isset($operation['sync_id'])) {
                $sync = DB::table('trading_journal_sync_records')
                    ->where('user_id', $userId)->where('id', $operation['sync_id'])->lockForUpdate()->first();
                if ($sync && $sync->mobile_id !== null && $sync->mobile_id !== $mobileId) {
                    return $this->conflict('This journal is linked to another mobile record.', $sync);
                }
                if (! $sync) {
                    return ['status' => 'not_found', 'message' => 'The linked journal does not exist.'];
                }
            }
            if ($sync && $sync->deleted_at) {
                return $kind === 'delete'
                    ? ['status' => 'deleted', 'record' => $this->serialize($sync)]
                    : $this->conflict('This journal was deleted. The old mobile copy cannot restore it.', $sync);
            }
            if ($kind === 'delete') {
                if (! $sync) {
                    return isset($operation['sync_id'])
                        ? ['status' => 'not_found', 'message' => 'The linked journal does not exist.']
                        : ['status' => 'unchanged', 'record' => null];
                }
                if (! $this->revisionMatches($sync, $operation)) {
                    return $this->conflict('The journal changed before deletion.', $sync);
                }
                $journal = TradingJournal::where('user_id', $userId)->find($sync->journal_id);
                $journal?->delete();
                return ['status' => 'deleted', 'record' => $this->item($userId, (int) $sync->id)];
            }
            $fields = array_intersect_key((array) ($operation['fields'] ?? []), array_flip(self::JOURNAL_FIELDS));
            $metadata = array_intersect_key((array) ($operation['fields'] ?? []), array_flip(self::MOBILE_FIELDS));
            $this->validateFields($fields, $metadata, $kind === 'create');
            foreach (['open_date', 'close_date'] as $dateField) {
                if (isset($fields[$dateField])) {
                    $fields[$dateField] = Carbon::parse($fields[$dateField])->format('Y-m-d H:i:s');
                }
            }
            if ($kind === 'create' && ! $sync) {
                // Serialize creates for this account so two devices cannot both
                // decide that the same legacy trade is absent.
                DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                $retry = DB::table('trading_journal_sync_records')->where('user_id', $userId)
                    ->where('mobile_id', $mobileId)->lockForUpdate()->first();
                if ($retry) {
                    return $this->createRetry($retry, $fields, $metadata);
                }
                $duplicates = TradingJournal::where('user_id', $userId)
                    ->where('pair', $fields['pair'])
                    ->where('open_date', Carbon::parse($fields['open_date'])->format('Y-m-d H:i:s'))
                    ->where('direction', $fields['direction'])->where('entry_price', $fields['entry_price'])
                    ->where('lot_size', $fields['lot_size'])->get();
                if ($duplicates->isNotEmpty()) {
                    $this->ledger->ensureLegacyRows($userId);
                    if ($duplicates->count() === 1 && $this->isExactLegacyMatch($duplicates->first(), $fields)) {
                        $candidate = DB::table('trading_journal_sync_records')
                            ->where('user_id', $userId)->where('journal_id', $duplicates->first()->id)
                            ->lockForUpdate()->first();
                        if ($candidate && $candidate->mobile_id === null && $candidate->deleted_at === null) {
                            DB::table('trading_journal_sync_records')->where('id', $candidate->id)->update([
                                'mobile_id' => $mobileId,
                                'mobile_metadata' => json_encode($metadata),
                                'updated_at' => now(),
                            ]);
                            $this->ledger->record($duplicates->first(), 'update');
                            return ['status' => 'linked', 'record' => $this->item($userId, (int) $candidate->id)];
                        }
                    }
                    return [
                        'status' => 'reconciliation_required',
                        'message' => 'A similar website trade exists. Link it manually before uploading.',
                        'candidate_sync_ids' => DB::table('trading_journal_sync_records')
                            ->where('user_id', $userId)->whereIn('journal_id', $duplicates->pluck('id'))->pluck('id')->all(),
                    ];
                }
                $journal = TradingJournal::create([
                    'user_id' => $userId, 'type' => 'trade',
                    ...$fields,
                    'status' => $fields['status'] ?? 'closed',
                    'trade_date' => $fields['close_date'] ?? null,
                ]);
                $sync = DB::table('trading_journal_sync_records')->where('journal_id', $journal->id)->first();
                DB::table('trading_journal_sync_records')->where('id', $sync->id)->update([
                    'mobile_id' => $mobileId,
                    'mobile_metadata' => json_encode($metadata),
                    'updated_at' => now(),
                ]);
                return ['status' => 'created', 'record' => $this->item($userId, (int) $sync->id)];
            }
            if (! $sync) {
                return ['status' => 'not_found', 'message' => 'The linked journal does not exist.'];
            }
            if ($kind === 'create') {
                if ($sync->mobile_id === null) {
                    if (($operation['confirm_match'] ?? null) !== true || ! $this->revisionMatches($sync, $operation)) {
                        return $this->conflict('Confirm the website match at its current revision before linking.', $sync);
                    }
                    $journal = TradingJournal::where('user_id', $userId)->find($sync->journal_id);
                    if (! $journal) {
                        return $this->conflict('The website journal is unavailable.', $sync);
                    }
                    DB::table('trading_journal_sync_records')->where('id', $sync->id)->update([
                        'mobile_id' => $mobileId,
                        'mobile_metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);
                    $this->ledger->record($journal, 'update');
                    return ['status' => 'linked', 'record' => $this->item($userId, (int) $sync->id)];
                }
                return $this->createRetry($sync, $fields, $metadata);
            }
            $journal = TradingJournal::where('user_id', $userId)->find($sync->journal_id);
            if (! $journal) {
                return $this->conflict('The website journal is unavailable.', $sync);
            }
            $current = $this->serialize($sync);
            $base = (array) ($operation['base'] ?? []);
            $merged = array_replace($current, $fields);
            if (($merged['status'] ?? 'closed') === 'closed') {
                foreach (['close_date', 'exit_price', 'profit_loss', 'result'] as $required) {
                    if (($merged[$required] ?? null) === null) {
                        throw ValidationException::withMessages([$required => 'Closed trades require complete close fields.']);
                    }
                }
            }
            if (! $this->revisionMatches($sync, $operation)) {
                foreach ([...$fields, ...$metadata] as $key => $value) {
                    if (! array_key_exists($key, $base) || (! $this->sameValue($key, $current[$key] ?? null, $base[$key])
                        && ! $this->sameValue($key, $current[$key] ?? null, $value))) {
                        return $this->conflict('Concurrent edit of '.$key.'.', $sync);
                    }
                }
            }
            $changedFields = array_filter($fields, fn ($value, $key) => ! $this->sameValue($key, $current[$key] ?? null, $value), ARRAY_FILTER_USE_BOTH);
            $storedMetadata = (array) json_decode($sync->mobile_metadata ?? '{}', true);
            $newMetadata = array_replace($storedMetadata, $metadata);
            if ($changedFields) {
                if (array_key_exists('close_date', $changedFields)) {
                    $changedFields['trade_date'] = $changedFields['close_date'];
                }
                $journal->update($changedFields);
            } elseif ($newMetadata != $storedMetadata || $sync->mobile_id === null) {
                $this->ledger->record($journal, 'update');
            }
            if ($metadata || $sync->mobile_id === null) {
                DB::table('trading_journal_sync_records')->where('id', $sync->id)->update([
                    'mobile_id' => $mobileId,
                    'mobile_metadata' => json_encode($newMetadata),
                    'updated_at' => now(),
                ]);
            }
            return ['status' => $changedFields || $newMetadata != $storedMetadata ? 'updated' : 'unchanged', 'record' => $this->item($userId, (int) $sync->id)];
        });
    }

    private function revisionMatches(object $sync, array $operation): bool
    {
        return isset($operation['base_revision']) && (int) $operation['base_revision'] === (int) $sync->revision;
    }

    private function createRetry(object $sync, array $fields, array $metadata): array
    {
        if ($sync->deleted_at) return $this->conflict('This journal was deleted.', $sync);
        $current = $this->serialize($sync);
        foreach ([...$fields, ...$metadata] as $key => $value) {
            if (! $this->sameValue($key, $current[$key] ?? null, $value)) {
                return $this->conflict('This mobile record was already uploaded with different values. Review it before replacing changes.', $sync);
            }
        }
        return ['status' => 'unchanged', 'record' => $current];
    }

    private function sameValue(string $key, mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) return $left === $right;
        if (in_array($key, ['open_date', 'close_date'], true)) {
            try {
                return Carbon::parse($left)->format('Y-m-d H:i:s') === Carbon::parse($right)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return false;
            }
        }
        return $left == $right;
    }

    private function isExactLegacyMatch(TradingJournal $journal, array $fields): bool
    {
        if (($fields['status'] ?? null) !== 'closed' || $journal->status !== 'closed') {
            return false;
        }
        if ($journal->close_date?->format('Y-m-d H:i:s') !== Carbon::parse($fields['close_date'])->format('Y-m-d H:i:s')) {
            return false;
        }
        foreach (['exit_price' => 8, 'pips' => 5, 'profit_loss' => 2] as $field => $scale) {
            if (! isset($fields[$field], $journal->$field) || bccomp((string) $fields[$field], (string) $journal->$field, $scale) !== 0) {
                return false;
            }
        }
        if ((int) $journal->result !== (int) ($fields['result'] ?? 0)) {
            return false;
        }
        $defaults = [
            'close_handling' => 'manual_close', 'time_input_timezone' => 'malaysia',
            'time_input_offset_minutes' => 480,
        ];
        foreach (['close_handling', 'time_input_timezone', 'time_input_offset_minutes',
            'notes', 'emotion_before_entry', 'emotion_during_trade', 'emotion_after_trade',
            'confidence_level', 'discipline_rating', 'mistake_tags', 'psychology_notes'] as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }
            $websiteValue = $journal->$field ?? ($defaults[$field] ?? null);
            $mobileValue = $fields[$field];
            if (is_array($websiteValue) || is_array($mobileValue)) {
                if (($websiteValue ?? []) !== ($mobileValue ?? [])) return false;
            } elseif ((string) ($websiteValue ?? '') !== (string) ($mobileValue ?? '')) {
                return false;
            }
        }
        return true;
    }

    private function conflict(string $message, object $sync): array
    {
        return ['status' => 'conflict', 'message' => $message, 'record' => $this->serialize($sync)];
    }

    private function serialize(object $sync): array
    {
        $journal = $sync->deleted_at ? null : TradingJournal::where('user_id', $sync->user_id)->find($sync->journal_id);
        return [
            ...($journal?->toArray() ?? []),
            // Journal forms on both platforms store Malaysia wall-clock values.
            // Eloquent's default JSON adds a misleading UTC suffix to these.
            'open_date' => $journal?->open_date?->format('Y-m-d\TH:i:s'),
            'close_date' => $journal?->close_date?->format('Y-m-d\TH:i:s'),
            ...(array) json_decode($sync->mobile_metadata ?? '{}', true),
            'source' => data_get(json_decode($sync->mobile_metadata ?? '{}', true), 'source', 'manual'),
            'sync_id' => $sync->id,
            'website_record_id' => $sync->journal_id,
            'mobile_id' => $sync->mobile_id,
            'revision' => (int) $sync->revision,
            'deleted_at' => $sync->deleted_at,
        ];
    }

    private function validateFields(array $fields, array $metadata, bool $create): void
    {
        $rules = [
            'status' => [Rule::in(['draft', 'open', 'closed'])],
            'close_handling' => [Rule::in(['manual_close', 'follow_pre_set'])],
            'time_input_timezone' => [Rule::in(['malaysia', 'mt5'])],
            'time_input_offset_minutes' => ['nullable', 'integer', 'between:-840,840'],
            'open_date' => [$create ? 'required' : 'sometimes', 'date'],
            'close_date' => ['nullable', 'date'],
            'pair' => [$create ? 'required' : 'sometimes', 'string', 'max:255'],
            'direction' => [$create ? 'required' : 'sometimes', Rule::in([1, 2])],
            'entry_price' => [$create ? 'required' : 'sometimes', 'numeric', 'between:0,9999999999'],
            'exit_price' => ['nullable', 'numeric', 'between:0,9999999999'],
            'lot_size' => [$create ? 'required' : 'sometimes', 'numeric', 'between:0,9999999999'],
            'pips' => ['nullable', 'numeric'],
            'profit_loss' => ['nullable', 'numeric'],
            'result' => ['nullable', Rule::in([1, 2, 3])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'emotion_before_entry' => ['nullable', 'string', 'max:100'],
            'emotion_during_trade' => ['nullable', 'string', 'max:100'],
            'emotion_after_trade' => ['nullable', 'string', 'max:100'],
            'confidence_level' => ['nullable', 'integer', 'between:1,10'],
            'discipline_rating' => ['nullable', 'integer', 'between:1,10'],
            'mistake_tags' => ['nullable', 'array', 'max:20'],
            'mistake_tags.*' => ['string', 'max:100'],
            'psychology_notes' => ['nullable', 'string', 'max:5000'],
        ];
        Validator::make($fields, $rules)->validate();
        if ($create && ($fields['status'] ?? 'closed') === 'closed') {
            Validator::make($fields, [
                'close_date' => ['required', 'date'],
                'exit_price' => ['required', 'numeric'],
                'profit_loss' => ['required', 'numeric'],
                'result' => ['required', Rule::in([1, 2, 3])],
            ])->validate();
        }
        if (strlen(json_encode($metadata) ?: '') > 65535) {
            throw ValidationException::withMessages(['fields' => 'Mobile journal metadata is too large.']);
        }
        Validator::make($metadata, [
            'mobile_trading_account_id' => ['nullable', 'string', 'max:191'],
            'source' => [Rule::in(['manual', 'excel_import'])],
            'gross_profit' => ['nullable', 'numeric'],
            'commission' => ['nullable', 'numeric'],
            'swap' => ['nullable', 'numeric'],
            'fees' => ['nullable', 'numeric'],
            'stop_loss' => ['nullable', 'numeric'],
            'take_profit' => ['nullable', 'numeric'],
            'initial_risk_amount' => ['nullable', 'numeric'],
            'initial_risk_percent' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'max:10'],
            'setup_name' => ['nullable', 'string', 'max:255'],
            'session' => ['nullable', 'string', 'max:100'],
            'source_ticket' => ['nullable', 'string', 'max:255'],
            'source_order_id' => ['nullable', 'string', 'max:255'],
            'source_position_id' => ['nullable', 'string', 'max:255'],
            'trading_plan_id' => ['nullable', 'string', 'max:255'],
            'trading_plan_name' => ['nullable', 'string', 'max:255'],
            'trading_plan_checked_at' => ['nullable', 'date'],
            'strategy_id' => ['nullable', 'string', 'max:255'],
            'strategy_name' => ['nullable', 'string', 'max:255'],
            'plan_compliance_checklist' => ['nullable', 'array', 'max:50'],
            'plan_compliance_checklist.*' => ['boolean'],
            'strategy_compliance_checklist' => ['nullable', 'array', 'max:50'],
            'strategy_compliance_checklist.*' => ['boolean'],
            'followed_trading_plan' => ['nullable', 'boolean'],
            'followed_risk_rules' => ['nullable', 'boolean'],
            'avoided_fomo' => ['nullable', 'boolean'],
            'followed_session_rules' => ['nullable', 'boolean'],
            'avoided_restricted_news' => ['nullable', 'boolean'],
            'stopped_after_max_loss' => ['nullable', 'boolean'],
            'followed_strategy_rules' => ['nullable', 'boolean'],
            'followed_sl_rule' => ['nullable', 'boolean'],
            'followed_tp_rule' => ['nullable', 'boolean'],
            'followed_invalidation_rule' => ['nullable', 'boolean'],
        ])->validate();
    }
}
