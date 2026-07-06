<?php
/**
 * Registers the 'Patty's Analytics' admin menu page
 *
 * @package TV_Board_Plugin
 */

if (!defined('ABSPATH')) exit; // No direct access

add_action('admin_menu', function() {
    add_menu_page(
        "Patty's Analytics",
        "Patty's Stats",
        'manage_options',
        'pattys-analytics',
        'render_pattys_analytics_page',
        'dashicons-chart-bar',
        6
    );
});

