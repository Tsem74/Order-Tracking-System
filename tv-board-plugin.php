<?php
/**
 * Plugin Name: Patty's TV Board & Order Tracking
 * Description: Live order TV board, kitchen/counter/pickup ops console, order tracker, and analytics dashboard. Split into logical files under /includes and /templates instead of one monolithic router.
 * Version:     1.0.0
 *
 * @package TV_Board_Plugin
 */

if (!defined('ABSPATH')) exit; // No direct access

define('TV_BOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Files are loaded in a deliberate order:
 * 1) Global styles / theme-chrome hiding (needed on every custom page)
 * 2) Data layer (DB schema + the api_action endpoint the front-end JS calls)
 * 3) Page templates (TV board, ops console)
 * 4) Order tracker header/footer injections
 * 5) Admin menu + the page it points to
 * 6) Supabase sync helpers (used by the api layer and analytics page)
 */
$tv_board_includes = [
    // Front-end plumbing
    'includes/styles-and-visibility.php',
    'includes/api-data-layer.php',

    // Public-facing page templates
    'templates/page-live-tv.php',
    'templates/page-ops-console.php',

    // "Track your order" widget
    'includes/tracker-header-injection.php',
    'includes/tracker-footer-script.php',

    // Admin / analytics
    'includes/admin-menu.php',
    'includes/analytics-dashboard.php',
    'includes/supabase-sync.php',
];

foreach ($tv_board_includes as $tv_board_file) {
    $tv_board_path = TV_BOARD_PLUGIN_DIR . $tv_board_file;
    if (file_exists($tv_board_path)) {
        require_once $tv_board_path;
    }
}
