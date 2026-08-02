<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Kid;
use App\Services\CycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class KidController extends Controller
{
    public function __construct(private readonly CycleService $cycle) {}

    public function show(Request $request): View
    {
        $kid = $this->kid($request);

        return view('kid.timer', [
            'kid' => $kid,
            'categories' => Category::where('active', true)->orderBy('name')->get(),
            'state' => $this->cycle->stateFor($kid),
            'summary' => $this->cycle->todayCategoryTotals($kid),
        ]);
    }

    /** Polled by the timer screen. */
    public function state(Request $request): JsonResponse
    {
        $kid = $this->kid($request);

        return response()->json([
            'state' => $this->cycle->stateFor($kid),
            'summary' => $this->cycle->todayCategoryTotals($kid),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $kid = $this->kid($request);
        $data = $request->validate([
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        try {
            $this->cycle->startWork($kid, array_map('intval', $data['category_ids']));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['state' => $this->cycle->stateFor($kid)]);
    }

    public function stop(Request $request): JsonResponse
    {
        $kid = $this->kid($request);
        $this->cycle->stopWork($kid);

        return response()->json(['state' => $this->cycle->stateFor($kid)]);
    }

    public function toggleTheme(Request $request): JsonResponse
    {
        $kid = $this->kid($request);
        $kid->dark_mode = ! $kid->dark_mode;
        $kid->save();

        return response()->json(['dark_mode' => $kid->dark_mode]);
    }

    /** A kid changes their own PIN (already signed in). */
    public function changePin(Request $request): RedirectResponse
    {
        $kid = $this->kid($request);
        $data = $request->validate([
            'pin' => ['required', 'digits_between:3,8', 'confirmed'],
        ], [
            'pin.confirmed' => 'The two PINs did not match.',
            'pin.digits_between' => 'PIN must be 3 to 8 digits.',
        ]);

        $kid->pin = Hash::make($data['pin']);
        $kid->save();

        return back()->with('status', 'Your PIN was changed.');
    }

    public function history(Request $request): View
    {
        $kid = $this->kid($request);

        $days = $kid->sessions()
            ->where('phase', 'work')
            ->orderByDesc('started_at')
            ->get()
            ->groupBy(fn ($s) => $s->started_at->toDateString())
            ->map(function ($sessions) {
                $byCategory = $sessions
                    ->groupBy(fn ($s) => $s->category_name ?? 'Unknown')
                    ->map(fn ($group) => $group->sum(fn ($s) => (int) $s->duration_seconds))
                    ->sortDesc();

                return [
                    'total' => $byCategory->sum(),
                    'categories' => $byCategory,
                ];
            });

        return view('kid.history', [
            'kid' => $kid,
            'days' => $days,
        ]);
    }

    private function kid(Request $request): Kid
    {
        return $request->attributes->get('kid');
    }
}
