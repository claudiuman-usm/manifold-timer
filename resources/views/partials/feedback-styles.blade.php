@push('head')
<style>
    /* ---- Floating feedback widget (shared by kid + parent) ---------------- */
    .fb-fab {
        position: fixed; right: 20px; bottom: 20px; z-index: 300;
        width: 58px; height: 58px; border-radius: 50%; border: 0; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff;
        background: linear-gradient(140deg, color-mix(in srgb, var(--accent) 82%, white), var(--accent));
        box-shadow: 0 10px 26px color-mix(in srgb, var(--accent) 40%, transparent);
        transition: transform .12s ease, filter .15s ease;
    }
    .fb-fab:hover { filter: brightness(1.06); }
    .fb-fab:active { transform: scale(.92); }
    .fb-fab svg { width: 28px; height: 28px; fill: currentColor; display: block; }
    .fb-badge {
        position: absolute; top: -3px; right: -3px;
        min-width: 21px; height: 21px; padding: 0 6px;
        border-radius: 11px; background: var(--danger); color: #fff;
        font: 800 .74rem/21px system-ui, sans-serif; text-align: center;
        box-shadow: 0 0 0 2px var(--bg, #fff);
    }

    .fb-panel {
        position: fixed; right: 20px; bottom: 88px; z-index: 300;
        width: 370px; max-width: calc(100vw - 40px);
        height: 540px; max-height: calc(100dvh - 130px);
        display: flex; flex-direction: column; overflow: hidden;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 20px; box-shadow: 0 26px 60px rgba(0,0,0,.30);
    }
    .fb-panel[hidden] { display: none; } /* author display:flex would otherwise beat [hidden] */
    .fb-head {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 14px 16px; border-bottom: 1px solid var(--border);
    }
    .fb-head h3 { margin: 0; font-size: 1rem; font-weight: 750; }
    .fb-head p { margin: 2px 0 0; font-size: .76rem; color: var(--text-muted); }
    .fb-close {
        background: none; border: 0; cursor: pointer; color: var(--text-muted);
        font-size: 1.4rem; line-height: 1; padding: 4px 8px; border-radius: 10px;
    }
    .fb-close:hover { color: var(--text); }

    .fb-body {
        flex: 1; overflow-y: auto; padding: 14px 16px;
        display: flex; flex-direction: column; gap: 14px;
    }
    .fb-empty { margin: auto; text-align: center; color: var(--text-muted); font-size: .9rem; padding: 20px; }

    .fb-thread { display: flex; flex-direction: column; gap: 6px; }
    .fb-thread + .fb-thread { border-top: 1px dashed var(--border); padding-top: 14px; }
    .fb-thread-head { display: flex; align-items: center; gap: 8px; margin-bottom: 2px; }
    .fb-chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px; border-radius: 999px; font-size: .74rem; font-weight: 700;
    }
    .fb-chip.glitch  { background: color-mix(in srgb, var(--danger) 16%, transparent); color: var(--danger); }
    .fb-chip.feature { background: color-mix(in srgb, var(--accent) 16%, transparent); color: var(--accent); }
    .fb-chip.resolved { background: var(--work-soft); color: var(--work); }
    .fb-kidtag { font-size: .78rem; font-weight: 700; }

    .fb-msg {
        max-width: 82%; padding: 9px 12px; border-radius: 15px;
        font-size: .92rem; line-height: 1.38; word-wrap: break-word; white-space: pre-wrap;
    }
    .fb-msg .fb-when { display: block; font-size: .68rem; opacity: .7; margin-top: 3px; }
    .fb-msg.kid    { align-self: flex-end; background: linear-gradient(140deg, color-mix(in srgb, var(--accent) 82%, white), var(--accent)); color: #fff; border-bottom-right-radius: 5px; }
    .fb-msg.parent { align-self: flex-start; background: var(--surface-2); color: var(--text); border-bottom-left-radius: 5px; }

    .fb-foot { border-top: 1px solid var(--border); padding: 12px 14px; }
    .fb-types { display: flex; gap: 8px; margin-bottom: 10px; }
    .fb-type {
        flex: 1; cursor: pointer; padding: 9px; border-radius: 12px;
        border: 1.5px solid var(--border); background: var(--surface); color: var(--text-muted);
        font: inherit; font-weight: 650; font-size: .88rem;
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        transition: border-color .12s, color .12s, background .12s;
    }
    .fb-type.active.glitch  { border-color: var(--danger); color: var(--danger); background: color-mix(in srgb, var(--danger) 10%, transparent); }
    .fb-type.active.feature { border-color: var(--accent); color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); }
    .fb-compose { display: flex; gap: 8px; align-items: flex-end; }
    .fb-compose textarea {
        flex: 1; resize: none; font: inherit; min-height: 44px; max-height: 120px;
        padding: 11px 13px; border-radius: 12px; border: 1px solid var(--border);
        background: var(--surface); color: var(--text);
    }
    .fb-compose textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 20%, transparent); }
    .fb-send {
        flex: none; width: 46px; height: 46px; border-radius: 12px; border: 0; cursor: pointer;
        color: #fff; background: linear-gradient(140deg, color-mix(in srgb, var(--accent) 82%, white), var(--accent));
        display: inline-flex; align-items: center; justify-content: center;
    }
    .fb-send:disabled { opacity: .45; cursor: not-allowed; }
    .fb-send svg { width: 22px; height: 22px; fill: currentColor; }

    /* Parent-only: reply row + resolve control */
    .fb-thread-actions { display: flex; gap: 8px; margin-top: 4px; }
    .fb-mini {
        font: inherit; font-size: .78rem; font-weight: 650; cursor: pointer;
        padding: 6px 10px; border-radius: 9px; border: 1px solid var(--border);
        background: var(--surface); color: var(--text-muted);
    }
    .fb-mini:hover { color: var(--text); }
    .fb-mini.done { border-color: color-mix(in srgb, var(--work) 50%, transparent); color: var(--work); }
    .fb-thread.resolved .fb-msg, .fb-thread.resolved .fb-chip.glitch, .fb-thread.resolved .fb-chip.feature { opacity: .6; }
    .fb-replybox { display: flex; gap: 6px; margin-top: 6px; }
    .fb-replybox input {
        flex: 1; font: inherit; font-size: .88rem; padding: 8px 11px; border-radius: 10px;
        border: 1px solid var(--border); background: var(--surface); color: var(--text);
    }
    .fb-replybox input:focus { outline: none; border-color: var(--accent); }
    .fb-replybox button {
        flex: none; font: inherit; font-size: .82rem; font-weight: 650; cursor: pointer;
        padding: 8px 12px; border-radius: 10px; border: 0; color: #fff;
        background: linear-gradient(140deg, color-mix(in srgb, var(--accent) 82%, white), var(--accent));
    }

    /* Parent view flips the bubbles (parent = self = right, kid = incoming = left) */
    .fb-panel.is-parent .fb-msg.kid    { align-self: flex-start; background: var(--surface-2); color: var(--text); border-radius: 15px; border-bottom-left-radius: 5px; }
    .fb-panel.is-parent .fb-msg.parent { align-self: flex-end; background: linear-gradient(140deg, color-mix(in srgb, var(--accent) 82%, white), var(--accent)); color: #fff; border-radius: 15px; border-bottom-right-radius: 5px; }
    .fb-kidtag::before { content: ''; display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: currentColor; margin-right: 5px; vertical-align: middle; }

    @media (max-width: 480px) {
        .fb-panel { right: 12px; left: 12px; width: auto; bottom: 84px; }
        .fb-fab { right: 14px; bottom: 14px; }
    }
</style>
@endpush
