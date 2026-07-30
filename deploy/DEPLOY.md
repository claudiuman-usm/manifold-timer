# Deploying Manifold Timer to timer.manifold.ro (cPanel + Git)

Architecture: your **local** machine pushes to **GitHub**; **cPanel Git Version
Control** pulls into `/home/manifold/repositories/manifold-timer`; the subdomain
serves that repo's **`/public`** folder. `vendor/` is committed, so the server
needs **no Composer / SSH**. Database schema + seed go in once via **phpMyAdmin**.

---

## A. One-time setup

### 1. Push the code to GitHub (from your Mac)
```
cd ~/manifold-timer
git push -u origin main
```
(The repo, remote, and first commit are already prepared locally.)

> If the GitHub repo is **private**, cPanel needs read access: in cPanel →
> *Git™ Version Control*, copy the account's **SSH public key** and add it as a
> **Deploy key** on GitHub (repo → Settings → Deploy keys). Public repo = skip this.

### 2. Pull it on the server
cPanel → **Git Version Control** → your repo → **Manage** → **Update from Remote**.
This pulls everything, including `vendor/`.

### 3. Create the database (cPanel → MySQL® Databases)
- Create a database, e.g. `manifold_timer`.
- Create a user + password, then **add the user to the database** with **ALL PRIVILEGES**.
- Note the final names (cPanel prefixes them, e.g. `manifold_timer`, `manifold_dbuser`).

### 4. Load schema + your data (cPanel → phpMyAdmin)
Select the database → **Import** tab → upload **`deploy/production.sql`** → Go.
This creates the 4 tables and seeds Robin, Adora, the categories, and settings.

### 5. Create the `.env` (cPanel → File Manager)
- Go to `/home/manifold/repositories/manifold-timer`, **New File** → `.env`.
- Paste the contents of **`deploy/env.production.txt`** and fill in:
  - `APP_KEY` — on your Mac run `php artisan key:generate --show` and paste the `base64:...` value.
  - `PARENT_PIN` — **change it from 0000.**
  - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 3.
  - `APP_URL=https://timer.manifold.ro`, `APP_TIMEZONE=Europe/Bucharest`.

### 6. Point the subdomain at `/public`
cPanel → **Domains** (or *Subdomains*) → `timer.manifold.ro` → set **Document Root** to:
```
/home/manifold/repositories/manifold-timer/public
```

### 7. Make Laravel's folders writable
cPanel → File Manager (or the Deploy button, which runs `.cpanel.yml`):
`storage/` and `bootstrap/cache/` should be **775** (recursively).

### 8. Turn on HTTPS
cPanel → **SSL/TLS Status** → include `timer.manifold.ro` → **Run AutoSSL**
(Let's Encrypt). Keep `SESSION_SECURE_COOKIE=true` in `.env`.

### 9. Smoke test
Open **https://timer.manifold.ro** → the PIN gate appears → enter a kid PIN and
the parent PIN → confirm timer + dashboard work.

---

## B. Deploying updates later
1. On your Mac: `git add -A && git commit -m "..." && git push`
2. cPanel → Git Version Control → **Update from Remote** (and optionally
   **Deploy HEAD Commit** to re-apply folder permissions).

Schema changes (rare) ship as SQL — run the relevant `ALTER TABLE` from
`database/schema/mysql-schema.sql` in phpMyAdmin by hand.

---

## Notes
- No `artisan migrate` on the server — schema is applied by hand (no SSH).
- Sessions/cache are file-based, so no framework DB tables are required.
- Kids change their own PIN in-app (⚙); the parent PIN lives only in `.env`.
- Server time drives all cycle logic — make sure `APP_TIMEZONE` is correct.
