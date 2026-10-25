<?php

namespace App\Services;

use App\Models\PmCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsCycleService
{
    public const PHASES = [
        'development' => 'Development',
        'internal_testing' => 'Internal Testing',
        'qat' => 'QAT + Debugging',
        'buffer' => 'Contingency Buffer',
    ];

    public function template(string $mode, int $weeks, ?object $settings = null): array
    {
        $perWeek = $mode === 'working'
            ? max(1, 7 - count(array_unique($this->weekends($settings ?? (object) []))))
            : 7;

        return match ($weeks) {
            4 => ['development_days' => 2 * $perWeek, 'internal_testing_days' => $perWeek, 'qat_days' => $perWeek, 'buffer_days' => 0],
            5 => ['development_days' => 2 * $perWeek, 'internal_testing_days' => $perWeek, 'qat_days' => 2 * $perWeek, 'buffer_days' => 0],
            6 => ['development_days' => 2 * $perWeek, 'internal_testing_days' => $perWeek, 'qat_days' => 2 * $perWeek, 'buffer_days' => $perWeek],
            default => throw ValidationException::withMessages(['template_weeks' => 'Choose a 4, 5, or 6 week template, or provide a custom target end date.']),
        };
    }

    public function targetEnd(string $startDate, int $weeks, string $mode, object $settings): CarbonImmutable
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        if ($mode === 'calendar') {
            return $start->addDays(($weeks * 7) - 1);
        }

        $eligiblePerWeek = max(1, 7 - count(array_unique($this->weekends($settings))));

        return $this->advanceEligible($start, ($weeks * $eligiblePerWeek) - 1, $mode, $settings);
    }

    public function schedule(array $data, object $settings): array
    {
        $dates = $this->eligibleDates($data['start_date'], $data['target_end_date'], $data['week_mode'], $settings);
        $durations = [
            'development' => (int) $data['development_days'],
            'internal_testing' => (int) $data['internal_testing_days'],
            'qat' => (int) $data['qat_days'],
            'buffer' => (int) ($data['buffer_days'] ?? 0),
        ];
        $planned = array_sum($durations);
        if ($planned > $dates->count()) {
            throw ValidationException::withMessages([
                'target_end_date' => "The phases require {$planned} eligible days, but the cycle window contains only {$dates->count()}.",
            ]);
        }

        $offset = 0;
        $phases = [];
        foreach ($durations as $key => $days) {
            if ($days < 1) {
                continue;
            }
            $phaseDates = $dates->slice($offset, $days)->values();
            $phases[] = [
                'phase_key' => $key,
                'name' => self::PHASES[$key],
                'sort_order' => count($phases) + 1,
                'planned_start_date' => $phaseDates->first()->toDateString(),
                'planned_end_date' => $phaseDates->last()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $offset += $days;
        }

        return $phases;
    }

    public function analytics(PmCycle $cycle): array
    {
        $cycle->loadMissing(['phases', 'issues']);
        $settings = DB::table('pms_workspace_settings')->first() ?: (object) [];
        $eligible = $this->eligibleDates($cycle->start_date, $cycle->target_end_date, $cycle->week_mode, $settings);
        $today = CarbonImmutable::today();
        $elapsed = $eligible->filter(fn (CarbonImmutable $date) => $date->lte($today))->count();
        $schedule = $eligible->isEmpty() ? 0 : min(100, round(($elapsed / $eligible->count()) * 100, 1));

        $stages = app(PmsCycleProgress::class)->forCycle($cycle);
        $basis = 'development-applicable tickets';
        $total = (float) $stages['development']['total'];
        $completed = (float) $stages['development']['completed'];
        $work = $stages['development']['percent'] ?? 0;
        $qaPlanned = $stages['testing']['total'];
        $qaComplete = $stages['testing']['completed'];
        $quality = $stages['testing']['percent'] ?? 0;

        $currentPhase = $cycle->phases->first(fn ($phase) => $today->betweenIncluded($phase->planned_start_date, $phase->planned_end_date));
        $thresholds = json_decode($settings->health_thresholds ?? '{}', true) ?: [];
        $riskGap = (int) ($thresholds['risk_gap'] ?? 20);
        $attentionGap = (int) ($thresholds['attention_gap'] ?? 10);
        $reopenLimit = (int) ($thresholds['reopen_limit'] ?? 2);
        $reopens = (int) $cycle->issues->sum('reopen_count');
        $blockedIds = app(PmsBlockers::class)->activeTicketIds()->flip();
        $blockers = $cycle->issues->filter(fn ($issue) => $blockedIds->has($issue->id))->count();
        $requiredStagesComplete = collect($stages)->every(fn ($stage) => $stage['percent'] === null || $stage['percent'] === 100);
        $reasons = [];
        $health = 'On Track';
        if ($cycle->status === 'Closed') {
            $health = $requiredStagesComplete ? 'Completed' : 'Attention Required';
            if (! $requiredStagesComplete) $reasons[] = 'Cycle closed with stage work still outstanding.';
        } elseif ($today->gt($cycle->target_end_date) && ($total <= 0 || $work < 100)) {
            $health = 'Delayed';
            $reasons[] = $total > 0 ? 'The planned finish date has passed with unfinished work.' : 'The planned finish date has passed; no scoped work is recorded.';
        } elseif ($blockers > 0 || $reopens >= $reopenLimit || ($total > 0 && ($schedule - $work) >= $riskGap)) {
            $health = 'At Risk';
            if ($blockers > 0) {
                $reasons[] = "{$blockers} critical or blocker item(s) remain unresolved.";
            }
            if ($reopens >= $reopenLimit) {
                $reasons[] = "{$reopens} reopen event(s) exceed the configured threshold of {$reopenLimit}.";
            }
            if ($total > 0 && ($schedule - $work) >= $riskGap) {
                $reasons[] = 'Work completion trails elapsed schedule by '.round($schedule - $work, 1).' percentage points.';
            }
        } elseif ($total > 0 && ($schedule - $work) >= $attentionGap) {
            $health = 'Attention Required';
            $reasons[] = 'Work completion trails elapsed schedule by '.round($schedule - $work, 1).' percentage points.';
        }
        if ($reasons === []) {
            $reasons[] = $total > 0 ? 'No configured delay or quality threshold is currently breached.' : 'No scoped work is assigned; health is based on schedule only.';
        }

        $remainingDays = $eligible->filter(fn (CarbonImmutable $date) => $date->gte($today))->count();
        $forecast = $this->forecast($cycle, $eligible, $basis, $total, $completed, $elapsed, $settings);

        return compact('schedule', 'work', 'quality', 'basis', 'total', 'completed', 'qaPlanned', 'qaComplete', 'stages', 'currentPhase', 'health', 'reasons', 'remainingDays', 'blockers', 'reopens', 'forecast');
    }

    private function workUnits(Collection $issues): array
    {
        if ($issues->isNotEmpty() && $issues->every(fn ($issue) => $issue->story_points !== null)) {
            return ['story points', (float) $issues->sum('story_points'), (float) $issues->where('status', 'Production Done')->sum('story_points')];
        }
        if ($issues->isNotEmpty() && $issues->every(fn ($issue) => $issue->estimate_minutes !== null)) {
            return ['estimated minutes', (float) $issues->sum('estimate_minutes'), (float) $issues->where('status', 'Production Done')->sum('estimate_minutes')];
        }

        return ['issue count', (float) $issues->count(), (float) $issues->where('status', 'Production Done')->count()];
    }

    private function forecast(PmCycle $cycle, Collection $eligible, string $basis, float $total, float $completed, int $elapsed, object $settings): array
    {
        if ($total <= 0 || $completed <= 0 || $elapsed <= 0 || ! in_array($cycle->status, ['Active', 'Internal Testing', 'QAT'], true)) {
            return ['state' => 'Insufficient Data', 'date' => null, 'confidence' => null, 'basis' => "A forecast needs active-cycle throughput in {$basis}."];
        }
        $throughput = $completed / $elapsed;
        $daysNeeded = (int) ceil(($total - $completed) / max($throughput, 0.0001));
        $start = CarbonImmutable::today();
        $date = $this->advanceEligible($start, max(0, $daysNeeded - 1), $cycle->week_mode, $settings);
        $sample = (int) $completed;
        $confidence = $sample >= 10 ? 'High' : ($sample >= 5 ? 'Medium' : 'Low');

        return [
            'state' => $date->lte($cycle->target_end_date) ? 'On Track' : 'At Risk',
            'date' => $date->toDateString(),
            'confidence' => $confidence,
            'basis' => round($throughput, 2)." {$basis} completed per eligible day across {$elapsed} elapsed day(s).",
        ];
    }

    private function eligibleDates(mixed $start, mixed $end, string $mode, object $settings): Collection
    {
        $cursor = CarbonImmutable::parse($start)->startOfDay();
        $last = CarbonImmutable::parse($end)->startOfDay();
        $dates = collect();
        while ($cursor->lte($last)) {
            if ($this->eligible($cursor, $mode, $settings)) {
                $dates->push($cursor);
            }
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    private function advanceEligible(CarbonImmutable $start, int $increments, string $mode, object $settings): CarbonImmutable
    {
        $cursor = $start;
        while (! $this->eligible($cursor, $mode, $settings)) {
            $cursor = $cursor->addDay();
        }
        while ($increments > 0) {
            $cursor = $cursor->addDay();
            if ($this->eligible($cursor, $mode, $settings)) {
                $increments--;
            }
        }

        return $cursor;
    }

    private function eligible(CarbonImmutable $date, string $mode, object $settings): bool
    {
        if ($mode === 'calendar') {
            return true;
        }

        return ! in_array($date->dayOfWeek, $this->weekends($settings), true)
            && ! in_array($date->toDateString(), json_decode($settings->holidays ?? '[]', true) ?: [], true);
    }

    private function weekends(object $settings): array
    {
        return array_map('intval', json_decode($settings->weekend_days ?? '[0,6]', true) ?: [0, 6]);
    }
}
