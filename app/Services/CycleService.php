<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CycleSetting;
use App\Models\Kid;
use App\Models\TimerSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The heart of Manifold Timer.
 *
 * Given a kid and the *server* clock, this service derives the child's current
 * cycle state (work / break / locked) purely from today's stored sessions plus
 * the global CycleSetting. The client only displays; the server decides.
 *
 * Representation choices:
 *  - A running WORK segment is a `timer_sessions` row with `ended_at IS NULL`.
 *    This is the only kind of "running segment" — at most one per kid.
 *  - A BREAK is a stored session whose `ended_at` is fixed at creation
 *    (`started_at + break_minutes`). Breaks count down by server clock and end
 *    automatically; they are never "running" in the ended_at-null sense.
 */
class CycleService
{
    /**
     * Public entry point: reconcile any automatic transitions, then return state.
     *
     * @return array<string,mixed>
     */
    public function stateFor(Kid $kid): array
    {
        $this->reconcile($kid);

        return $this->computeState($kid, $this->now());
    }

    /* ------------------------------------------------------------------ */
    /* Actions */
    /* ------------------------------------------------------------------ */

    /**
     * Start a work segment for the kid in the given categories. Multiple
     * categories are stored as one combined label snapshot (e.g. "TV + iPad").
     *
     * @param  array<int>  $categoryIds
     *
     * @throws RuntimeException on any rule violation.
     */
    public function startWork(Kid $kid, array $categoryIds): TimerSession
    {
        return DB::transaction(function () use ($kid, $categoryIds) {
            // Lock the kid row so two rapid taps can't open two segments.
            Kid::whereKey($kid->getKey())->lockForUpdate()->first();

            $this->reconcile($kid);
            $state = $this->computeState($kid, $this->now());

            if ($state['locked']) {
                throw new RuntimeException('Done for today — locked until tomorrow.');
            }
            if ($state['phase'] === 'break') {
                throw new RuntimeException('On a forced break. Start is disabled until the break ends.');
            }
            if ($state['is_running']) {
                throw new RuntimeException('A timer is already running.');
            }

            $categories = Category::where('active', true)
                ->whereIn('id', $categoryIds)
                ->orderBy('name')
                ->get();

            if ($categories->isEmpty()) {
                throw new RuntimeException('Pick at least one thing to play.');
            }

            return TimerSession::create([
                'kid_id' => $kid->id,
                'category_id' => $categories->first()->id,          // FK: first of the selection
                'category_name' => $categories->pluck('name')->implode(' + '), // combined snapshot
                'phase' => 'work',
                'started_at' => $this->now(),
                'ended_at' => null,
            ]);
        });
    }

