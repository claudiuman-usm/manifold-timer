# Deploying a Laravel app to cPanel (no‑SSH, Git) — battle‑tested runbook

Written from the real deployment of Manifold Timer to `timer.manifold.ro`
(cPanel + LiteSpeed + CloudLinux, **no SSH**, MySQL via phpMyAdmin, code delivered
by **cPanel Git™ Version Control** pulling from GitHub).

The friction we actually hit is baked in here as **⚠️ Gotchas** and a
**Troubleshooting** table at the end. Follow the phases in order.

---

## Architecture (how the pieces fit)

```
Your Mac  ──git push (SSH)──►  GitHub repo  ──cPanel "Update from Remote"──►  /home/USER/repositories/APP
                                                                                      │
                       served docroot: /home/USER/SUBDOMAIN/public  ── loader shim ──►┘ (boots the app)
```

- `vendor/` is **committed to git** so the server needs no Composer/SSH.
- Sessions & cache use the **`file`** driver → **no framework DB tables** needed.
- Only the app's own tables are created by hand in phpMyAdmin.

Replace throughout: `USER` = cPanel user (e.g. `manifold`), `APP` = repo folder
(e.g. `manifold-timer-app`), `SUBDOMAIN` = the domain folder (e.g. `timer.manifold.ro`).

---

## Phase 0 — Prepare the app locally (once)

1. **Commit `vendor/`.** Remove `/vendor` from `.gitignore` so it's tracked. Also
   ignore local artefacts you don't want on the server:
   ```
   # .gitignore
   /database/*.sqlite
   # (do NOT ignore /vendor — we ship it)
   ```
2. **Production env template** — keep a `deploy/env.production.txt` with the
   production settings (see Phase 4). Never commit the real `.env`.
3. **Production SQL** — generate `deploy/production.sql` = `CREATE TABLE`s + seed
   rows (export the seed straight from your local DB so hashed PINs carry over).
4. **`.cpanel.yml`** — keep permission fixes path‑independent (tasks run from the
   repo root, so use **relative** paths):
   ```yaml
   ---
   deployment:
     tasks:
       - /bin/chmod -R 775 storage bootstrap/cache
   ```

---

## Phase 1 — Git: local → GitHub

```bash
cd ~/APP
git init -b main
git add -A
git commit -m "Production-ready build"
git remote add origin git@github.com:USERNAME/REPO.git   # use SSH, not HTTPS
git push -u origin main
```

⚠️ **Gotcha — HTTPS push fails** with `could not read Username for 'https://github.com'`
in a non‑interactive shell. **Use the SSH remote** (`git@github.com:…`). Verify your
key first:
```bash
ssh -T git@github.com     # should say: Hi USERNAME! You've successfully authenticated
```
If you only have an HTTPS remote: `git remote set-url origin git@github.com:USERNAME/REPO.git`.

⚠️ **Gotcha — GitHub still shows the "empty repo" page** after a successful push.
It's just cached. Confirm with `git ls-remote origin` (you'll see `refs/heads/main`)
and hard‑refresh the page.

---

## Phase 2 — cPanel Git™ Version Control: clone onto the server

cPanel → **Git Version Control** → **Create** → **Clone a Repository: ON**.

- **Clone URL:** public repo → `https://github.com/USERNAME/REPO.git`.
  Private repo → `git@github.com:USERNAME/REPO.git` **and** add the cPanel account's
  SSH key (cPanel → *SSH Access → Manage SSH Keys*, copy the public key) as a
  **Deploy key** on the GitHub repo (Settings → Deploy keys).
- **Repository Path:** `/home/USER/repositories/APP`

