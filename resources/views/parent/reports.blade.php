@extends('layouts.app')

@section('title', 'Reports — Manifold Timer')

@php
    $fmtSecs = function ($s) {
        $m = intdiv((int) $s, 60);
        if ($m < 60) return $m.' min';
        $h = intdiv($m, 60); $r = $m % 60;
        return $r ? "{$h}h {$r}m" : "{$h}h";
    };
    $kidsById = $kids->keyBy('id');
@endphp

@section('content')
    <div class="topbar">
        <div class="brand">
            <span class="dot"></span>
            <div>Reports <small>By day &amp; week</small></div>
        </div>
        <a class="btn btn-ghost" href="{{ route('parent.dashboard') }}">← Dashboard</a>
    </div>

    {{-- WEEKLY --}}
    <div class="card">
        <p class="section-title">Weekly totals (last 8 weeks)</p>
        @if (empty($weekly))
            <p class="muted">No completed sessions yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Week</th>
                        @foreach ($kids as $kid)<th class="num">{{ $kid->name }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weekly as $week => $perKid)
                        <tr>
                            <td class="mono">{{ $week }}</td>
                            @foreach ($kids as $kid)
                                <td class="num mono">{{ isset($perKid[$kid->id]) ? $fmtSecs($perKid[$kid->id]) : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- DAILY --}}
    <div class="card" style="margin-top:22px">
        <p class="section-title">Daily breakdown (last 14 days)</p>
        @if (empty($daily))
            <p class="muted">No completed sessions yet.</p>
        @else
            @foreach ($daily as $date => $perKid)
                <div class="day-block">
                    <div class="day-date">{{ \Illuminate\Support\Carbon::parse($date)->isoFormat('ddd, D MMM') }}</div>
                    <div class="grid grid-2">
                        @foreach ($kids as $kid)
                            <div class="day-kid" style="border-left:4px solid {{ $kid->color }}">
                                <div class="day-kid-head">
                                    <b>{{ $kid->name }}</b>
                                    <span class="muted mono">{{ isset($perKid[$kid->id]) ? $fmtSecs(array_sum($perKid[$kid->id])) : '0 min' }}</span>
                                </div>
                                @if (isset($perKid[$kid->id]))
                                    @foreach (collect($perKid[$kid->id])->sortDesc() as $cat => $secs)
                                        <div class="cat-row"><span>{{ $cat }}</span><b>{{ $fmtSecs($secs) }}</b></div>
                                    @endforeach
                                @else
                                    <div class="muted" style="font-size:.88rem">Nothing logged.</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <style>
        .day-block { padding: 14px 0; border-bottom: 1px solid var(--border); }
        .day-block:last-child { border-bottom: 0; }
        .day-date { font-weight: 750; margin-bottom: 12px; }
        .day-kid { background: var(--surface-2); border-radius: 12px; padding: 12px 14px; }
        .day-kid-head { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .cat-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: .92rem; }
        .cat-row b { font-variant-numeric: tabular-nums; }
    </style>
@endsection
