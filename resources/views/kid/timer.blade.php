@extends('layouts.app')

@section('title', $kid->name.' — Timer')

@php($accent = $kid->color)
@php($dark = $kid->dark_mode)

@section('content')
    <div class="topbar">
        <div class="brand">
            <span class="dot"></span>
            <div>{{ $kid->name }} <small id="phaseLabel">Loading…</small></div>
        </div>
        <div class="nav-icons">
            <a class="nav-icon" href="{{ route('kid.history') }}" title="History" aria-label="History">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 3a9 9 0 00-9 9H1l3.9 3.9.1.1L9 12H6a7 7 0 117 7c-1.9 0-3.6-.8-4.8-2l-1.5 1.5A9 9 0 1013 3zm-1 5v5l4.3 2.5.7-1.2-3.5-2.1V8h-1.5z"/></svg>
            </a>
            <button class="nav-icon" id="pinBtn" title="Change my PIN" aria-label="Change my PIN">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M14 2a6 6 0 00-5.7 7.9L2 16.2V22h5.8l.6-.6V19H11v-2.4l1.1-1.1A6 6 0 1014 2zm2.5 3.5a1.5 1.5 0 11-2.1 2.1 1.5 1.5 0 012.1-2.1z"/></svg>
            </button>
            <button class="nav-icon" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
                <svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 109 9c0-.46-.03-.92-.1-1.36A6 6 0 0111.36 3.1 9.05 9.05 0 0012 3z"/></svg>
                <svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9a3 3 0 100 6 3 3 0 000-6zM12 1.5l1.6 3h-3.2zM12 22.5l-1.6-3h3.2zM1.5 12l3-1.6v3.2zM22.5 12l-3 1.6v-3.2zM4.6 4.6l3.1 1.1-2 2zM19.4 19.4l-3.1-1.1 2-2zM4.6 19.4l1.1-3.1 2 2zM19.4 4.6l-1.1 3.1-2-2z"/></svg>
            </button>
            <form method="POST" action="{{ route('kid.logout') }}">@csrf
                <button class="nav-icon" type="submit" title="Exit" aria-label="Exit">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 3H6a2 2 0 00-2 2v14a2 2 0 002 2h4v-2H6V5h4V3z"/><path d="M15 7l-1.4 1.4L16.2 11H9v2h7.2l-2.6 2.6L15 17l5-5-5-5z"/></svg>
                </button>
            </form>
        </div>
    </div>

    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif

    {{-- Change-PIN modal --}}
    <div class="modal" id="pinModal" @if (! $errors->has('pin')) hidden @endif>
        <div class="modal-card card">
            <p class="section-title" style="margin-bottom:16px">Change my PIN</p>
            <form method="POST" action="{{ route('kid.changePin') }}">
                @csrf
                <label>New PIN</label>
                <input type="password" inputmode="numeric" autocomplete="off" name="pin" placeholder="3–8 digits" style="text-align:center; letter-spacing:.3em; font-size:1.3rem">
                <label style="margin-top:12px">Repeat new PIN</label>
                <input type="password" inputmode="numeric" autocomplete="off" name="pin_confirmation" placeholder="repeat" style="text-align:center; letter-spacing:.3em; font-size:1.3rem">
                @error('pin')<div class="error">{{ $message }}</div>@enderror
                <div style="display:flex; gap:10px; margin-top:18px">
                    <button class="btn btn-ghost" type="button" id="pinCancel" style="flex:1">Cancel</button>
                    <button class="btn btn-accent" type="submit" style="flex:1">Save PIN</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toasts" class="toasts" aria-live="assertive"></div>

    <div class="stage card" id="stage" data-phase="">
        <div class="phase-badge"><span class="pill" id="phasePill">—</span></div>

        <div class="ring" id="ring" style="--pct: 0">
            <div class="ring-inner">
                <div class="time mono" id="bigTime">00:00</div>
                <div class="time-sub" id="timeSub">&nbsp;</div>
            </div>
        </div>

        {{-- WORK · IDLE --}}
        <div class="panel" data-panel="work-idle" hidden>
            <p class="section-title" style="text-align:center">Pick what you're doing</p>
            <div class="cats">
                @foreach ($categories as $c)
                    <button class="cat" data-cat="{{ $c->id }}">{{ $c->name }}</button>
                @endforeach
            </div>
            <button class="btn btn-lg btn-work" id="startBtn" disabled>Start</button>
        </div>

        {{-- WORK · RUNNING --}}
        <div class="panel" data-panel="work-running" hidden>
            <p class="running-cat">Playing <b id="runningCat">—</b></p>
            <button class="btn btn-lg btn-danger" id="stopBtn">Stop</button>
        </div>

        {{-- BREAK --}}
        <div class="panel" data-panel="break" hidden>
            <p class="break-msg">Break time — have a rest 🌿<br><span class="muted">Start is off until the break ends.</span></p>
        </div>

        {{-- LOCKED --}}
        <div class="panel" data-panel="locked" hidden>
            <p class="break-msg">Done for today 🌙<br><span class="muted">See you tomorrow!</span></p>
        </div>
    </div>

    {{-- Today's summary --}}
    <div class="card" style="margin-top:18px">
        <p class="section-title">Today</p>
        <div class="stats">
            <div class="stat"><div class="stat-num" id="statCycles">0</div><div class="muted">cycles done</div></div>
            <div class="stat"><div class="stat-num" id="statBreaks">0</div><div class="muted">breaks taken</div></div>
        </div>
        <div id="catTotals" class="cat-totals"></div>
    </div>

    <style>
        .nav-icons { display: flex; align-items: center; gap: 6px; }
        .nav-icons form { display: flex; }
        .nav-icon {
            background: none; border: 0; padding: 8px; margin: 0; cursor: pointer;
            color: var(--text-muted); line-height: 0; border-radius: 12px;
            display: inline-flex; align-items: center; justify-content: center;
            transition: color .15s ease, transform .06s ease;
        }
        .nav-icon svg { width: 32px; height: 32px; fill: currentColor; display: block; }
        .nav-icon:hover { color: var(--accent); }
        .nav-icon:active { transform: scale(.9); }
        .nav-icon .icon-moon, .nav-icon .icon-sun { display: none; }
        html[data-theme="light"] .nav-icon .icon-moon { display: block; }
        html[data-theme="dark"] .nav-icon .icon-sun { display: block; }

        .stage { margin-top: 6px; text-align: center; padding-bottom: 34px; transition: background .3s ease; }
        .stage[data-phase="break"] { background: color-mix(in srgb, var(--break) 8%, var(--surface)); border-color: color-mix(in srgb, var(--break) 35%, var(--border)); }
        .stage[data-phase="locked"] { background: var(--locked-soft); }
        .phase-badge { margin-bottom: 8px; }

        .ring {
            --size: min(74vw, 320px);
            width: var(--size); height: var(--size); border-radius: 50%; margin: 10px auto 6px;
            background: conic-gradient(var(--ring-color, var(--accent)) calc(var(--pct) * 1%), var(--surface-2) 0);
            display: grid; place-items: center; transition: background .4s linear;
        }
        .ring-inner {
            width: calc(var(--size) - 30px); height: calc(var(--size) - 30px); border-radius: 50%;
            background: var(--surface); box-shadow: inset 0 2px 10px rgba(0,0,0,.04);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 6px; padding: 0 6%; text-align: center;
        }
        .stage[data-phase="work"] { --ring-color: var(--work); }
        .stage[data-phase="break"] { --ring-color: var(--break); }
        .stage[data-phase="locked"] { --ring-color: var(--locked); }

        .time {
            font-size: clamp(2rem, 9vw, 3.4rem); font-weight: 800; letter-spacing: -.03em;
            line-height: 1; white-space: nowrap; max-width: 100%;
        }
        .time-sub {
            font-size: clamp(.95rem, 4vw, 1.15rem); font-weight: 800; letter-spacing: .01em;
            color: var(--ring-color, var(--accent)); font-variant-numeric: tabular-nums; white-space: nowrap;
        }

        .panel { margin-top: 26px; max-width: 460px; margin-left: auto; margin-right: auto; }
        .cats { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .cat {
            appearance: none; cursor: pointer; font: inherit; font-weight: 700; font-size: 1.1rem;
            padding: 20px 12px; border-radius: 16px; border: 2px solid var(--border);
            background: var(--surface-2); color: var(--text); transition: all .12s ease;
        }
        .cat[aria-pressed="true"] { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 16%, var(--surface)); color: var(--accent); }
        .running-cat { font-size: 1.3rem; margin: 0 0 18px; }
        .running-cat b { color: var(--accent); }
        .break-msg { font-size: 1.4rem; font-weight: 700; line-height: 1.5; }

        .stats { display: flex; gap: 14px; margin-bottom: 16px; }
        .stat { flex: 1; background: var(--surface-2); border-radius: 14px; padding: 16px; text-align: center; }
        .stat-num { font-size: 2rem; font-weight: 800; }
        .cat-totals { display: flex; flex-direction: column; gap: 8px; }
        .cat-row { display: flex; justify-content: space-between; padding: 10px 12px; background: var(--surface-2); border-radius: 12px; }
        .cat-row b { font-variant-numeric: tabular-nums; }
        .cat-empty { color: var(--text-muted); padding: 6px 2px; }

        .modal { position: fixed; inset: 0; background: rgba(10,14,22,.55); display: grid; place-items: center; z-index: 50; padding: 20px; }
        .modal[hidden] { display: none; }
        .modal-card { width: 100%; max-width: 360px; border-top: 6px solid var(--accent); }

        .toasts { position: fixed; left: 50%; top: 18px; transform: translateX(-50%); z-index: 60; display: flex; flex-direction: column; gap: 10px; width: min(92vw, 460px); pointer-events: none; }
        .toast {
            pointer-events: auto; border-radius: 16px; padding: 16px 20px; box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 14px; font-weight: 650;
            background: var(--surface); border: 1px solid var(--border); color: var(--text);
            animation: toast-in .28s ease both;
        }
        .toast .t-emoji { font-size: 1.8rem; line-height: 1; }
        .toast .t-title { font-weight: 800; font-size: 1.05rem; }
        .toast .t-body { color: var(--text-muted); font-weight: 600; font-size: .9rem; }
        .toast.warn { border-color: color-mix(in srgb, var(--break) 55%, transparent); background: var(--break-soft); }
        .toast.timeup { border-color: color-mix(in srgb, var(--danger) 55%, transparent); background: color-mix(in srgb, var(--danger) 12%, var(--surface)); }
        .toast.ok { border-color: color-mix(in srgb, var(--work) 55%, transparent); background: var(--work-soft); }
        .toast.leaving { animation: toast-out .3s ease both; }
        @keyframes toast-in { from { opacity: 0; transform: translateY(-14px) scale(.96); } to { opacity: 1; transform: none; } }
        @keyframes toast-out { to { opacity: 0; transform: translateY(-14px) scale(.96); } }
    </style>

    @push('scripts')
    <script>
    (function () {
        const csrf = document.querySelector('meta[name=csrf-token]').content;
        const routes = {
            state: @json(route('kid.state')),
            start: @json(route('kid.start')),
            stop:  @json(route('kid.stop')),
            theme: @json(route('kid.theme')),
        };

        const el = {
            stage: document.getElementById('stage'),
            ring: document.getElementById('ring'),
            bigTime: document.getElementById('bigTime'),
            timeSub: document.getElementById('timeSub'),
            phasePill: document.getElementById('phasePill'),
            phaseLabel: document.getElementById('phaseLabel'),
            startBtn: document.getElementById('startBtn'),
            stopBtn: document.getElementById('stopBtn'),
            runningCat: document.getElementById('runningCat'),
            statCycles: document.getElementById('statCycles'),
            statBreaks: document.getElementById('statBreaks'),
            catTotals: document.getElementById('catTotals'),
            themeToggle: document.getElementById('themeToggle'),
        };
        const panels = {};
        document.querySelectorAll('[data-panel]').forEach(p => panels[p.dataset.panel] = p);

        let state = @json($state);
        let summary = @json($summary);
        let anchor = performance.now();   // client time when state was received
        let chosenCat = null;
        let busy = false;

        function fmt(total) {
            total = Math.max(0, Math.round(total));
            const m = Math.floor(total / 60), s = total % 60;
            const h = Math.floor(m / 60);
            const mm = (m % 60).toString().padStart(2, '0');
            const ss = s.toString().padStart(2, '0');
            return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
        }
        function fmtLong(total) {
            const m = Math.round(total / 60);
            if (m < 60) return `${m} min`;
            const h = Math.floor(m / 60), r = m % 60;
            return r ? `${h}h ${r}m` : `${h}h`;
        }
        function fmtHM(total) {
            total = Math.max(0, Math.round(total));
            const h = Math.floor(total / 3600);
            const m = Math.floor((total % 3600) / 60);
            return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
        }

        function setState(s, sum) {
            state = s;
            if (sum) summary = sum;
            anchor = performance.now();
            render();
            checkTransitions(s);
        }

        function elapsedSince() { return (performance.now() - anchor) / 1000; }

        function showPanel(name) {
            for (const k in panels) panels[k].hidden = (k !== name);
        }

        function render() {
            const p = state.phase;
            el.stage.dataset.phase = p;

            el.statCycles.textContent = state.completed_cycles_today;
            el.statBreaks.textContent = state.breaks_today;
            renderSummary();

            if (p === 'locked') {
                el.phasePill.className = 'pill locked'; el.phasePill.textContent = 'Locked';
                el.phaseLabel.textContent = 'Done for today';
                el.bigTime.textContent = '🌙';
                el.timeSub.textContent = '';
                el.ring.style.setProperty('--pct', 100);
                showPanel('locked');
                return;
            }

            if (p === 'break') {
                el.phasePill.className = 'pill break'; el.phasePill.textContent = 'Break';
                el.phaseLabel.textContent = 'Forced break';
                showPanel('break');
                return;
            }

            // work
            el.phasePill.className = 'pill work'; el.phasePill.textContent = 'Work';
            if (state.is_running) {
                el.phaseLabel.textContent = 'Playing';
                el.runningCat.textContent = state.active_category || '';
                showPanel('work-running');
            } else {
                el.phaseLabel.textContent = 'Ready to play';
                showPanel('work-idle');
            }
        }

        function renderSummary() {
            if (!summary || summary.length === 0) {
                el.catTotals.innerHTML = '<div class="cat-empty">No screen time logged yet today.</div>';
                return;
            }
            el.catTotals.innerHTML = summary.map(r =>
                `<div class="cat-row"><span>${r.category}</span><b>${fmtLong(r.seconds)}</b></div>`
            ).join('');
        }

        // Smooth ticking of the big countdown between polls.
        function tick() {
            const p = state.phase;
            if (p === 'break') {
                const remain = state.break_remaining_seconds - elapsedSince();
                el.bigTime.textContent = fmt(remain);
                el.timeSub.textContent = `remaining: ${fmtHM(remain)}`;
                el.ring.style.setProperty('--pct', 100 * (1 - remain / Math.max(1, state.break_total_seconds)));
                if (remain <= 0) poll();
            } else if (p === 'work') {
                let remain = state.work_remaining_seconds;
                let elapsed = state.work_elapsed_seconds;
                if (state.is_running) { remain -= elapsedSince(); elapsed += elapsedSince(); }
                el.bigTime.textContent = fmt(remain);
                el.timeSub.textContent = `remaining: ${fmtHM(remain)}`;
                el.ring.style.setProperty('--pct', 100 * (elapsed / Math.max(1, state.work_total_seconds)));

                // 5-minutes-left warning (once per work phase; only if the phase is long enough).
                if (state.is_running && state.work_total_seconds > 300) {
                    if (remain > 305) warned5 = false; // re-arm for the next/new phase
                    else if (!warned5 && remain > 0) {
                        warned5 = true;
                        notify('warn', '⏳', '5 minutes left', 'Your break is coming up soon.', 2);
                    }
                }

                if (state.is_running && remain <= 0) poll();
            }
            requestAnimationFrame(tick);
        }

        async function poll() {
            try {
                const r = await fetch(routes.state, { headers: { 'Accept': 'application/json' } });
                if (r.status === 401) { location.href = '/'; return; }
                const data = await r.json();
                setState(data.state, data.summary);
            } catch (e) { /* keep last state on transient error */ }
        }

        async function post(url, body) {
            busy = true;
            try {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: body ? JSON.stringify(body) : null,
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) { alertMsg(data.message || 'Something went wrong.'); await poll(); return; }
                if (data.state) setState(data.state, data.summary);
            } finally { busy = false; }
        }

        function alertMsg(m) {
            el.timeSub.textContent = m;
        }

        /* ---- Alerts: sound + on-screen toast + optional browser notification ---- */
        let audioCtx = null;
        function armAlerts() {
            if (!audioCtx) {
                try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
            }
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {});
            }
        }
        function beep(times) {
            if (!audioCtx) return;
            if (audioCtx.state === 'suspended') audioCtx.resume();
            for (let i = 0; i < times; i++) {
                const t = audioCtx.currentTime + i * 0.28;
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, t);
                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(0.35, t + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
                osc.connect(gain).connect(audioCtx.destination);
                osc.start(t); osc.stop(t + 0.24);
            }
        }
        function toast(kind, emoji, title, body) {
            const box = document.getElementById('toasts');
            const t = document.createElement('div');
            t.className = 'toast ' + kind;
            t.innerHTML = `<span class="t-emoji">${emoji}</span><span><span class="t-title">${title}</span><br><span class="t-body">${body}</span></span>`;
            box.appendChild(t);
            setTimeout(() => { t.classList.add('leaving'); setTimeout(() => t.remove(), 320); }, 6500);
        }
        function notify(kind, emoji, title, body, beeps) {
            toast(kind, emoji, title, body);
            beep(beeps);
            // If the kid switched tabs, raise a system notification too.
            if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                try { new Notification(title, { body, silent: false }); } catch (e) {}
            }
        }

        let prevPhase = state.phase;
        let warned5 = false;

        function checkTransitions(s) {
            if (prevPhase === 'work' && s.phase === 'break') {
                notify('timeup', '⏰', "Time's up!", 'Play time is over — break time now.', 3);
            } else if (prevPhase === 'break' && s.phase === 'work') {
                notify('ok', '✅', 'Break over!', 'You can play again.', 2);
            } else if (prevPhase !== 'locked' && s.phase === 'locked') {
                notify('timeup', '🌙', 'Done for today', 'See you tomorrow!', 3);
            }
            prevPhase = s.phase;
        }

        // Category selection
        document.querySelectorAll('.cat').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.cat').forEach(b => b.setAttribute('aria-pressed', 'false'));
                btn.setAttribute('aria-pressed', 'true');
                chosenCat = btn.dataset.cat;
                el.startBtn.disabled = false;
            });
        });

        el.startBtn.addEventListener('click', () => {
            if (busy || !chosenCat) return;
            post(routes.start, { category_id: Number(chosenCat) }).then(() => { chosenCat = null; });
        });
        el.stopBtn.addEventListener('click', () => { if (!busy) post(routes.stop); });

        el.themeToggle.addEventListener('click', async () => {
            const r = await fetch(routes.theme, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await r.json();
            document.documentElement.setAttribute('data-theme', data.dark_mode ? 'dark' : 'light');
        });

        // Change-PIN modal
        const pinModal = document.getElementById('pinModal');
        document.getElementById('pinBtn').addEventListener('click', () => { pinModal.hidden = false; });
        document.getElementById('pinCancel').addEventListener('click', () => { pinModal.hidden = true; });
        pinModal.addEventListener('click', (e) => { if (e.target === pinModal) pinModal.hidden = true; });

        // Arm sound + notification permission on the first interaction (autoplay policy).
        window.addEventListener('pointerdown', armAlerts, { once: true });

        render();
        requestAnimationFrame(tick);
        setInterval(poll, 3000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    })();
    </script>
    @endpush

    @include('partials.feedback-kid')
@endsection