⚠️ **Gotcha — `"" is not a valid "branch"` on "Update from Remote".** This means
cPanel cloned the repo **while it was still empty** (before your first push), so it
has no branch. **Fix:** push your code first, then **re‑create** the cPanel repo by
cloning (don't try to repair the empty one).

⚠️ **Gotcha — "directory already contains files" and the folder keeps reappearing.**
A cPanel Git *entry* still points at that path and keeps recreating it. **Remove every
Git Version Control entry** for it first, then either clean the folder or — simplest —
**clone into a fresh path** (e.g. `…/APP` with a new name). Adjust the docroot to match.

After the clone: **Manage → Update from Remote** pulls everything (including `vendor/`).
The first pull is slow (thousands of files) — that's normal.

---

## Phase 3 — Database (phpMyAdmin, by hand)

1. cPanel → **MySQL® Databases** → create a **database**, a **user** (strong
   password), then **add the user to the database with ALL PRIVILEGES**. Note the
   **prefixed** names cPanel gives (e.g. `USER_dbname`, `USER_dbuser`).
2. cPanel → **phpMyAdmin** → select the DB → **SQL** tab → paste the contents of
   `deploy/production.sql` → **Go**. (Or **Import** the file.)
3. Verify:
   ```sql
   SELECT (SELECT COUNT(*) FROM kids) k, (SELECT COUNT(*) FROM categories) c;
   ```

⚠️ **Gotcha — `#1050 Table 'x' already exists`.** Harmless — the tables were created
by an earlier run; MySQL just stops at the first `CREATE`. Only re‑run the **INSERTs**
if the tables are empty (check counts first, to avoid duplicates).

---

## Phase 4 — The `.env` (create on the server)

File Manager → `/home/USER/repositories/APP` → **+ File** → `.env` (exact name, leading
dot). `.env.example` is **not** it. Paste your production values:

```dotenv
APP_NAME="Your App"
APP_ENV=production
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX=
APP_DEBUG=false
APP_URL=https://SUBDOMAIN
APP_TIMEZONE=Europe/Bucharest        # server time drives the app — set your zone

DB_CONNECTION=mysql
DB_HOST=127.0.0.1                     # if "Connection refused", try: localhost
DB_PORT=3306
DB_DATABASE=USER_dbname
DB_USERNAME=USER_dbuser
DB_PASSWORD=the-exact-password

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false           # flip to true AFTER https works
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_LEVEL=error
```

- **APP_KEY:** generate locally `php artisan key:generate --show` and paste the whole
  value **including the `base64:` prefix**.

⚠️ **Gotcha — `RuntimeException: Unsupported cipher or incorrect key length`.** The
`APP_KEY` is wrong — 99% of the time the **`base64:` prefix was dropped**, or a
stray space/quote crept in.

⚠️ **Gotcha — 500 on pages that touch the DB (but the login page works).** The
credentials are wrong or the user isn't attached to the DB. The public landing page
often needs no DB, so it loads while `/dashboard` 500s — that's the tell.

---

## Phase 5 — Document root + the LiteSpeed vhost workaround

The app must be served from its **`public/`** folder.

cPanel → **Domains → SUBDOMAIN → Manage** → set **Document Root**.

⚠️ **The big one — cPanel says the docroot changed, but LiteSpeed keeps serving the
old empty `SUBDOMAIN/public` folder.** Symptoms: every URL (even a static
`robots.txt`) returns a **LiteSpeed 404**, and AutoSSL validation 404s. The vhost
didn't rebuild. Rather than fight it, **serve from the folder LiteSpeed already uses**
and have it boot the app that lives elsewhere:

1. Set Document Root to **`SUBDOMAIN/public`** (the folder the server actually serves).
2. In `/home/USER/SUBDOMAIN/public/` create **`index.php`**:
   ```php
   <?php
   use Illuminate\Http\Request;
   define('LARAVEL_START', microtime(true));

   // App lives outside this web root:
   $appBase = dirname(__DIR__, 2).'/repositories/APP';

   if (file_exists($m = $appBase.'/storage/framework/maintenance.php')) require $m;
   require $appBase.'/vendor/autoload.php';
   ($app = require_once $appBase.'/bootstrap/app.php')->handleRequest(Request::capture());
   ```
   …and **`.htaccess`** (copy Laravel's stock `public/.htaccess`).

This also fixes SSL, because the cert's `.well-known` challenge now lands in the
folder that's actually served.

> If your host's vhost **does** honour a custom docroot, you can skip the shim and
> just point the docroot straight at `/home/USER/repositories/APP/public`. Test with a
> throwaway `test.txt` in that folder + `http://SUBDOMAIN/test.txt` to know which case
> you're in before doing anything else.

---

## Phase 6 — Permissions

File Manager → inside `APP`, set **`storage`** and **`bootstrap/cache`** to **775**,
**recursively** (tick "Recurse into subdirectories").

⚠️ **Gotcha — `419 Page Expired` on any form submit.** Laravel can't write session
files, so the CSRF token never persists. Fixing `storage` to 775 (recursive) resolves
it. If not, the folder is owned by the wrong user — test with `775`/`777` on
`storage/framework/sessions` to confirm.

---

## Phase 7 — First run & how to see real errors

Everything hides behind a blank **500** when `APP_DEBUG=false`. To diagnose:

1. `.env` → `APP_DEBUG=true` → reload → read the red headline (or check
   `storage/logs/laravel.log`, which is writable once Phase 6 is done).
2. Fix, then set `APP_DEBUG=false` again.

A **500 is progress** — it means PHP is executing your app; only the app is erroring.
A **LiteSpeed 404** means the request never reached the app (docroot/vhost, Phase 5).

---

## Phase 8 — HTTPS

⚠️ **Gotcha — `https://` gives a LiteSpeed 404 while `http://` works.** Before a
certificate exists, HTTPS requests can't match your domain's vhost and fall back to a
default one → 404. **Test over `http://` first.** Then:

1. cPanel → **SSL/TLS Status** → tick SUBDOMAIN → **Run AutoSSL** → wait for issuance.
2. Once `https://` loads, in `.env` set **`SESSION_SECURE_COOKIE=true`** and save.

---

## Phase 9 — Go‑live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` set (with `base64:`), `APP_URL=https://…`, `APP_TIMEZONE` correct
- [ ] DB credentials correct; app tables imported & seeded
- [ ] Document root serves the app (Phase 5); `storage`/`bootstrap/cache` = 775
- [ ] HTTPS issued; `SESSION_SECURE_COOKIE=true`
- [ ] Changed any default PINs/secrets from placeholders
- [ ] Smoke test: login, a DB‑backed page, a form submit

---

## Phase 10 — Deploying updates later

```bash
# on your Mac
git add -A && git commit -m "…" && git push
```
Then cPanel → **Git Version Control → Update from Remote** (pulls). Optionally
**Deploy HEAD Commit** to re‑apply permissions via `.cpanel.yml`. Schema changes ship
as SQL — run the relevant `ALTER TABLE` in phpMyAdmin by hand.

---

## Troubleshooting — the frictions we hit, mapped

| Symptom | Cause | Fix |
|---|---|---|
| `could not read Username for 'https://github.com'` | HTTPS git auth in non‑interactive shell | Use the **SSH** remote; `ssh -T git@github.com` to verify |
| GitHub shows empty‑repo page after push | Cached page | `git ls-remote origin`; hard‑refresh |
| cPanel: `"" is not a valid "branch"` | Repo cloned while empty | Push first, then **re‑clone** the cPanel repo |
| cPanel: "directory already contains files" (keeps returning) | A Git entry still owns the path | Remove all Git entries; clone into a **fresh path** |
| LiteSpeed **404** on every URL incl. static files | Docroot/vhost not honouring your change | **Loader shim** in the served `SUBDOMAIN/public` (Phase 5) |
| `https://` 404 but `http://` works | No SSL cert yet → default vhost | Test on `http`; **Run AutoSSL** |
| **500**, `Unsupported cipher or incorrect key length` | Bad `APP_KEY` | Paste full value **with `base64:`**, no spaces/quotes |
| **419 Page Expired** on submit | Can't write session files | `storage` → **775 recursive** |
| **500** on DB pages, login page fine | Wrong DB credentials / user not attached | Fix `.env` DB block; `localhost` vs `127.0.0.1`; ALL PRIVILEGES |
| `#1050 Table already exists` on import | Tables already created | Harmless; re‑run only INSERTs if empty |
```
