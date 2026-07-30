@extends('layouts.app')

@section('title', 'Parent — Manifold Timer')

@php
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
            <div>Parent dashboard <small>Manifold Timer v{{ config('timer.version') }}</small></div>
        </div>
        <div style="display:flex; gap:10px">
            <a class="btn btn-ghost" href="{{ route('parent.reports') }}">Reports</a>
            <form method="POST" action="{{ route('parent.logout') }}">@csrf
                <button class="btn btn-ghost" type="submit">Log out</button>
            </form>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- LIVE STATUS --}}
    <p class="section-title">Live status</p>
    <div class="grid grid-2" id="liveGrid">
        @foreach ($kids as $row)
            @php($kid = $row['kid'])
            @php($st = $row['state'])
            <div class="card live" data-kid="{{ $kid->id }}" style="--accent: {{ $kid->color }}">
                <div class="live-head">
                    <span class="live-name">{{ $kid->name }}</span>
                    <span class="pill {{ $st['phase'] }}" data-role="pill">{{ ucfirst($st['phase']) }}</span>
                </div>
                <div class="live-time mono" data-role="time">—</div>
                <div class="muted" data-role="sub">&nbsp;</div>
                <div class="live-meta">
                    <span><b data-role="cycles">{{ $st['completed_cycles_today'] }}</b> cycles</span>
                    <span><b data-role="breaks">{{ $st['breaks_today'] }}</b> breaks</span>
                    <span data-role="cat">{{ $st['active_category'] ?? '' }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- KIDS --}}
    <p class="section-title" style="margin-top:30px">Kids</p>
    <div class="grid grid-2">
        @foreach ($kids as $row)
            @php($kid = $row['kid'])
            <div class="card" style="--accent: {{ $kid->color }}">
                <form method="POST" action="{{ route('parent.kids.update', $kid) }}" class="kid-edit">
                    @csrf @method('PATCH')
                    <div class="kid-edit-row">
                        <div style="flex:1">
                            <label>Name</label>
                            <input type="text" name="name" value="{{ $kid->name }}" maxlength="50">
                        </div>
                        <div>
                            <label>Colour</label>
                            <input type="color" name="color" value="{{ $kid->color }}" class="color-input">
                        </div>
                    </div>
                    @error('name')<div class="error">{{ $message }}</div>@enderror
                    @error('color')<div class="error">{{ $message }}</div>@enderror
                    <div class="kid-edit-actions">
                        <button class="btn btn-accent" type="submit">Save</button>
                    </div>
                </form>
                {{-- Per-kid cycle --}}
                <div class="kid-cycle">
                    <div class="kid-cycle-head">
                        <span class="section-title" style="margin:0">Cycle</span>
                        @if ($row['custom'])
                            <span class="pill work" style="font-size:.7rem">Custom</span>
                        @else
                            <span class="pill locked" style="font-size:.7rem">Using default</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('parent.kids.cycle.update', $kid) }}">
                        @csrf @method('PATCH')
                        <div class="cycle-grid">
                            <div>
                                <label>Work min</label>
                                <input type="number" name="work_minutes" min="1" max="1440" value="{{ $row['effective']->work_minutes }}">
                            </div>
                            <div>
                                <label>Break min</label>
                                <input type="number" name="break_minutes" min="1" max="1440" value="{{ $row['effective']->break_minutes }}">
                            </div>
                            <div>
                                <label>Cutoff</label>
                                <input type="time" name="cutoff_time" value="{{ \Illuminate\Support\Str::substr($row['effective']->cutoff_time, 0, 5) }}">
                            </div>
                        </div>
                        <div class="kid-edit-actions">
                            <button class="btn btn-accent" type="submit">Save custom cycle</button>
                        </div>
                    </form>
                    @if ($row['custom'])
                        <form method="POST" action="{{ route('parent.kids.cycle.reset', $kid) }}" style="margin-top:8px">
                            @csrf
                            <button class="btn btn-ghost" type="submit">Reset to default</button>
                        </form>
                    @endif
                </div>

                <div class="kid-edit-actions" style="border-top:1px solid var(--border); padding-top:14px; margin-top:16px">
                    <form method="POST" action="{{ route('parent.kids.open', $kid) }}">
                        @csrf
                        <button class="btn btn-ghost" type="submit" title="Open this kid's timer to test it">🔍 Open {{ $kid->name }}'s screen (test)</button>
                    </form>
                </div>
                <p class="muted" style="font-size:.82rem; margin:12px 0 0">PINs are changed by the kid on their own screen (⚙ button).</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-2" style="margin-top:26px">
        {{-- DEFAULT CYCLE SETTINGS --}}
        <div class="card">
            <p class="section-title">Default cycle settings</p>
            <p class="muted" style="margin:-6px 0 14px; font-size:.86rem">Applies to any kid without a custom cycle above.</p>
            <form method="POST" action="{{ route('parent.settings.update') }}" class="grid" style="gap:14px">
                @csrf
                <div>
                    <label>Work minutes (per phase)</label>
                    <input type="number" name="work_minutes" min="1" max="1440" value="{{ $settings->work_minutes }}">
                </div>
                <div>
                    <label>Break minutes</label>
                    <input type="number" name="break_minutes" min="1" max="1440" value="{{ $settings->break_minutes }}">
                </div>
                <div>
                    <label>Daily cutoff (lock time — 00:00 = reset at midnight)</label>
                    <input type="time" name="cutoff_time" value="{{ \Illuminate\Support\Str::substr($settings->cutoff_time, 0, 5) }}">
                </div>
                @error('work_minutes')<div class="error">{{ $message }}</div>@enderror
                @error('break_minutes')<div class="error">{{ $message }}</div>@enderror
                @error('cutoff_time')<div class="error">{{ $message }}</div>@enderror
                <button class="btn btn-accent" type="submit">Save settings</button>
            </form>
        </div>

        {{-- CATEGORIES --}}
        <div class="card">
            <p class="section-title">Categories</p>
            <div class="cat-list">
                @foreach ($categories as $c)
                    <div class="cat-manage {{ $c->active ? '' : 'inactive' }}">
                        <form method="POST" action="{{ route('parent.categories.update', $c) }}" class="cat-rename">
                            @csrf @method('PATCH')
                            <input type="text" name="name" value="{{ $c->name }}">
                            <button class="btn btn-ghost" type="submit">Rename</button>
                        </form>
                        <form method="POST" action="{{ route('parent.categories.update', $c) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="active" value="{{ $c->active ? 0 : 1 }}">
                            <button class="btn btn-ghost" type="submit">{{ $c->active ? 'Disable' : 'Enable' }}</button>
                        </form>
                        <form method="POST" action="{{ route('parent.categories.destroy', $c) }}"
                              onsubmit="return confirm('Remove {{ $c->name }}? History keeps its name.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost" type="submit" title="Delete">🗑</button>
                        </form>
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('parent.categories.store') }}" class="cat-add">
                @csrf
                <input type="text" name="name" placeholder="New category" maxlength="50">
                <button class="btn btn-accent" type="submit">Add</button>
            </form>
        </div>
    </div>

    {{-- TODAY'S SESSIONS (corrections) --}}
    <p class="section-title" style="margin-top:30px">Today's sessions — corrections</p>
    <div class="grid grid-2">
        @foreach ($kids as $row)
            @php($kid = $row['kid'])
            <div class="card">
                <div class="live-head" style="margin-bottom:14px">
                    <span class="live-name">{{ $kid->name }}</span>
                    <span class="muted">{{ $row['sessions']->count() }} today</span>
                </div>
                @forelse ($row['sessions'] as $s)
                    <div class="sess {{ $s->phase }}">
                        <div class="sess-top">
                            <span class="pill {{ $s->phase }}">{{ ucfirst($s->phase) }}</span>
                            <span class="muted">{{ $s->category_name ?? ($s->phase === 'break' ? 'Break' : '—') }}</span>
                            <span class="muted mono">{{ $s->ended_at ? $fmtSecs($s->duration_seconds) : 'running' }}</span>
                        </div>
                        <div class="sess-edit">
                            <form method="POST" action="{{ route('parent.sessions.update', $s) }}" class="sess-edit">
                                @csrf @method('PATCH')
                                <label>Start
                                    <input type="datetime-local" name="started_at" value="{{ $s->started_at->format('Y-m-d\TH:i') }}">
                                </label>
                                <label>End
                                    <input type="datetime-local" name="ended_at" value="{{ $s->ended_at?->format('Y-m-d\TH:i') }}">
                                </label>
                                <button class="btn btn-ghost" type="submit">Save</button>
                            </form>
                            <form method="POST" action="{{ route('parent.sessions.destroy', $s) }}"
                                  onsubmit="return confirm('Delete this session?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="muted">No sessions today.</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <style>
        .live-head { display: flex; align-items: center; justify-content: space-between; }
        .live-name { font-size: 1.3rem; font-weight: 750; }
        .live-time { font-size: 2.6rem; font-weight: 800; letter-spacing: -.03em; margin: 6px 0 2px; }
        .live-meta { display: flex; gap: 18px; margin-top: 12px; color: var(--text-muted); font-size: .92rem; }
        .live-meta b { color: var(--text); }

        .kid-edit-row { display: flex; gap: 12px; align-items: flex-end; }
        .color-input { width: 56px; height: 46px; padding: 4px; cursor: pointer; }
        .kid-edit-actions { display: flex; gap: 10px; margin-top: 14px; }
        .kid-cycle { border-top: 1px solid var(--border); margin-top: 16px; padding-top: 14px; }
        .kid-cycle-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .cycle-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }

        .cat-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
        .cat-manage { display: flex; gap: 8px; align-items: center; }
        .cat-manage.inactive .cat-rename input { opacity: .55; text-decoration: line-through; }
        .cat-rename { display: flex; gap: 8px; flex: 1; }
        .cat-rename input { flex: 1; }
        .cat-manage .btn { padding: 10px 12px; min-height: 44px; }
        .cat-add { display: flex; gap: 8px; }
        .cat-add input { flex: 1; }

        .sess { border: 1px solid var(--border); border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; }
        .sess.break { background: var(--break-soft); }
        .sess-top { display: flex; gap: 12px; align-items: center; margin-bottom: 10px; }
        .sess-edit { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .sess-edit label { margin: 0; }
        .sess-edit input { width: auto; }
        .sess-edit .btn { min-height: 44px; padding: 10px 14px; }
    </style>

    @push('scripts')
    <script>
    (function () {
        const stateUrl = @json(route('parent.state'));

        function fmt(total) {
            total = Math.max(0, Math.round(total));
            const m = Math.floor(total / 60), s = total % 60;
            const h = Math.floor(m / 60);
            return (h > 0 ? h + ':' : '') + (m % 60).toString().padStart(2, '0') + ':' + s.toString().padStart(2, '0');
        }

        // Per-kid local anchors so countdowns tick smoothly between polls.
        const kids = {};
        document.querySelectorAll('.live').forEach(c => kids[c.dataset.kid] = { card: c, state: null, anchor: performance.now() });

        function apply(id, state) {
            const k = kids[id];
            if (!k) return;
            k.state = state; k.anchor = performance.now();
            const q = sel => k.card.querySelector(`[data-role=${sel}]`);
            q('pill').className = 'pill ' + state.phase;
            q('pill').textContent = state.phase.charAt(0).toUpperCase() + state.phase.slice(1);
            q('cycles').textContent = state.completed_cycles_today;
            q('breaks').textContent = state.breaks_today;
            q('cat').textContent = state.is_running ? (state.active_category || '') : '';
        }

        function tick() {
            for (const id in kids) {
                const k = kids[id]; if (!k.state) continue;
                const st = k.state, el = k.card;
                const since = (performance.now() - k.anchor) / 1000;
                const time = el.querySelector('[data-role=time]');
                const sub = el.querySelector('[data-role=sub]');
                if (st.phase === 'locked') { time.textContent = '🌙'; sub.textContent = 'Done for today'; }
                else if (st.phase === 'break') {
                    time.textContent = fmt(st.break_remaining_seconds - since);
                    sub.textContent = 'break remaining';
                } else {
                    let remain = st.work_remaining_seconds - (st.is_running ? since : 0);
                    time.textContent = fmt(remain);
                    sub.textContent = st.is_running ? ('playing ' + (st.active_category || '')) : 'ready — not playing';
                }
            }
            requestAnimationFrame(tick);
        }

        async function poll() {
            try {
                const r = await fetch(stateUrl, { headers: { 'Accept': 'application/json' } });
                if (r.status === 401) { location.href = @json(route('parent.login')); return; }
                const data = await r.json();
                data.kids.forEach(k => apply(String(k.id), k.state));
            } catch (e) {}
        }

        // seed from server-rendered state, then poll
        @foreach ($kids as $row)
            apply(@json((string) $row['kid']->id), @json($row['state']));
        @endforeach
        requestAnimationFrame(tick);
        poll();
        setInterval(poll, 3000);
    })();
    </script>
    @endpush
@endsection
