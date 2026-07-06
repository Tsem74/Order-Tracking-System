<?php
/**
 * Data layer: DB schema tweak + api_action REST-style endpoint handler
 *
 * @package TV_Board_Plugin
 */

if (!defined('ABSPATH')) exit; // No direct access

add_action('init', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . "ewd_otp_orders";

    // Enforce loose schema constraint rules
    $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN Order_Status VARCHAR(100) DEFAULT 'Order Confirmed'");

    if (!isset($_GET['api_action'])) return;
    $action = $_GET['api_action'];

    $cooking = get_option('pattys_cache_cooking', []);
    $ready   = get_option('pattys_cache_ready', []);

    if ($action === 'add_order' && isset($_POST['order_num'])) {
        $num = sanitize_text_field($_POST['order_num']);
        $cooking = array_diff($cooking, [$num]);
        $ready = array_diff($ready, [$num]);

        $cooking[] = $num;
        update_option('pattys_cache_cooking', array_values($cooking));
        update_option('pattys_cache_ready', array_values($ready));

        $timestamps = get_option('pattys_order_timestamps', []);
        $timestamps[$num] = time();
        update_option('pattys_order_timestamps', $timestamps);
        
        $wpdb->delete($table_name, ['Order_Number' => $num]);
        $wpdb->insert($table_name, [
            'Order_Number' => $num, 
            'Order_Status' => 'Order Confirmed', 
            'Order_Name' => 'Counter Order #' . $num, 
            'Order_Created_Time' => current_time('mysql')
        ]);

        pattys_sync_to_supabase($num, 'Order Confirmed');

        wp_send_json_success();
        exit;
    }

    if ($action === 'update_status' && isset($_POST['order_num']) && isset($_POST['new_status'])) {
        $num = sanitize_text_field($_POST['order_num']);
        $status = sanitize_text_field($_POST['new_status']);
        
        $cooking = array_diff($cooking, [$num]);
        $ready = array_diff($ready, [$num]);

        if ($status === 'Preparing Food') {
            $cooking[] = $num;
        } else if ($status === 'Ready for Pickup') {
            $ready[] = $num;
        }
        
        update_option('pattys_cache_cooking', array_values($cooking));
        update_option('pattys_cache_ready', array_values($ready));

        if ($status === 'DELETE') {
            $created_at = pattys_get_order_created_at($num);
            $wpdb->delete($table_name, ['Order_Number' => $num]);
            pattys_log_completed_order($num, $created_at);
            $timestamps = get_option('pattys_order_timestamps', []);
            unset($timestamps[$num]);
            update_option('pattys_order_timestamps', $timestamps);
        } else {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE Order_Number = %s", $num));
            if (!$exists) {
                $wpdb->insert($table_name, [
                    'Order_Number' => $num, 
                    'Order_Status' => $status, 
                    'Order_Name' => 'Counter Order #' . $num,
                    'Order_Created_Time' => current_time('mysql')
                ]);
            } else {
                $wpdb->update($table_name, ['Order_Status' => $status], ['Order_Number' => $num]);
            }
        }

        pattys_sync_to_supabase($num, $status);

        wp_send_json_success();
        exit;
    }

    if ($action === 'restore_order' && isset($_POST['order_num'])) {
        $num = sanitize_text_field($_POST['order_num']);
        $cooking = array_diff($cooking, [$num]);
        $ready = array_diff($ready, [$num]);
        $cooking[] = $num;
        update_option('pattys_cache_cooking', array_values($cooking));
        update_option('pattys_cache_ready', array_values($ready));

        $deleted = get_option('pattys_cache_deleted', []);
        $deleted = array_filter($deleted, function($d) use ($num) { return $d['num'] !== $num; });
        update_option('pattys_cache_deleted', array_values($deleted));

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE Order_Number = %s", $num));
        if (!$exists) {
            $wpdb->insert($table_name, [
                'Order_Number' => $num,
                'Order_Status' => 'Preparing Food',
                'Order_Name' => 'Counter Order #' . $num,
                'Order_Created_Time' => current_time('mysql')
            ]);
        }

        pattys_sync_to_supabase($num, 'Preparing Food');

        wp_send_json_success();
        exit;
    }

    if ($action === 'send_back' && isset($_POST['order_num'])) {
        $num = sanitize_text_field($_POST['order_num']);
        $cooking = array_diff($cooking, [$num]);
        $ready = array_diff($ready, [$num]);
        update_option('pattys_cache_cooking', array_values($cooking));
        update_option('pattys_cache_ready', array_values($ready));

        $wpdb->delete($table_name, ['Order_Number' => $num]);

        $deleted = get_option('pattys_cache_deleted', []);
        $deleted = array_filter($deleted, function($d) use ($num) { return $d['num'] !== $num; });
        array_unshift($deleted, ['num' => $num, 'time' => time()]);
        $deleted = array_slice($deleted, 0, 8);
        update_option('pattys_cache_deleted', $deleted);

        pattys_sync_to_supabase($num, 'Sent Back');

        wp_send_json_success();
        exit;
    }

    if ($action === 'cancel_order' && isset($_POST['order_num'])) {
        $num = sanitize_text_field($_POST['order_num']);
        $cooking = array_diff($cooking, [$num]);
        $ready = array_diff($ready, [$num]);
        update_option('pattys_cache_cooking', array_values($cooking));
        update_option('pattys_cache_ready', array_values($ready));

        $created_at = pattys_get_order_created_at($num);
        $wpdb->delete($table_name, ['Order_Number' => $num]);
        pattys_log_completed_order($num, $created_at);

        $timestamps = get_option('pattys_order_timestamps', []);
        unset($timestamps[$num]);
        update_option('pattys_order_timestamps', $timestamps);

        $deleted = get_option('pattys_cache_deleted', []);
        $deleted = array_filter($deleted, function($d) use ($num) { return $d['num'] !== $num; });
        array_unshift($deleted, ['num' => $num, 'time' => time()]);
        $deleted = array_slice($deleted, 0, 8);
        update_option('pattys_cache_deleted', $deleted);

        pattys_sync_to_supabase($num, 'Canceled');

        wp_send_json_success();
        exit;
    }

    if ($action === 'get_deleted') {
        $deleted = get_option('pattys_cache_deleted', []);
        $deleted = array_filter($deleted, function($d) { return time() - $d['time'] < 600; });
        $deleted = array_values($deleted);
        update_option('pattys_cache_deleted', $deleted);
        wp_send_json_success(['deleted' => $deleted]);
        exit;
    }

    if ($action === 'remove_from_deleted' && isset($_POST['order_num'])) {
        $num = sanitize_text_field($_POST['order_num']);
        $deleted = get_option('pattys_cache_deleted', []);
        $deleted = array_filter($deleted, function($d) use ($num) { return $d['num'] !== $num; });
        update_option('pattys_cache_deleted', array_values($deleted));
        wp_send_json_success();
        exit;
    }

    if ($action === 'get_order_status') {
        if (isset($_GET['order_id'])) {
            $order_id = sanitize_text_field($_GET['order_id']);
            
            if (in_array($order_id, $ready)) {
                $resolved_status = 'Ready for Pickup';
            } else if (in_array($order_id, $cooking)) {
                $resolved_status = 'Preparing Food';
            } else {
                $db_status = $wpdb->get_var($wpdb->prepare("SELECT Order_Status FROM $table_name WHERE Order_Number = %s", $order_id));
                $resolved_status = $db_status ? $db_status : 'NOT_FOUND';
            }
            
            wp_send_json_success(['status' => $resolved_status]);
            exit;
        }
        $timestamps = get_option('pattys_order_timestamps', []);
        $cooking_with_time = [];
        foreach ($cooking as $num) {
            $cooking_with_time[] = ['num' => $num, 'ts' => isset($timestamps[$num]) ? (int)$timestamps[$num] : 0];
        }
        $ready_with_time = [];
        foreach ($ready as $num) {
            $ready_with_time[] = ['num' => $num, 'ts' => isset($timestamps[$num]) ? (int)$timestamps[$num] : 0];
        }
        wp_send_json_success(['cooking' => $cooking_with_time, 'ready' => $ready_with_time]);
        exit;
    }
});

// Nuclear Option: Intercept the page content string right before output to clear the shortcode conflict
add_filter('the_content', function($content) {
    if (has_shortcode($content, 'ewd-otp-tracking-form') || strpos($content, '[ewd-otp-tracking-form]') !== false) {
        $clean_search_ui = '
        <div class="patty-clean-search-box">
            <h2>Track Your Smash Burger Live</h2>
            <div class="patty-search-row">
                <input type="text" id="patty-manual-num" class="patty-clean-input" placeholder="Enter Order Number (e.g. 12)" pattern="[0-9]*" inputmode="numeric">
                <button type="button" id="patty-manual-submit" class="patty-clean-submit">TRACK</button>
            </div>
        </div>
        <div id="patty-tracker-mount-point"></div>';
        
        return str_replace('[ewd-otp-tracking-form]', $clean_search_ui, $content);
    }
    return $content;
}, 99999);

