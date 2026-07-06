<?php
/**
 * Injects the live tracker header markup on the 'track-your-order' page
 *
 * @package TV_Board_Plugin
 */

if (!defined('ABSPATH')) exit; // No direct access

add_action('wp_body_open', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'track-your-order') === false) return;
    echo '
    <header class="custom-nav-bar">
        <div class="header-brand">
            <h1 class="brand-title">PATTY\'S SMASH BURGER</h1>
            <div class="status-monitor"><span class="status-dot">•</span> LIVE KITCHEN MONITOR</div>
        </div>
        <div class="header-actions"><div class="header-time" id="headerClock">00:00</div></div>
    </header>';
});

