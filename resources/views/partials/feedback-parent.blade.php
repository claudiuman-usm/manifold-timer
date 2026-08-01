@include('partials.feedback-styles')

{{-- Floating feedback widget — parent side (read kids' reports, reply, resolve) --}}
<button class="fb-fab" id="fbFab" type="button" aria-label="Kids' reports">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm3 6a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 7 10zm5 0a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 12 10zm5 0a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 17 10z"/></svg>
    <span class="fb-badge" id="fbBadge" hidden>0</span>
</button>

<div class="fb-panel is-parent" id="fbPanel" hidden>
    <div class="fb-head">
        <div>
            <h3>Kids' reports</h3>
            <p>Glitches 🐞 &amp; ideas ✨ from the kids</p>
        </div>
        <button class="fb-close" id="fbClose" type="button" aria-label="Close">&times;</button>
    </div>
    <div class="fb-body" id="fbBody"></div>
</div>

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const base = @json(url('/parent/feedback'));
    const routes = {
        index:   @json(route('parent.feedback.index')),
        seen:    @json(route('parent.feedback.seen')),
        reply:   id => base + '/' + id + '/reply',
        resolve: id => base + '/' + id + '/resolve',
    };
    const fab = document.getElementById('fbFab');
    const panel = document.getElementById('fbPanel');
    const badge = document.getElementById('fbBadge');
    const body = document.getElementById('fbBody');

    let open = false, threads = [], unread = 0, lastSig = '';

    const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    const post = (url, data) => fetch(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(data || {}),
    }).then(r => r.json());

    function fmtWhen(iso) {
        const d = new Date(iso), now = new Date(), diff = (now - d) / 1000;
        if (diff < 45) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        const t = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return d.toDateString() === now.toDateString() ? t : d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + t;
    }

    function renderBadge() { badge.hidden = unread === 0; badge.textContent = unread; }

    // Keep a reply the parent is mid-typing alive across a re-render.
    function captureFocus() {
        const el = document.activeElement;
        return (el && el.matches && el.matches('[data-reply]'))
            ? { id: el.dataset.reply, value: el.value, pos: el.selectionStart } : null;
    }
    function restoreFocus(f) {
        if (!f) return;
        const el = body.querySelector(`[data-reply="${f.id}"]`);
        if (el) { el.value = f.value; el.focus(); try { el.setSelectionRange(f.pos, f.pos); } catch (e) {} }
    }

    function render() {
        renderBadge();
        const f = captureFocus();
        body.innerHTML = threads.length ? threads.map(t => `
            <div class="fb-thread ${t.resolved ? 'resolved' : ''}" data-id="${t.id}">
                <div class="fb-thread-head">
                    <span class="fb-kidtag" style="color:${esc(t.color)}">${esc(t.kid)}</span>
                    <span class="fb-chip ${t.type}">${t.type === 'glitch' ? '🐞 Glitch' : '✨ Idea'}</span>
                    ${t.resolved ? '<span class="fb-chip resolved">✓ Resolved</span>' : ''}
                </div>
                ${t.messages.map(m => `
                    <div class="fb-msg ${m.sender}">${esc(m.body)}<span class="fb-when">${fmtWhen(m.at)}</span></div>
                `).join('')}
                <div class="fb-replybox">
                    <input type="text" placeholder="Reply to ${esc(t.kid)}…" maxlength="1000" data-reply="${t.id}">
                    <button type="button" data-send="${t.id}">Send</button>
                </div>
                <div class="fb-thread-actions">
                    <button class="fb-mini ${t.resolved ? 'done' : ''}" type="button" data-resolve="${t.id}">
                        ${t.resolved ? '↩ Reopen' : '✓ Mark resolved'}
                    </button>
                </div>
            </div>`).join('')
            : '<div class="fb-empty">No reports from the kids yet.</div>';
        restoreFocus(f);
    }

    // Only rebuild the list when it actually changed, so a background poll never
    // wipes a reply mid-type; always keep the badge in sync.
    function apply(data) {
        threads = data.threads; unread = open ? 0 : data.unread;
        const sig = JSON.stringify(threads);
        if (sig !== lastSig) { lastSig = sig; render(); } else { renderBadge(); }
    }

    async function refresh() {
        try {
            const data = await (await fetch(routes.index, { headers: { 'Accept': 'application/json' } })).json();
            apply(data);
            if (open && data.unread > 0) markSeen();
        } catch (e) {}
    }

    async function markSeen() { apply(await post(routes.seen)); }

    function setOpen(v) { open = v; panel.hidden = !open; if (open) markSeen(); }

    async function sendReply(id) {
        const input = body.querySelector(`[data-reply="${id}"]`);
        const text = input.value.trim();
        if (!text) return;
        input.disabled = true;
        try {
            const data = await post(routes.reply(id), { body: text });
            input.value = ''; input.blur();
            apply(data);
        } catch (e) { input.disabled = false; }
    }

    async function toggleResolve(id) { apply(await post(routes.resolve(id))); }

    // Delegated handlers for the dynamically-rendered thread controls.
    body.addEventListener('click', e => {
        const send = e.target.closest('[data-send]');
        if (send) return sendReply(send.dataset.send);
        const resolve = e.target.closest('[data-resolve]');
        if (resolve) return toggleResolve(resolve.dataset.resolve);
    });
    body.addEventListener('keydown', e => {
        const input = e.target.closest('[data-reply]');
        if (input && e.key === 'Enter') { e.preventDefault(); sendReply(input.dataset.reply); }
    });

    fab.addEventListener('click', () => setOpen(!open));
    document.getElementById('fbClose').addEventListener('click', () => setOpen(false));

    refresh();
    setInterval(refresh, 5000);
})();
</script>
@endpush
