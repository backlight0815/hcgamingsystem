<?php

namespace App\Services;

use App\Models\PmGlobalCycle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsGlobalCycleService
{
    public const STAGES = [
        'planning' => 'Planning & preparation',
        'development' => 'Development',
        'internal_testing' => 'Internal testing',
        'qat' => 'QAT testing & debugging',
        'preparation' => 'Release preparation',
        'closure' => 'Release & closure',
    ];

    public const CLOSED_TICKET_STATUSES = ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed', 'Cancelled', 'Duplicate', 'Rejected'];

    public function create(array $data, ?User $actor = null, bool $automated = false): PmGlobalCycle
    {
        return DB::transaction(function () use ($data, $actor, $automated): PmGlobalCycle {
            $settings = DB::table('pm_global_cycle_settings')->where('stream_key', 'main')->lockForUpdate()->first();
            $start = Carbon::parse($data['start_date'])->startOfDay();
            $end = Carbon::parse($data['end_date'])->startOfDay();
            $days = $start->diffInDays($end) + 1;
            if ($end->lt($start) || $days < 6 || $days > 365) {
                throw ValidationException::withMessages(['end_date' => 'A cycle must last between 6 and 365 calendar days.']);
            }
            $this->assertNoOverlap($start->toDateString(), $end->toDateString());
            $year = $start->year;
            $last = DB::table('pm_global_cycles')->where('cycle_key', 'like', 'CYC-'.$year.'-%')
                ->orderByDesc('cycle_key')->value('cycle_key');
            $number = $last ? (int) substr($last, -3) + 1 : 1;
            $today = now($settings->timezone)->toDateString();
            $status = $start->toDateString() > $today ? 'Upcoming' : ($end->toDateString() < $today ? ($automated ? 'Completed' : 'Delayed') : 'Active');
            $cycle = new PmGlobalCycle();
            $cycle->forceFill([
                'stream_key' => 'main', 'cycle_key' => $data['cycle_key'] ?? sprintf('CYC-%d-%03d', $year, $number),
                'name' => $data['name'], 'goal' => $data['goal'] ?? null,
                'notes' => $data['notes'] ?? null,
                'production_target_date' => $data['production_target_date'] ?? $end->toDateString(),
                'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
                'baseline_start_date' => $start->toDateString(), 'baseline_end_date' => $end->toDateString(),
                'status' => $status, 'automated' => $automated, 'owner_id' => $data['owner_id'] ?? null,
                'completed_at' => $status === 'Completed' ? now() : null,
                'created_by' => $actor?->id,
            ])->save();
            $durations = $data['stage_durations'] ?? $this->defaultDurations($days);
            if (count($durations) !== count(self::STAGES) || array_diff(array_keys(self::STAGES), array_keys($durations))
                || array_sum($durations) !== $days
                || collect($durations)->contains(fn ($duration) => ! is_numeric($duration) || (int) $duration < 1)) {
                throw ValidationException::withMessages(['stage_durations' => 'Provide positive durations for all six stages that total the cycle length.']);
            }
            $this->writeStages($cycle, $durations);
            $this->history($cycle, $actor, $automated ? 'auto_created' : 'created', [
                'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
            ]);

            return $cycle->load('stages');
        });
    }

    public function generateDue(): int
    {
        $created = 0;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            try {
                $generated = DB::transaction(function (): bool {
                    $settings = DB::table('pm_global_cycle_settings')->where('stream_key', 'main')->lockForUpdate()->first();
                    if (! $settings) {
                        return false;
                    }
                    $today = now($settings->timezone)->toDateString();
                    foreach (PmGlobalCycle::where('stream_key', 'main')->whereIn('status', ['Upcoming', 'Active'])
                        ->whereDate('end_date', '<', $today)->orderBy('id')->lockForUpdate()->get() as $expired) {
                        $open = $expired->tickets()->whereNotIn('status', self::CLOSED_TICKET_STATUSES)->exists();
                        $expired->update(['status' => $open ? 'Delayed' : 'Completed', 'completed_at' => $open ? null : now()]);
                        $this->history($expired, null, $open ? 'closure_review' : 'auto_completed', ['open_tickets' => $open]);
                    }
                    foreach (PmGlobalCycle::where('stream_key', 'main')->where('status', 'Upcoming')
                        ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
                        ->orderBy('id')->lockForUpdate()->get() as $active) {
                        $active->update(['status' => 'Active']);
                        $this->history($active, null, 'auto_activated');
                    }
                    if (! $settings->auto_enabled) return false;
                    $latest = PmGlobalCycle::where('stream_key', 'main')->orderByDesc('end_date')->lockForUpdate()->first();
                    $start = $latest ? $latest->end_date->copy()->addDay() : Carbon::parse($settings->anchor_date);
                    if ($start->toDateString() > $today) {
                        return false;
                    }
                    $this->create([
                        'name' => 'Development cycle '.$start->format('M Y'),
                        'start_date' => $start->toDateString(),
                        'end_date' => $start->copy()->addDays($settings->interval_days - 1)->toDateString(),
                    ], null, true);
                    return true;
                });
                if (! $generated) {
                    break;
                }
                $created++;
            } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
                // A concurrent generator committed first. The next iteration reads its cycle.
            }
        }

        return $created;
    }

    public function reschedule(PmGlobalCycle $cycle, array $data, User $actor): PmGlobalCycle
    {
        return DB::transaction(function () use ($cycle, $data, $actor): PmGlobalCycle {
            $locked = PmGlobalCycle::lockForUpdate()->findOrFail($cycle->id);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This cycle changed. Refresh and try again.']);
            }
            if (in_array($locked->status, ['Completed', 'Cancelled'], true)) {
                throw ValidationException::withMessages(['status' => 'Completed or cancelled cycles cannot be rescheduled.']);
            }
            $start = Carbon::parse($data['start_date'])->startOfDay();
            $end = Carbon::parse($data['end_date'])->startOfDay();
            $days = $start->diffInDays($end) + 1;
            if ($end->lt($start) || $days < 6 || $days > 365) {
                throw ValidationException::withMessages(['end_date' => 'A cycle must last between 6 and 365 calendar days.']);
            }
            $this->assertNoOverlap($start->toDateString(), $end->toDateString(), $locked->id);
            $before = $locked->only(['start_date', 'end_date', 'name', 'goal', 'owner_id']);
            $scheduleChanged = $locked->start_date->toDateString() !== $start->toDateString()
                || $locked->end_date->toDateString() !== $end->toDateString();
            if ($scheduleChanged && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Explain every cycle date change.']);
            }
            $previousStages = $locked->stages->keyBy('stage_key');
            $locked->forceFill([
                'name' => $data['name'], 'goal' => $data['goal'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            if ($scheduleChanged) {
                $locked->stages()->delete();
                $this->writeStages($locked, $this->defaultDurations($days), $previousStages);
            }
            $this->history($locked, $actor, 'updated', ['before' => $before, 'after' => $locked->only(array_keys($before))], $data['reason'] ?? null);

            return $locked->fresh()->load('stages');
        });
    }

    public function replaceStages(PmGlobalCycle $cycle, array $stages, User $actor, string $reason): PmGlobalCycle
    {
        return DB::transaction(function () use ($cycle, $stages, $actor, $reason): PmGlobalCycle {
            $locked = PmGlobalCycle::lockForUpdate()->findOrFail($cycle->id);
            if (in_array($locked->status, ['Completed', 'Cancelled'], true)) {
                throw ValidationException::withMessages(['status' => 'This cycle is closed.']);
            }
            $keys = collect($stages)->pluck('stage_key');
            if ($keys->count() !== count(self::STAGES) || $keys->unique()->count() !== count(self::STAGES)
                || $keys->diff(array_keys(self::STAGES))->isNotEmpty()) {
                throw ValidationException::withMessages(['stages' => 'Include each lifecycle stage exactly once.']);
            }
            $days = $locked->start_date->diffInDays($locked->end_date) + 1;
            if (collect($stages)->sum('duration_days') !== $days) {
                throw ValidationException::withMessages(['stages' => "Stage durations must add up to {$days} calendar days."]);
            }
            $previous = $locked->stages->map(fn ($stage) => $stage->only(['stage_key', 'sort_order', 'start_date', 'end_date', 'completion_percent']))->all();
            $locked->stages()->delete();
            $this->writeStages($locked, collect($stages)->pluck('duration_days', 'stage_key')->all(), null, $stages);
            $locked->increment('lock_version');
            $this->history($locked, $actor, 'stages_updated', ['before' => $previous, 'after' => $stages], $reason);

            return $locked->fresh()->load('stages');
        });
    }

    public function history(PmGlobalCycle $cycle, ?User $actor, string $event, array $details = [], ?string $reason = null): void
    {
        DB::table('pm_global_cycle_history')->insert([
            'cycle_id' => $cycle->id, 'actor_id' => $actor?->id, 'event' => $event,
            'reason' => $reason, 'details' => json_encode($details), 'created_at' => now(),
        ]);
    }

    private function assertNoOverlap(string $start, string $end, ?int $exceptId = null): void
    {
        $overlap = PmGlobalCycle::where('stream_key', 'main')->when($exceptId, fn ($q) => $q->where('id', '<>', $exceptId))
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'This cycle overlaps an existing organization cycle.']);
        }
    }

    private function defaultDurations(int $days): array
    {
        $configured = json_decode((string) DB::table('pm_global_cycle_settings')->where('stream_key', 'main')->value('default_stage_durations'), true);
        $weights = ['planning' => 3, 'development' => 14, 'internal_testing' => 7, 'qat' => 14, 'preparation' => 6];
        if (is_array($configured) && collect($weights)->keys()->every(fn ($key) => isset($configured[$key]) && is_numeric($configured[$key]) && $configured[$key] > 0)) {
            $weights = array_intersect_key($configured, $weights);
        }
        $usable = $days - 1;
        $durations = [];
        foreach ($weights as $key => $weight) {
            $durations[$key] = max(1, (int) floor($usable * $weight / array_sum($weights)));
        }
        while (array_sum($durations) > $usable) {
            $key = array_search(max($durations), $durations, true);
            $durations[$key]--;
        }
        $durations['preparation'] += $usable - array_sum($durations);
        $durations['closure'] = 1;

        return $durations;
    }

    private function writeStages(PmGlobalCycle $cycle, array $durations, $previous = null, ?array $ordered = null): void
    {
        $cursor = $cycle->start_date->copy();
        $order = $ordered ?? collect(array_keys(self::STAGES))->map(fn ($key) => ['stage_key' => $key, 'duration_days' => $durations[$key]])->all();
        foreach ($order as $position => $data) {
            $key = $data['stage_key'];
            $length = (int) $data['duration_days'];
            $end = $cursor->copy()->addDays($length - 1);
            $prior = $previous?->get($key);
            $cycle->stages()->create([
                'stage_key' => $key, 'name' => self::STAGES[$key], 'sort_order' => $position + 1,
                'start_date' => $cursor->toDateString(), 'end_date' => $end->toDateString(),
                'completion_percent' => $data['completion_percent'] ?? $prior?->completion_percent ?? 0,
                'owner_id' => $data['owner_id'] ?? $prior?->owner_id,
                'notes' => $data['notes'] ?? $prior?->notes,
                'completed_at' => ((int) ($data['completion_percent'] ?? $prior?->completion_percent ?? 0)) === 100 ? ($prior?->completed_at ?? now()) : null,
            ]);
            $cursor = $end->addDay();
        }
    }
}
