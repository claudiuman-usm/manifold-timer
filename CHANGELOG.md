# Changelog

All notable changes to Manifold Timer are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.3.0] — 2026-07-30

### Added — single PIN gate (app is no longer publicly browsable)
- The public kid picker is gone. Everyone — both kids and the parent — lands on
  **one PIN pad**; the PIN entered identifies who you are (a kid → their timer,
  the parent PIN → the dashboard). No names or details are shown until a valid
  PIN is entered. Attempts are rate-limited per IP.
- Parent PIN takes precedence; kid PINs should be unique.

### Changed — glassmorphism UI (platform-wide)
- Gradient background (tinted per kid's accent colour), **frosted-glass** cards
  with `backdrop-blur`, and **gradient card borders** (masked pseudo-element),
  in both light and dark themes. Accent/work/danger buttons are now gradients.
- Falls back to solid surfaces where `backdrop-filter` is unsupported.

### Notes
- Removed the old `picker` / `pin/{kid}` / parent-login screens; `/parent/login`
  now redirects to the gate. No database change.
- Security scope: this hides the app behind PINs. For stronger production
  hiding, add HTTP Basic Auth or an IP allowlist at the web server (see README).

## [1.2.1] — 2026-07-30

### Fixed / changed (kid screen UI)
- Replaced the top-bar emoji controls with a minimalist **solid icon set**
  (history, change-PIN, theme, exit), borderless and larger.
- Fixed the theme toggle showing both moon **and** sun at once (CSS specificity);
  it now shows the moon in light mode and the sun in dark mode.
- The countdown now sits **centred inside the ring** and is no longer clipped.
- Added a clearer, accent-coloured **`remaining: HH:MM`** label under the timer.
- Home screen heading changed from "Who's playing?" to **"Who are you?"**.

## [1.2.0] — 2026-07-29

### Added
- **Kid alerts.** On the timer screen the kid now gets a sound + an on-screen
  banner **5 minutes before** their play time ends and **when it ends**
  (work → forced break), plus "break over" and "done for today" cues.
- If the kid has switched to another browser tab, a **system notification** is
  raised too (asks permission on first tap). No push server — everything is
  driven client-side from the authoritative server countdown.

### Notes
- The 5-minute warning fires once per work phase and is skipped when a kid's
  work phase is shorter than 5 minutes. No schema change in this release.

## [1.1.0] — 2026-07-29

### Added
- **Per-kid cycle settings.** Each kid can now have their own work / break /
  cutoff, overriding the global default. `kids` gains nullable `work_minutes`,
  `break_minutes`, `cutoff_time` (NULL = inherit the default). `CycleService`
  resolves effective settings per kid via `settingsFor()`.
- **Parent kid management**: edit each kid's name and accent colour; set or reset
  a kid's custom cycle; a "Custom / Using default" badge per kid.
- **Open a kid's screen for testing** — a parent-dashboard button signs the
  browser in as that kid without needing the kid's PIN.
- **Kids can change their own PIN** from the timer screen (⚙ button).

### Notes
- Global "Cycle settings" is now labelled **Default cycle settings** and applies
  to any kid without a custom cycle.
- Schema change for production is documented in
  `database/schema/mysql-schema.sql` (v1.1.0 `ALTER TABLE kids`).

## [1.0.0] — 2026-07-29

### Initial build

First working version of Manifold Timer — a self-hosted screen-time app that
drives two children through a repeating **work → forced break** cycle, locked at
midnight, with server time as the single source of truth.

**Core**
- Server-authoritative `CycleService` derives each kid's state (work / break /
  locked) purely from the day's sessions plus global cycle settings. Elapsed and
  remaining time are always computed from `started_at` vs server `now()` — the
  client only displays.
- Forced-break engine: a work phase completes once summed work reaches
  `work_minutes` (splittable across several start/stops), then a mandatory
  `break_minutes` countdown; start is disabled until it ends.
- Midnight cutoff: at the configured lock time an open segment is auto-closed at
  the boundary and daily counters reset. `cutoff_time = 00:00` means a clean
  midnight reset; any other time is an early "done for today" lock.
- One running segment per kid, enforced server-side (row lock + guards).

**Data**
- Tables `kids`, `categories`, `cycle_settings`, `timer_sessions` — all with
  timestamps and soft deletes.
- Category name is snapshotted onto each session, so renaming/deleting a category
  never rewrites history.
- PINs stored as bcrypt hashes, never plaintext.
- Seed: 2 placeholder kids, categories Game / TV / Tablet, one global settings
  row (120 min work / 45 min break / 00:00 cutoff).

**Screens**
- Kid picker → PIN pad → tablet-first timer with a live countdown ring, category
  chooser, distinct work / break / locked states, per-kid dark mode, today's
  summary, and a per-day history view.
- Parent view (separate PIN): both kids live side by side, cycle-settings editor,
  category management (add / rename / enable-disable / soft delete), session
  corrections (edit times / soft delete), and day + week reports per category.

**Theming**
- Full light/dark themes via CSS custom properties and a `data-theme` attribute;
  dark mode persisted per kid. Each kid's colour is used as a UI accent.

**Ops**
- Local dev uses the SQLite driver; production mirrors to MySQL.
- Plain MySQL DDL for hand-application in phpMyAdmin at
  `database/schema/mysql-schema.sql` (production has no SSH).
- Parent PIN lives in `.env` (`PARENT_PIN`), separate from kids' PINs.

_Local only — not deployed._
