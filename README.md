# Patty's TV Board & Order Tracking — restructured

Your original file was one 1,450-line `.php` file mixing PHP logic, inline CSS,
and inline JavaScript for six different features. It's now split into a small
plugin folder, matching how a professional WordPress plugin is organized:

```
tv-board-plugin/
├── tv-board-plugin.php          ← main file: just loads everything below, in order
├── includes/
│   ├── styles-and-visibility.php     (wp_head: global CSS + hide theme chrome)
│   ├── api-data-layer.php            (init: DB schema + api_action endpoint)
│   ├── tracker-header-injection.php  (wp_body_open: "track your order" header)
│   ├── tracker-footer-script.php     (wp_footer: order progress tracker JS)
│   ├── admin-menu.php                (admin_menu: analytics menu item)
│   ├── analytics-dashboard.php       (renders the analytics admin page)
│   └── supabase-sync.php             (3 helper functions: push/pull Supabase data)
└── templates/
    ├── page-live-tv.php              (the live order TV board)
    └── page-ops-console.php          (counter keypad / kitchen / pickup screens)
```

## How to install it (mu-plugins version)
Your original file lived in `wp-content/mu-plugins/`, so this package is
built for that folder instead of the regular `plugins/` folder. Two key
differences from a normal plugin:
- **No activation step** — anything WordPress finds in `mu-plugins/` runs
  automatically on every page load. There's no on/off toggle in wp-admin.
- **WordPress does NOT look inside subfolders of `mu-plugins/`.** It only
  auto-loads `.php` files sitting directly in that folder. That's why this
  package includes `tv-board-plugin-loader.php` — a tiny stub whose only job
  is to `require` the real code from the `tv-board-plugin/` subfolder.

Install steps:
1. Copy **both** of these into `C:\xampp\htdocs\wordpress\wp-content\mu-plugins\`:
   - `tv-board-plugin-loader.php` (goes directly in `mu-plugins/`)
   - `tv-board-plugin/` (the whole subfolder, goes directly in `mu-plugins/`)

   So you end up with:
   ```
   wp-content/mu-plugins/
   ├── tv-board-plugin-loader.php
   └── tv-board-plugin/
       ├── tv-board-plugin.php
       ├── includes/...
       └── templates/...
   ```
2. Delete your old single router `.php` file from `mu-plugins/` so it isn't
   running alongside the new split version (that would double-register
   everything and likely throw "cannot redeclare function" fatal errors).
3. Reload any page on the site — mu-plugins load immediately, nothing to
   activate. Test `order-tv`, `counter-entry`, `kitchen-display`, and
   `track-your-order` to confirm everything still works.

## What changed vs. the original file
- **Nothing about the logic changed.** Every `add_action`, query, and script
  was moved verbatim into its own file — I didn't rewrite any behavior.
- Each file now starts with a doc-comment describing what it does, and an
  `if (!defined('ABSPATH')) exit;` guard (standard practice so the file can't
  be requested directly over the web).
- `tv-board-plugin.php` just requires the other files in a sensible order —
  it's the only file WordPress actually loads directly.

## Note on the inline `<style>`/`<script>` blocks
The CSS and JS inside `page-live-tv.php`, `page-ops-console.php`, and
`tracker-footer-script.php` are still inline (not split into separate
`.css`/`.js` files) because they contain live PHP variables (e.g. order
statuses, URLs, dynamic colors) mixed directly into the markup. Pulling those
out into static asset files is possible but needs each dynamic value passed
through `wp_localize_script()` or a data attribute — happy to do that next if
you want proper `assets/css/` and `assets/js/` files with cache-busting via
`wp_enqueue_style()` / `wp_enqueue_script()`.
