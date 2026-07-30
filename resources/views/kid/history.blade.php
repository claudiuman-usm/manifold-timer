@extends('layouts.app')

@section('title', $kid->name.' — History')

@php
    $accent = $kid->color;
    $dark = $kid->dark_mode;
    $fmtSecs = function ($s) {
        $m = intdiv((int) $s, 60);
        if ($m < 60) return $m.' min';
        $h = intdiv($m, 60); $r = $m % 60;
        return $r ? "{$h}h {$r}m" : "{$h}h";
    };
@endphp

@section('content')
    <div class="topbar">
        <div class="brand">
            <span class="dot"></span>
            <div>{{ $kid->name }} <small>History</small></div>
        </div>
        <a class="btn btn-ghost" href="{{ route('kid.show') }}">← Timer</a>
    </div>

    <h1 class="h1">Your screen time</h1>

    @forelse ($days as $date => $day)
        <div class="card" style="margin-top:16px">
            <div class="day-head">
                <div class="day-date">{{ \Illuminate\Support\Carbon::parse($date)->isoFormat('ddd, D MMM YYYY') }}</div>
                <div class="day-total">{{ $fmtSecs($day['total']) }}</div>
            </div>
            <div class="cat-totals">
                @foreach ($day['categories'] as $cat => $secs)
                    <div class="cat-row"><span>{{ $cat }}</span><b>{{ $fmtSecs($secs) }}</b></div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card" style="margin-top:16px"><p class="muted">No history yet — go play!</p></div>
    @endforelse

    <style>
        .day-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px; }
        .day-date { font-weight: 750; font-size: 1.05rem; }
        .day-total { font-weight: 800; color: var(--accent); font-variant-numeric: tabular-nums; }
        .cat-totals { display: flex; flex-direction: column; gap: 8px; }
        .cat-row { display: flex; justify-content: space-between; padding: 10px 12px; background: var(--surface-2); border-radius: 12px; }
        .cat-row b { font-variant-numeric: tabular-nums; }
    </style>
@endsection
