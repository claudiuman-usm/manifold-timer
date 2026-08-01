<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CycleSetting;
use App\Models\Kid;
use App\Models\TimerSession;
use App\Services\CycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ParentController extends Controller
{
    public function __construct(private readonly CycleService $cycle) {}

    public function dashboard(): View
    {
        $kids = Kid::orderBy('name')->get();

        return view('parent.dashboard', [
            'kids' => $kids->map(fn (Kid $kid) => [
                'kid' => $kid,
                'state' => $this->cycle->stateFor($kid),
                'summary' => $this->cycle->todayCategoryTotals($kid),
                'effective' => $this->cycle->settingsFor($kid),
                'custom' => $kid->hasCycleOverride(),
                'sessions' => $kid->sessions()
                    ->whereDate('started_at', Carbon::now()->toDateString())
                    ->orderByDesc('started_at')
                    ->get(),
            ]),
            'settings' => CycleSetting::current(),
            'categories' => Category::orderBy('name')->get(),
            'notifications' => $this->recentCycleCompletions($kids),
        ]);
    }

    /**
     * History for the notification bell: every completed work cycle over the
     * last 7 days. A cycle completes exactly when a `break` session opens, so
     * each break row is one "kid finished a cycle" event.
     *
     * @param  \Illuminate\Support\Collection<int,Kid>  $kids
     * @return array<int,array{kid:string,kid_id:int,color:string,at:string}>
     */
    private function recentCycleCompletions($kids): array
    {
        $byId = $kids->keyBy('id');

        return TimerSession::query()
            ->where('phase', 'break')
            ->where('started_at', '>=', Carbon::now()->subDays(7)->startOfDay())
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (TimerSession $s) => [
                'kid' => $byId[$s->kid_id]->name ?? 'A kid',
                'kid_id' => (int) $s->kid_id,
                'color' => $byId[$s->kid_id]->color ?? '#888',
                'at' => $s->started_at->toIso8601String(),
            ])
            ->all();
    }

    /** Live status of both kids, polled by the dashboard. */
    public function state(): JsonResponse
    {
        $kids = Kid::orderBy('name')->get();

        return response()->json([
            'kids' => $kids->map(fn (Kid $kid) => [
                'id' => $kid->id,
                'name' => $kid->name,
                'color' => $kid->color,
                'state' => $this->cycle->stateFor($kid),
                'summary' => $this->cycle->todayCategoryTotals($kid),
            ]),
        ]);
    }

    public function reports(Request $request): View
    {
        $kids = Kid::orderBy('name')->get();

        // Daily breakdown for the last 14 days.
        $since = Carbon::now()->subDays(13)->startOfDay();
        $work = TimerSession::query()
            ->where('phase', 'work')
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', $since)
            ->get();

        $daily = [];
        foreach ($work as $s) {
            $day = $s->started_at->toDateString();
            $cat = $s->category_name ?? 'Unknown';
            $daily[$day][$s->kid_id][$cat] = ($daily[$day][$s->kid_id][$cat] ?? 0) + (int) $s->duration_seconds;
        }
        krsort($daily);

        // Weekly totals per kid for the last 8 weeks.
        $weekly = [];
        foreach ($work->where('started_at', '>=', Carbon::now()->subWeeks(8)->startOfWeek()) as $s) {
            $week = $s->started_at->format('o-\WW'); // ISO year-week
            $weekly[$week][$s->kid_id] = ($weekly[$week][$s->kid_id] ?? 0) + (int) $s->duration_seconds;
        }
        krsort($weekly);

        return view('parent.reports', [
            'kids' => $kids,
            'daily' => $daily,
            'weekly' => $weekly,
        ]);
    }

    /* ----- Kids ----- */

    public function updateKid(Request $request, Kid $kid): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $kid->update($data);

        return back()->with('status', $kid->name.' updated.');
    }

    /** Set a kid's custom cycle settings (overrides the global default). */
    public function updateKidCycle(Request $request, Kid $kid): RedirectResponse
    {
        $data = $request->validate([
            'work_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'break_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'cutoff_time' => ['required', 'date_format:H:i'],
        ]);

        $kid->update([
            'work_minutes' => $data['work_minutes'],
            'break_minutes' => $data['break_minutes'],
            'cutoff_time' => $data['cutoff_time'].':00',
        ]);

        return back()->with('status', $kid->name."'s custom cycle saved.");
    }

    /** Clear a kid's overrides so they follow the global default again. */
    public function resetKidCycle(Kid $kid): RedirectResponse
    {
        $kid->update([
            'work_minutes' => null,
            'break_minutes' => null,
            'cutoff_time' => null,
        ]);

        return back()->with('status', $kid->name.' now uses the default cycle.');
    }

    /** Sign the parent's browser in as a kid, for testing that kid's screen. */
    public function openKid(Request $request, Kid $kid): RedirectResponse
    {
        $request->session()->put('kid_id', $kid->id);

        return redirect()->route('kid.show');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'break_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'cutoff_time' => ['required', 'date_format:H:i'],
        ]);

        $settings = CycleSetting::current();
        $settings->update([
            'work_minutes' => $data['work_minutes'],
            'break_minutes' => $data['break_minutes'],
            'cutoff_time' => $data['cutoff_time'].':00',
        ]);

        return back()->with('status', 'Cycle settings saved.');
    }

    /* ----- Categories ----- */

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);
        Category::create(['name' => $data['name'], 'active' => true]);

        return back()->with('status', 'Category added.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $category->update($data);

        return back()->with('status', 'Category updated.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        $category->delete(); // soft delete — history keeps snapshotted names

        return back()->with('status', 'Category removed.');
    }

    /* ----- Sessions (parent corrections) ----- */

    public function updateSession(Request $request, TimerSession $session): RedirectResponse
    {
        $data = $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
        ]);

        $started = Carbon::parse($data['started_at']);
        $ended = $data['ended_at'] ? Carbon::parse($data['ended_at']) : null;

        $session->started_at = $started;
        $session->ended_at = $ended;
        $session->duration_seconds = $ended ? max(0, $ended->diffInSeconds($started, true)) : null;
        $session->save();

        return back()->with('status', 'Session updated.');
    }

    public function destroySession(TimerSession $session): RedirectResponse
    {
        $session->delete(); // soft delete

        return back()->with('status', 'Session deleted.');
    }
}
