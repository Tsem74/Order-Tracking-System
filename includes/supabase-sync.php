<?php
/**
 * Supabase sync helpers: push status, fetch created_at, log completed orders
 *
 * @package TV_Board_Plugin
 */

if (!defined('ABSPATH')) exit; // No direct access

function pattys_sync_to_supabase($order_number, $status) {
    $supabase_url = get_option('pattys_supabase_url');
    $supabase_key = get_option('pattys_supabase_key');

    if ($status === 'DELETE') {
        wp_remote_request($supabase_url . '/rest/v1/live_orders?order_number=eq.' . urlencode($order_number), [
            'method'  => 'DELETE',
            'headers' => [
                'apikey'        => $supabase_key,
                'Authorization' => 'Bearer ' . $supabase_key,
                'Content-Type'  => 'application/json',
            ],
        ]);
    } else {
        wp_remote_post($supabase_url . '/rest/v1/live_orders', [
            'headers' => [
                'apikey'        => $supabase_key,
                'Authorization' => 'Bearer ' . $supabase_key,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'resolution=merge-duplicates',
            ],
            'body' => json_encode([
                'order_number' => $order_number,
                'status'       => $status,
                'updated_at'   => current_time('c'),
            ]),
        ]);
    }
}

// ─── 9. GET ORDER CREATED_AT FROM SUPABASE ────────────────────────────────────
function pattys_get_order_created_at($order_number) {
    $supabase_url = get_option('pattys_supabase_url');
    $supabase_key = get_option('pattys_supabase_key');

    $response = wp_remote_get(
        $supabase_url . '/rest/v1/live_orders?order_number=eq.' . urlencode($order_number) . '&select=created_at',
        [
            'headers' => [
                'apikey'        => $supabase_key,
                'Authorization' => 'Bearer ' . $supabase_key,
            ],
        ]
    );

    if (is_wp_error($response)) return null;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return (!empty($data) && isset($data[0]['created_at'])) ? $data[0]['created_at'] : null;
}

// ─── 10. LOG COMPLETED ORDER TO SUPABASE ─────────────────────────────────────
function pattys_log_completed_order($order_number, $created_at) {
    $supabase_url = get_option('pattys_supabase_url');
    $supabase_key = get_option('pattys_supabase_key');

    $completed_at = current_time('c');
    $duration = null;

    if ($created_at) {
        $duration = (int) (strtotime($completed_at) - strtotime($created_at));
    }

    wp_remote_post($supabase_url . '/rest/v1/completed_orders', [
        'headers' => [
            'apikey'        => $supabase_key,
            'Authorization' => 'Bearer ' . $supabase_key,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=minimal',
        ],
        'body' => json_encode([
            'order_number'   => $order_number,
            'created_at'     => $created_at ?? $completed_at,
            'completed_at'   => $completed_at,
            'duration_seconds' => $duration,
        ]),
    ]);
}

