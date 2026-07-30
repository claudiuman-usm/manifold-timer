# Manifold Timer — Build Spec (v1)

A self-hosted Laravel app that helps two children log their screen time and enforces a repeating work → break cycle, with all activity locked at midnight.

**Domain:** timer.manifold.ro
**Author:** Claudiu Man / Manifold

---

## Build rules (read first)

- **Local only. Do not deploy.** All work happens on the local machine.
- On completion, **create/update `CHANGELOG.md`** and **bump the app version** (this is the initial build → version `1.0.0`, changelog entry "Initial build").
- Keep the solution **clean and maintainable**. No over-engineering.
- Do **not** apply "minimal design" / "minimal styling" — build a complete, real UI. Claudiu shapes the design as it's built.
- Follow the established Manifold conventions below.

---

## Stack

- **Laravel** (latest stable), **Blade** templating
- **SQLite** for local dev, **MySQL** for production (mirror `.env` accordingly; local uses the SQLite driver)
- **Dark mode** via CSS custom properties (variables), toggled and persisted per kid
- No SSH in production: schema changes are applied by hand in phpMyAdmin, so **keep migrations clean and produce plain SQL where a schema change lands** (Claudiu runs it manually)

---

## Core concept

The timer is not a neutral stopwatch. It **drives each child through a cycle**:

```
work (default 120 min)  →  forced break (default 45 min)  →  work  →  break  →  … until 00:00
```

- The work budget is **per-phase and continuous**: 2h of work, then a **mandatory** break. It does **not** accumulate into a daily pool.
- Work time within a phase may be **split across several start/stops** — the phase completes once the summed work time reaches the configured work duration.
- At **midnight (00:00)** everything **locks** ("done for today") regardless of the current phase, and **daily counters reset** for the new day. A child mid-work-phase at 23:50 is cut off at 00:00 — this is intended.
- **Server time is the single source of truth** for phase, elapsed time, and the midnight cutoff. Never trust the client clock — a child must not be able to game the tablet clock.

---

## Data model

Use **soft deletes** on all tables. Timestamps on all tables.

### `kids`
- `id`
- `name`
- `pin` — hashed (store a hash, not plaintext; short numeric PIN)
- `color` — hex, for UI accenting
- `dark_mode` — boolean, per-kid theme preference
- Seed exactly **2 kids** (placeholder names/PINs Claudiu will edit).

### `categories`
- `id`
- `name`
- `active` — boolean (soft-disable without breaking history)
- Seed: `Game`, `TV`, `Tablet`.

### `cycle_settings`
Single global settings row (v1 is global, not per-kid — but structure it so per-kid override is easy later).
- `id`
- `work_minutes` — default `120`
- `break_minutes` — default `45`
- `cutoff_time` — default `00:00` (stored as time; represents the midnight lock)
- Editable by parent.

### `sessions`
One row per work or break segment.
- `id`
- `kid_id`
- `category_id` — nullable (breaks have no category)
- `category_name` — **snapshotted** category name at creation (so renaming/deleting a category never rewrites history)
- `phase` — enum: `work` | `break`
- `started_at`
- `ended_at` — nullable; **null = currently running**
- `duration_seconds` — computed on stop
- Soft deletes.

> A running segment = a `sessions` row with `ended_at IS NULL`. **Enforce max one running segment per kid** at the server. Elapsed time is always computed from `started_at` vs server `now()`, never stored while running.

### `daily_cycles` (or derive on the fly — your call)
Track, per kid per day, how much work has been accumulated in the **current** work phase and which phase the kid is in. Two acceptable approaches — pick the cleaner one:
- **Derive** phase + remaining time by querying today's `sessions` for the kid and applying `cycle_settings`, **or**
- **Persist** a lightweight per-kid-per-day cycle state row.
Prefer deriving if it stays readable; persist only if derivation gets tangled.

---

## Cycle engine (the heart of the app)

Implement a single server-side service (e.g. `CycleService`) that, given a `kid` and server `now()`, returns the child's **current state**:

- `phase`: `work` | `break` | `locked`
- `work_elapsed_seconds` in the current work phase
- `work_remaining_seconds`
- `break_remaining_seconds` (when in break)
- `is_running`: whether a segment is currently open
- `active_category` (when in a running work segment)
- `completed_cycles_today`, `breaks_today`
- `locked`: true once server time has passed the cutoff for the day

Rules the service enforces:

1. **Starting work**: only allowed if not locked, not currently in a forced break, and no segment is already running. Kid must pick a category.
2. **Stopping work**: closes the running segment, writes `duration_seconds`. Work-phase elapsed = sum of today's `work` segments since the last completed break.
3. **Phase completion**: when summed work in the current phase ≥ `work_minutes`, the kid is pushed into a **forced break**. Start is disabled; a break countdown runs.
4. **Break**: implement as either an explicit running `break` session or a timestamp the service counts down from — whichever is cleaner. Break ends when `break_minutes` elapse (server-computed). Then the next work phase unlocks.
5. **Midnight cutoff**: if server `now()` ≥ today's cutoff, state is `locked`, any open segment is auto-closed at the cutoff boundary, and counters reset for the new day.

The frontend **polls** this state (or re-fetches on an interval) and renders countdowns by computing from `started_at`/server timestamps — the server is authoritative, the client only displays.

---

## Screens / routes

### Kid picker (`/`)
- Tap a kid (name + color) → PIN entry → kid's timer screen.
- Store the logged-in kid in session.

### Kid timer screen (`/kid`)
- Big, touch-friendly (tablet-first).
- Shows current **phase** and a large **live countdown**.
- **Work phase, idle**: category chooser (Game / TV / Tablet) + big **Start**.
- **Work phase, running**: running category + elapsed / remaining + big **Stop**.
- **Forced break**: distinct break screen, break countdown, Start disabled.
- **Locked (post-midnight)**: "Done for today" screen.
- Today's summary: completed cycles, breaks taken, time per category.
- **Dark mode toggle**, remembered per kid.
- Own **history** view (past days, grouped by day → category).

### Parent view (`/parent`, PIN-gated — separate parent PIN)
- **Both kids side by side**, live status (phase + running category + countdown).
- **Reports**: by day and by week, totals per category per kid.
- **Edit cycle settings**: work minutes, break minutes, cutoff time.
- **Edit / delete sessions** (soft delete) — kids will forget to stop timers, parent corrects.
- Manage categories (add / rename / deactivate).

---

## UI / theming

- Tablet-first, large tap targets, clear phase states (work vs break should look obviously different).
- **Dark mode**: CSS custom properties for all colors; a `data-theme` attribute on `<html>` or `<body>`. Persist per kid (`kids.dark_mode`) and apply on load.
- Use each kid's `color` as an accent.
- Real, finished styling — not a wireframe.

---

## Conventions checklist

- [ ] Soft deletes on all tables
- [ ] Category name snapshotted onto sessions
- [ ] Server time authoritative for all phase/elapsed/cutoff logic
- [ ] One running segment per kid, enforced server-side
- [ ] PINs hashed, never stored plaintext
- [ ] `CHANGELOG.md` created, version set to `1.0.0`
- [ ] **Local only — do not deploy**

---

## Explicitly out of scope (v2+)

Per-category limits, alerts / push notifications, charts/graphs, OS-level device lockout (this app enforces in-app only; closing the browser is on the honor system + parent oversight), per-kid cycle overrides.
