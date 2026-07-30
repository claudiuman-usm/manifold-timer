<!DOCTYPE html>
<html lang="en" data-theme="{{ ($dark ?? false) ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Manifold Timer')</title>
    <style>
        :root {
            --accent: {{ $accent ?? '#6366f1' }};

            --bg: #e7ebf5;
            --surface: #ffffff;
            --surface-2: #eef1f8;
            --text: #1a2233;
            --text-muted: #5b6577;
            --border: #dde2ec;
            --shadow: 0 18px 50px rgba(23, 32, 66, .12);

            /* glass + gradients */
            --glass: rgba(255, 255, 255, .55);
            --glass-2: rgba(255, 255, 255, .40);
            --grad-1: color-mix(in srgb, var(--accent) 26%, transparent);
            --grad-2: rgba(168, 85, 247, .20);
            --grad-3: rgba(56, 189, 248, .18);
            --grad-border: linear-gradient(135deg,
                color-mix(in srgb, var(--accent) 60%, white),
                color-mix(in srgb, var(--accent) 18%, transparent) 52%,
                rgba(255, 255, 255, .75));

            --work: #10b981;
            --work-soft: #e7f8f1;
            --break: #f59e0b;
            --break-soft: #fdf3e0;
            --locked: #64748b;
            --locked-soft: #eef1f6;
            --danger: #ef4444;

            --radius: 22px;
            --radius-sm: 14px;
        }

        html[data-theme="dark"] {
            --bg: #0a0e17;
            --surface: #161b26;
            --surface-2: #1d2431;
            --text: #e8edf5;
            --text-muted: #96a0b5;
            --border: #29313f;
            --shadow: 0 18px 50px rgba(0, 0, 0, .45);

            --glass: rgba(24, 30, 43, .55);
            --glass-2: rgba(31, 39, 54, .45);
            --grad-1: color-mix(in srgb, var(--accent) 34%, transparent);
            --grad-2: rgba(139, 92, 246, .24);
            --grad-3: rgba(14, 165, 233, .20);
            --grad-border: linear-gradient(135deg,
                color-mix(in srgb, var(--accent) 80%, white),
                color-mix(in srgb, var(--accent) 22%, transparent) 52%,
                rgba(255, 255, 255, .18));

            --work: #34d399;
            --work-soft: #10291f;
            --break: #fbbf24;
            --break-soft: #2a2110;
            --locked: #94a3b8;
            --locked-soft: #1a212c;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            min-height: 100dvh;
            background:
                radial-gradient(38vw 38vw at 8% 4%, var(--grad-1), transparent 72%),
                radial-gradient(42vw 42vw at 92% 8%, var(--grad-2), transparent 72%),
                radial-gradient(52vw 52vw at 50% 106%, var(--grad-3), transparent 74%),
                var(--bg);
            background-attachment: fixed;
            background-repeat: no-repeat;
            transition: color .25s ease;
        }

        a { color: var(--accent); text-decoration: none; }

        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px clamp(16px, 4vw, 40px) 64px; }

        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; padding: 6px 2px 24px;
        }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 700; letter-spacing: -.02em; }
        .brand .dot { width: 14px; height: 14px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent); }
        .brand small { display: block; font-weight: 500; color: var(--text-muted); font-size: .78rem; }

        /* Frosted-glass boxes with a gradient border */
        .card {
            position: relative;
            background: var(--glass);
            -webkit-backdrop-filter: blur(18px) saturate(165%);
            backdrop-filter: blur(18px) saturate(165%);
            border: 1px solid transparent;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: clamp(18px, 3vw, 30px);
        }
        .card::before {
            content: ""; position: absolute; inset: 0; z-index: 0;
            border-radius: inherit; padding: 1px;
            background: var(--grad-border);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor; mask-composite: exclude;
            pointer-events: none;
        }
        .card > * { position: relative; z-index: 1; }
        @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
            .card { background: var(--surface); }
        }

        .muted { color: var(--text-muted); }
        .h1 { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 750; letter-spacing: -.03em; margin: 0 0 4px; }
        .section-title { font-size: .82rem; text-transform: uppercase; letter-spacing: .09em; color: var(--text-muted); font-weight: 700; margin: 0 0 14px; }

        /* Buttons */
        .btn {
            appearance: none; border: 1px solid transparent; cursor: pointer;
            font: inherit; font-weight: 650; border-radius: var(--radius-sm);
            padding: 14px 20px; background: var(--surface-2); color: var(--text);
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            transition: transform .06s ease, filter .15s ease, background .15s ease;
            min-height: 52px;
        }
        .btn:active { transform: translateY(1px) scale(.995); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .btn-ghost {
            background: var(--glass-2);
            -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
            border-color: color-mix(in srgb, var(--border) 80%, transparent); color: var(--text-muted);
        }
        .btn-accent {
            background: linear-gradient(140deg, color-mix(in srgb, var(--accent) 82%, white), var(--accent));
            color: #fff; border-color: transparent;
            box-shadow: 0 8px 20px color-mix(in srgb, var(--accent) 32%, transparent);
        }
        .btn-work {
            background: linear-gradient(140deg, color-mix(in srgb, var(--work) 82%, white), var(--work));
            color: #04231a; box-shadow: 0 8px 20px color-mix(in srgb, var(--work) 32%, transparent);
        }
        .btn-danger {
            background: linear-gradient(140deg, color-mix(in srgb, var(--danger) 85%, white), var(--danger));
            color: #fff; box-shadow: 0 8px 20px color-mix(in srgb, var(--danger) 30%, transparent);
        }
        .btn-lg { min-height: 76px; font-size: 1.35rem; padding: 18px 28px; border-radius: 18px; width: 100%; }
        .btn:hover:not(:disabled) { filter: brightness(1.05); }

        .icon-btn {
            width: 46px; height: 46px; min-height: 46px; padding: 0; border-radius: 12px;
            background: var(--surface); border: 1px solid var(--border); color: var(--text);
            font-size: 1.2rem;
        }

        /* Forms */
        input[type=text], input[type=number], input[type=time], input[type=datetime-local], input[type=password], input[type=color], select {
            font: inherit; width: 100%; padding: 12px 14px; border-radius: 12px;
            border: 1px solid var(--border); background: var(--glass-2); color: var(--text);
            -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
        }
        input:focus, select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent); }
        label { font-size: .85rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 6px; }

        .error { color: var(--danger); font-size: .88rem; margin-top: 8px; }
        .flash {
            background: var(--work-soft); border: 1px solid color-mix(in srgb, var(--work) 40%, transparent);
            color: var(--text); padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;
        }

        .grid { display: grid; gap: 18px; }
        @media (min-width: 760px) { .grid-2 { grid-template-columns: 1fr 1fr; } }

        .pill {
            display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px;
            border-radius: 999px; font-size: .82rem; font-weight: 700;
        }
        .pill.work { background: var(--work-soft); color: var(--work); }
        .pill.break { background: var(--break-soft); color: var(--break); }
        .pill.locked { background: var(--locked-soft); color: var(--locked); }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); }
        th { font-size: .76rem; text-transform: uppercase; letter-spacing: .07em; color: var(--text-muted); }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }

        .mono { font-variant-numeric: tabular-nums; }
    </style>
    @stack('head')
</head>
<body>
    <div class="wrap">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
