@extends('layouts.app')

@section('title', 'Enter your PIN — Manifold Timer')

@section('content')
    <div class="topbar">
        <div class="brand">
            <span class="dot"></span>
            <div>Manifold Timer <small>Enter your PIN to continue</small></div>
        </div>
    </div>

    <div class="card pin-card">
        <p class="section-title" style="text-align:center; margin-bottom:18px">Who's here?</p>

        <div class="pin-display" id="dots">
            <span></span><span></span><span></span><span></span>
        </div>

        @error('pin')<div class="error" style="text-align:center">{{ $message }}</div>@enderror

        <form method="POST" action="{{ route('gate.enter') }}" id="pinForm">
            @csrf
            <input type="hidden" name="pin" id="pin" value="">
            <div class="pad">
                @foreach ([1,2,3,4,5,6,7,8,9] as $n)
                    <button type="button" class="key" data-key="{{ $n }}">{{ $n }}</button>
                @endforeach
                <button type="button" class="key ghost" data-action="back">⌫</button>
                <button type="button" class="key" data-key="0">0</button>
                <button type="submit" class="key ok" data-action="ok">OK</button>
            </div>
        </form>
    </div>

    <style>
        .pin-card { max-width: 380px; margin: 6vh auto 0; }
        .pin-display { display: flex; gap: 16px; justify-content: center; margin: 6px 0 22px; }
        .pin-display span {
            width: 18px; height: 18px; border-radius: 50%;
            border: 2px solid var(--border); transition: all .12s ease;
        }
        .pin-display span.on { background: var(--accent); border-color: var(--accent); transform: scale(1.05); }
        .pad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .key {
            appearance: none; border: 1px solid var(--border); background: var(--surface-2);
            color: var(--text); font-size: 1.7rem; font-weight: 650; border-radius: 16px;
            padding: 18px 0; cursor: pointer; transition: transform .05s ease, background .15s ease;
        }
        .key:active { transform: translateY(1px) scale(.97); background: color-mix(in srgb, var(--accent) 14%, var(--surface-2)); }
        .key.ghost { background: transparent; color: var(--text-muted); }
        .key.ok { background: var(--accent); color: #fff; border-color: transparent; font-size: 1.2rem; }
    </style>

    <script>
        (function () {
            const maxLen = 8;
            let pin = '';
            const dots = document.getElementById('dots');
            const field = document.getElementById('pin');

            function render() {
                dots.querySelectorAll('span').forEach((s, i) => s.classList.toggle('on', i < pin.length));
                field.value = pin;
            }

            document.querySelectorAll('.key').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const action = btn.dataset.action;
                    if (action === 'ok') return; // let the form submit
                    e.preventDefault();
                    if (action === 'back') pin = pin.slice(0, -1);
                    else if (pin.length < maxLen) pin += btn.dataset.key;
                    render();
                });
            });

            render();
        })();
    </script>
@endsection
