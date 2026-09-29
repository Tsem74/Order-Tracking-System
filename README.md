# Patty's TV Board & Order Tracking System

A custom WordPress **mu-plugin** built for Patty's Smash Burger — a real-time
kitchen operations system that replaces manual order call-outs with a live
TV board, a touch-friendly counter/kitchen console, and a customer-facing
order tracker, backed by a WordPress/MySQL local layer and a Supabase sync
layer for live status.

Originally a single 1,450-line router file, restructured into a proper
plugin layout with separated concerns, real asset files instead of inline
base64, and a resilient local-first data model.

---

## What it does

- **Live Order TV Board** (`order-tv`) — a kitchen/lobby display showing
  orders currently cooking and ready for pickup, with a branded QR code
  customers can scan to track their own order.
- **Ops Console** (`counter-entry`, `kitchen-display`, `pickup-display`) —
  touch-screen keypad and status screens staff use to fire, progress, and
  complete orders in real time.
- **Order Tracker** (`track-your-order`) — a customer-facing page that
  polls live status and shows a progress timeline (Confirmed → Preparing →
  Ready), with toast notifications and sound/vibration alerts if an order
  is canceled.
- **Analytics Dashboard** — an admin-only page showing today's completed
  orders, average completion time, peak hour, and a full order log pulled
  from Supabase.
- **Supabase sync layer** — every status change is mirrored to Supabase so
  the public tracker page can read live order status without hitting the
  restaurant's local WordPress database directly.

---

## Architecture

```
tv-board-plugin-loader.php        ← stub required in mu-plugins/ (WordPress
                                     doesn't autoload subfolders there)
tv-board-plugin/
├── tv-board-plugin.php           ← main file, loads everything below in order
├── includes/
│   ├── styles-and-visibility.php     Global CSS, hides theme chrome on
│   │                                  custom pages, enqueues branded fonts
│   ├── api-data-layer.php            DB schema + the api_action endpoint
│   │                                  all front-end JS talks to
│   ├── tracker-header-injection.php  "Track your order" page header
│   ├── tracker-footer-script.php     Order progress polling/toast JS
│   ├── admin-menu.php                Registers the analytics admin page
│   ├── analytics-dashboard.php       Renders the analytics admin page
│   └── supabase-sync.php             Push/pull helpers for Supabase
├── templates/
│   ├── page-live-tv.php              The live order TV board
│   └── page-ops-console.php          Counter / kitchen / pickup screens
└── assets/
    ├── css/tv-board-fonts.css        Branded @font-face declarations
    ├── fonts/                        Real .ttf font files
    └── images/                       Logo + QR code assets
```

**Data flow:** staff actions on the ops console hit `api_action` endpoints
in `api-data-layer.php`, which write to the local `wpdb` orders table
*and* push the same update to Supabase via `supabase-sync.php`. The public
tracker page polls status against the local WordPress endpoint, so it
keeps working even if Supabase is briefly unavailable — Supabase is the
sync/analytics layer, not the source of truth for kitchen operations.

Order numbers use a `ticket_id`-based architecture to avoid collisions when
order numbers wrap around during a busy shift.

---

## Tech stack

- **WordPress mu-plugin** (PHP), no external plugin dependencies
- **MySQL** via `$wpdb` for local order state
- **Supabase** (Postgres + REST) for live sync and completed-order history
- Vanilla JS (polling, toasts, Web Audio for alert chimes) — no build step
- Deployed/tested locally via **XAMPP**, with migration prep underway for
  a production host

---

## Installation

This is a **must-use plugin**, not a regular one — it loads automatically
on every page load with no activation step and no on/off toggle in
wp-admin.

1. Copy both of these into `wp-content/mu-plugins/`:
   - `tv-board-plugin-loader.php` (directly in `mu-plugins/`)
   - the whole `tv-board-plugin/` folder (directly in `mu-plugins/`)
2. Set your Supabase URL/key via the `pattys_supabase_url` and
   `pattys_supabase_key` WordPress options.
3. Reload any page — mu-plugins load immediately. Test `order-tv`,
   `counter-entry`, `kitchen-display`, and `track-your-order`.

Full install notes and folder layout are also documented inline in the
plugin's own `README.md`.

---

## Known issues / roadmap

- A schema-normalizing `ALTER TABLE` currently runs on the WordPress
  `init` hook — meaning it fires on *every* page load site-wide, not just
  the 5 custom pages. Needs to be gated to run once.
- Order tracker polls every 1.5s; likely safe to relax to 3–5s.
- Supabase calls on the analytics dashboard are synchronous and block
  render if Supabase is slow.
- Remaining inline `<style>`/`<script>` blocks (dynamic PHP values mixed
  into markup) haven't been split into static assets yet.
- Production deployment plan (moving off XAMPP, confirming no
  localhost-hardcoded URLs) still in progress.

---

## Recent history

- Fixed a critical silent DB insert bug (wrong column name breaking all
  local inserts since late June)
- iOS audio compatibility fixes for the alert chime
- Extracted 5 embedded base64 fonts and a duplicated logo into real asset
  files, cutting page sizes dramatically (largest template went from
  ~276KB to ~115KB)
- Fixed a stray folder created by a failed shell brace-expansion
- Supabase schema migrations and `ticket_id` order-collision handling
