@include('partials.feedback-styles')

{{-- Floating feedback widget — kid side (report a glitch / idea, read replies) --}}
<button class="fb-fab" id="fbFab" type="button" aria-label="Report a problem or idea">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm3 6a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 7 10zm5 0a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 12 10zm5 0a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 17 10z"/></svg>
    <span class="fb-badge" id="fbBadge" hidden>0</span>
</button>

<div class="fb-panel" id="fbPanel" hidden>
    <div class="fb-head">
        <div>
            <h3>Report a problem or idea</h3>
            <p>Tell us about a glitch 🐞 or an idea ✨</p>
        </div>
        <button class="fb-close" id="fbClose" type="button" aria-label="Close">&times;</button>
    </div>
    <div class="fb-body" id="fbBody"></div>
    <div class="fb-foot">
        <div class="fb-types">
            <button class="fb-type glitch"  type="button" data-type="glitch">🐞 Glitch</button>
            <button class="fb-type feature" type="button" data-type="feature">✨ Idea</button>
        </div>
        <div class="fb-compose">
            <textarea id="fbText" rows="1" placeholder="Pick 🐞 or ✨, then type…" maxlength="1000"></textarea>
            <button class="fb-send" id="fbSend" type="button" disabled aria-label="Send">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 9-18 9 4-9-4-9zm4.6 9L5.2 6.6 15.9 12 5.2 17.4 7.6 12z"/></svg>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const routes = {
        index: @json(route('kid.feedback.index')),
        store: @json(route('kid.feedback.store')),
        seen:  @json(route('kid.feedback.seen')),
    };
    const fab = document.getElementById('fbFab');
    const panel = document.getElementById('fbPanel');
    const badge = document.getElementById('fbBadge');
    const body = document.getElementById('fbBody');
    const text = document.getElementById('fbText');
    const send = document.getElementById('fbSend');
    const typeBtns = [...document.querySelectorAll('.fb-type')];

    let open = false, threads = [], unread = 0, chosenType = null, lastSig = '';

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

    // Only rebuild when the data changed, so an 8s poll doesn't jump the scroll.
    function apply(data) {
        threads = data.threads; unread = open ? 0 : data.unread;
        const sig = JSON.stringify(threads);
        if (sig !== lastSig) { lastSig = sig; render(); } else { renderBadge(); }
    }

    function render() {
        renderBadge();
        if (!threads.length) {
            body.innerHTML = '<div class="fb-empty">No reports yet.<br>Found a bug or have an idea? Tell us below 👇</div>';
            return;
        }
        body.innerHTML = threads.map(t => `
            <div class="fb-thread ${t.resolved ? 'resolved' : ''}">
                <div class="fb-thread-head">
                    <span class="fb-chip ${t.type}">${t.type === 'glitch' ? '🐞 Glitch' : '✨ Idea'}</span>
                    ${t.resolved ? '<span class="fb-chip resolved">✓ Fixed</span>' : ''}
                </div>
                ${t.messages.map(m => `
                    <div class="fb-msg ${m.sender}">${esc(m.body)}<span class="fb-when">${fmtWhen(m.at)}</span></div>
                `).join('')}
            </div>`).join('');
        body.scrollTop = body.scrollHeight;
    }

    async function refresh() {
        try {
            const data = await (await fetch(routes.index, { headers: { 'Accept': 'application/json' } })).json();
            apply(data);
            if (open && data.unread > 0) markSeen();
        } catch (e) {}
    }

    async function markSeen() { apply(await post(routes.seen)); }

    function setOpen(v) {
        open = v; panel.hidden = !open; fab.hidden = open ? false : false;
        if (open) { markSeen(); text.focus(); }
    }

    function updateSendState() {
        send.disabled = !(chosenType && text.value.trim().length);
    }

    typeBtns.forEach(b => b.addEventListener('click', () => {
        chosenType = b.dataset.type;
        typeBtns.forEach(x => x.classList.toggle('active', x === b));
        updateSendState(); text.focus();
    }));

    text.addEventListener('input', () => {
        text.style.height = 'auto'; text.style.height = Math.min(text.scrollHeight, 120) + 'px';
        updateSendState();
    });
    text.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
    });

    async function submit() {
        const bodyText = text.value.trim();
        if (!chosenType || !bodyText) return;
        send.disabled = true;
        try {
            const data = await post(routes.store, { type: chosenType, body: bodyText });
            text.value = ''; text.style.height = 'auto';
            chosenType = null; typeBtns.forEach(x => x.classList.remove('active'));
            apply(data);
        } catch (e) { send.disabled = false; }
    }

    fab.addEventListener('click', () => setOpen(!open));
    document.getElementById('fbClose').addEventListener('click', () => setOpen(false));
    send.addEventListener('click', submit);

    refresh();
    setInterval(refresh, 8000);
})();
</script>
@endpush