    /**
     * Stop the currently running work segment. If this completes the work phase
     * (summed work >= work_minutes), a forced break is opened immediately.
     */
    public function stopWork(Kid $kid): void
    {
        DB::transaction(function () use ($kid) {
            Kid::whereKey($kid->getKey())->lockForUpdate()->first();

            $now = $this->now();
            $running = $this->runningWork($kid);
            if (! $running) {
                return; // nothing to stop — idempotent
            }

            $running->ended_at = $now;
            $running->duration_seconds = max(0, $now->diffInSeconds($running->started_at, true));
            $running->save();

            $settings = $this->settingsFor($kid);
            $phaseWork = $this->completedWorkSecondsInCurrentPhase($kid, $now);

            if ($phaseWork >= $settings->work_minutes * 60) {
                $this->openBreak($kid, $now, $settings);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Reconciliation (automatic, time-driven transitions) */
    /* ------------------------------------------------------------------ */

    /**
     * Apply any transitions the passage of time has forced:
     *  - close an open work segment at the day's lock instant (midnight/cutoff);
     *  - close an open work segment at the exact moment it reaches work_minutes
     *    and open the forced break at that instant.
     */
    public function reconcile(Kid $kid): void
    {
        $now = $this->now();
        $running = $this->runningWork($kid);
        if (! $running) {
            return;
        }

        $settings = $this->settingsFor($kid);
        $lockInstant = $this->lockInstantForDay($running->started_at->copy()->startOfDay(), $settings);

        // 1) Midnight / cutoff lock — cut the segment off at the boundary.
        if ($now->greaterThanOrEqualTo($lockInstant)) {
            $this->closeSegmentAt($running, $lockInstant);

            return;
        }

        // 2) Work-phase completion — cap the segment at the threshold instant
        //    and open the forced break there.
        $priorWork = $this->completedWorkSecondsInCurrentPhase($kid, $now, excludeRunning: true);
        $remaining = $settings->work_minutes * 60 - $priorWork;

        if ($remaining <= 0) {
            // Threshold already met by prior segments — complete right away.
            $this->closeSegmentAt($running, $running->started_at);
            $this->openBreak($kid, $running->started_at, $settings);

            return;
        }

        $thresholdAt = $running->started_at->copy()->addSeconds($remaining);
        if ($now->greaterThanOrEqualTo($thresholdAt)) {
            $this->closeSegmentAt($running, $thresholdAt);
            $this->openBreak($kid, $thresholdAt, $settings);
        }
    }

    private function closeSegmentAt(TimerSession $segment, Carbon $at): void
    {
        $segment->ended_at = $at;
        $segment->duration_seconds = max(0, $at->diffInSeconds($segment->started_at, true));
        $segment->save();
    }

    private function openBreak(Kid $kid, Carbon $startAt, object $settings): TimerSession
    {
        $end = $startAt->copy()->addSeconds($settings->break_minutes * 60);

        return TimerSession::create([
            'kid_id' => $kid->id,
            'category_id' => null,
            'category_name' => null,
            'phase' => 'break',
            'started_at' => $startAt,
            'ended_at' => $end, // break length locked in at start
            'duration_seconds' => $settings->break_minutes * 60,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* State derivation (pure read) */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string,mixed>
     */
    public function computeState(Kid $kid, Carbon $now): array
    {
        $settings = $this->settingsFor($kid);
        $workSeconds = $settings->work_minutes * 60;
        $breakSeconds = $settings->break_minutes * 60;

        $sessions = $this->todaySessions($kid, $now);

        $phaseWork = 0;          // completed work secs in the current phase
        $completedCycles = 0;
        $breaksToday = 0;
        $running = null;
        $lastBreak = null;

        foreach ($sessions as $s) {
            if ($s->phase === 'work') {
                if ($s->ended_at === null) {
                    $running = $s;
                } else {
                    $phaseWork += (int) $s->duration_seconds;
                }
            } else { // break
                $breaksToday++;
                $completedCycles++;
                $phaseWork = 0; // a break resets the work accumulator
                $lastBreak = $s;
            }
        }

        $locked = $now->greaterThanOrEqualTo(
            $this->lockInstantForDay($now->copy()->startOfDay(), $settings)
        );

        $inBreak = $lastBreak !== null && $now->lessThan($lastBreak->ended_at);

        $liveWork = $running ? (int) max(0, round($now->diffInSeconds($running->started_at, true))) : 0;
        $workElapsed = $phaseWork + $liveWork;
        $workRemaining = max(0, $workSeconds - $workElapsed);

        if ($locked) {
            $phase = 'locked';
        } elseif ($inBreak) {
            $phase = 'break';
        } else {
            $phase = 'work';
        }

        return [
            'phase' => $phase,
            'locked' => $locked,
            'is_running' => $running !== null && ! $locked,
            'work_elapsed_seconds' => $workElapsed,
            'work_remaining_seconds' => $workRemaining,
            'work_total_seconds' => $workSeconds,
            'break_remaining_seconds' => $inBreak ? (int) max(0, round($now->diffInSeconds($lastBreak->ended_at, true))) : 0,
            'break_total_seconds' => $breakSeconds,
            'active_category' => $running?->category_name,
            'active_category_id' => $running?->category_id,
            'active_started_at' => $running?->started_at?->toIso8601String(),
            'completed_cycles_today' => $completedCycles,
            'breaks_today' => $breaksToday,
            'server_now' => $now->toIso8601String(),
        ];
    }

    /**
     * Per-category work seconds for the kid today (includes the live running
     * segment), for the "today's summary" panel.
     *
     * @return array<int,array{category:string,seconds:int}>
     */
    public function todayCategoryTotals(Kid $kid, ?Carbon $now = null): array
    {
        $now ??= $this->now();
        $totals = [];

        foreach ($this->todaySessions($kid, $now) as $s) {
            if ($s->phase !== 'work') {
                continue;
            }
            $name = $s->category_name ?? 'Unknown';
            $seconds = $s->ended_at === null
                ? (int) max(0, round($now->diffInSeconds($s->started_at, true)))
                : (int) $s->duration_seconds;
            $totals[$name] = ($totals[$name] ?? 0) + $seconds;
        }

        return collect($totals)
            ->map(fn ($seconds, $category) => ['category' => $category, 'seconds' => $seconds])
            ->sortByDesc('seconds')
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    public function now(): Carbon
    {
        return Carbon::now();
    }

    /**
     * Effective cycle settings for a kid: the kid's own override where set,
     * otherwise the global default. Returned as an object so callers can read
     * ->work_minutes / ->break_minutes / ->cutoff_time uniformly.
     */
    public function settingsFor(Kid $kid): object
    {
        $global = CycleSetting::current();

        return (object) [
            'work_minutes' => $kid->work_minutes ?? $global->work_minutes,
            'break_minutes' => $kid->break_minutes ?? $global->break_minutes,
            'cutoff_time' => $kid->cutoff_time ?? $global->cutoff_time,
        ];
    }

    private function runningWork(Kid $kid): ?TimerSession
    {
        return $kid->sessions()->running()->work()->latest('started_at')->first();
    }

    /** Today's sessions (by server date), ordered chronologically. */
    private function todaySessions(Kid $kid, Carbon $now): Collection
    {
        return $kid->sessions()
            ->whereDate('started_at', $now->toDateString())
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Completed (ended) work seconds accumulated in the current work phase —
     * i.e. work since the most recent break today.
     */
    private function completedWorkSecondsInCurrentPhase(Kid $kid, Carbon $now, bool $excludeRunning = true): int
    {
        $phaseWork = 0;
        foreach ($this->todaySessions($kid, $now) as $s) {
            if ($s->phase === 'break') {
                $phaseWork = 0;

                continue;
            }
            if ($s->ended_at === null) {
                if (! $excludeRunning) {
                    $phaseWork += max(0, $now->diffInSeconds($s->started_at, true));
                }

                continue;
            }
            $phaseWork += (int) $s->duration_seconds;
        }

        return $phaseWork;
    }

    /**
     * The instant a given calendar day locks.
     *  - cutoff 00:00 → the following midnight (pure daily reset, no early lock).
     *  - any other time → that time-of-day on the given day (early "done for today").
     */
    private function lockInstantForDay(Carbon $day, object $settings): Carbon
    {
        $cutoff = CarbonImmutable::parse($settings->cutoff_time);
        $isMidnight = $cutoff->hour === 0 && $cutoff->minute === 0 && $cutoff->second === 0;

        if ($isMidnight) {
            return $day->copy()->addDay()->startOfDay();
        }

        return $day->copy()->setTime($cutoff->hour, $cutoff->minute, $cutoff->second);
    }
}
