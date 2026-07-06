<?php
/**
 * Renders the Executive Analytics Dashboard admin page
 *
 * @package TV_Board_Plugin
 */

if (!defined('ABSPATH')) exit; // No direct access

function render_pattys_analytics_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . "ewd_otp_orders";
    $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    $supabase_url = get_option('pattys_supabase_url');
    $supabase_key = get_option('pattys_supabase_key');

    // Fetch today's completed orders from Supabase
    $today_start = date('Y-m-d') . 'T00:00:00+01:00';
    $response = wp_remote_get(
        $supabase_url . '/rest/v1/completed_orders?completed_at=gte.' . urlencode($today_start) . '&order=completed_at.desc&select=*',
        ['headers' => ['apikey' => $supabase_key, 'Authorization' => 'Bearer ' . $supabase_key]]
    );

    $completed = [];
    $avg_duration = 0;
    $hourly = array_fill(0, 24, 0);
    $peak_hour = 0;

    if (!is_wp_error($response)) {
        $completed = json_decode(wp_remote_retrieve_body($response), true) ?? [];
        if (!empty($completed)) {
            $durations = array_filter(array_column($completed, 'duration_seconds'));
            if (!empty($durations)) $avg_duration = round(array_sum($durations) / count($durations));
            foreach ($completed as $o) {
                $h = (int) date('G', strtotime($o['completed_at']));
                $hourly[$h]++;
            }
            $peak_hour = array_search(max($hourly), $hourly);
        }
    }

    $today_count   = count($completed);
    $avg_min       = $avg_duration > 0 ? floor($avg_duration / 60) . 'm ' . ($avg_duration % 60) . 's' : '—';
    $peak_label    = $today_count > 0 ? sprintf('%02d:00 – %02d:00', $peak_hour, $peak_hour + 1) : '—';
    $hourly_max    = max($hourly) ?: 1;
    ?>
    <style>
        #pattys-dash body[dir="rtl"] h2, body[dir="rtl"] h3, body[dir="rtl"] p { font-family: sans-serif !important; }
            * { box-sizing: border-box; }
        #pattys-dash {
            background: #0f0a1a;
            color: #fff;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            font-family: sans-serif;
            margin-left: -20px;
            margin-top: -10px;
        }
        #pattys-dash .dash-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(249,132,31,0.2);
        }
        #pattys-dash .dash-title {
            font-size: 2.2rem;
            font-weight: 900;
            margin: 0;
            background: linear-gradient(90deg, #f9841f, #ffb74d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        #pattys-dash .dash-date {
            color: #6b5a8a;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 0.3rem;
        }
        #pattys-dash .live-badge {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(76,175,80,0.12);
            border: 1px solid rgba(76,175,80,0.3);
            border-radius: 20px;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: #81c784;
        }
        #pattys-dash .live-dot {
            width: 7px; height: 7px;
            background: #81c784;
            border-radius: 50%;
            animation: pdash-pulse 1.5s infinite alternate;
        }
        @keyframes pdash-pulse { 0%{opacity:0.3} 100%{opacity:1} }

        /* KPI GRID */
        #pattys-dash .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        #pattys-dash .kpi-card {
            background: linear-gradient(135deg, #1e1133 0%, #281a42 100%);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s;
        }
        #pattys-dash .kpi-card:hover { transform: translateY(-2px); }
        #pattys-dash .kpi-card.highlight {
            border-color: rgba(249,132,31,0.4);
            background: linear-gradient(135deg, #2a1500 0%, #3d1f00 100%);
        }
        #pattys-dash .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: rgba(255,255,255,0.05);
            border-radius: 16px 16px 0 0;
        }
        #pattys-dash .kpi-card.highlight::before { background: linear-gradient(90deg, #f9841f, #ffb74d); }
        #pattys-dash .kpi-icon { font-size: 1.6rem; margin-bottom: 0.75rem; display: block; }
        #pattys-dash .kpi-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #6b5a8a;
            margin-bottom: 0.4rem;
        }
        #pattys-dash .kpi-value {
            font-size: 2.8rem;
            font-weight: 900;
            line-height: 1;
            color: #fff;
        }
        #pattys-dash .kpi-card.highlight .kpi-value { color: #f9841f; }
        #pattys-dash .kpi-sub {
            font-size: 0.75rem;
            color: #6b5a8a;
            margin-top: 0.5rem;
            font-weight: 600;
        }
        #pattys-dash .kpi-sub.good { color: #81c784; }
        #pattys-dash .kpi-sub.warn { color: #ffb74d; }

        /* CHART */
        #pattys-dash .chart-card {
            background: linear-gradient(135deg, #1e1133 0%, #281a42 100%);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        #pattys-dash .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        #pattys-dash .card-title span { color: #6b5a8a; font-size: 0.85rem; font-weight: 600; }
        #pattys-dash .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 5px;
            height: 140px;
            padding-bottom: 24px;
            position: relative;
        }
        #pattys-dash .bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            height: 100%;
        }
        #pattys-dash .bar-count {
            font-size: 0.6rem;
            font-weight: 700;
            color: #f9841f;
            min-height: 10px;
        }
        #pattys-dash .bar-fill {
            width: 100%;
            border-radius: 4px 4px 0 0;
            transition: height 0.3s ease;
            min-height: 3px;
        }
        #pattys-dash .bar-lbl {
            font-size: 0.5rem;
            color: #6b5a8a;
            position: absolute;
            bottom: 0;
        }

        /* TABLE */
        #pattys-dash .table-card {
            background: linear-gradient(135deg, #1e1133 0%, #281a42 100%);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 1.5rem;
            overflow: hidden;
        }
        #pattys-dash table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        #pattys-dash thead tr {
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        #pattys-dash th {
            padding: 0 12px 12px;
            text-align: left;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b5a8a;
        }
        #pattys-dash td {
            padding: 14px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        #pattys-dash tbody tr:last-child td { border-bottom: none; }
        #pattys-dash tbody tr:hover td { background: rgba(255,255,255,0.02); }
        #pattys-dash .order-num-cell {
            font-size: 1.2rem;
            font-weight: 900;
            color: #fff;
        }
        #pattys-dash .time-cell { color: #bcaed4; font-size: 0.9rem; }
        #pattys-dash .dur-cell { font-weight: 700; color: #fff; }
        #pattys-dash .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        #pattys-dash .badge-fast { background: rgba(76,175,80,0.15); color: #81c784; border: 1px solid rgba(76,175,80,0.3); }
        #pattys-dash .badge-ok   { background: rgba(255,183,77,0.15); color: #ffb74d; border: 1px solid rgba(255,183,77,0.3); }
        #pattys-dash .badge-slow { background: rgba(239,83,80,0.15);  color: #ef5350; border: 1px solid rgba(239,83,80,0.3); }
        #pattys-dash .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b5a8a;
        }
        #pattys-dash .empty-state .empty-icon { font-size: 3rem; margin-bottom: 0.75rem; display: block; opacity: 0.4; }
        #pattys-dash .empty-state p { margin: 0; font-size: 0.95rem; font-weight: 600; }
    </style>

    <div id="pattys-dash">
        <div class="dash-topbar">
            <div>
                <h1 class="dash-title">🍔 Patty's Dashboard</h1>
                <div class="dash-date"><?php echo date('l, F j, Y — H:i'); ?></div>
            </div>
            <div class="live-badge"><div class="live-dot"></div> LIVE DATA</div>
        </div>

        <!-- KPI CARDS -->
        <div class="kpi-grid">
            <div class="kpi-card highlight">
                <span class="kpi-icon">📦</span>
                <div class="kpi-label">Orders Today</div>
                <div class="kpi-value"><?php echo $today_count; ?></div>
                <div class="kpi-sub good">● Completed & logged</div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon">⏱️</span>
                <div class="kpi-label">Avg Completion</div>
                <div class="kpi-value" style="font-size:<?php echo strlen($avg_min) > 5 ? '1.8rem' : '2.8rem'; ?>"><?php echo $avg_min; ?></div>
                <div class="kpi-sub <?php echo ($avg_duration > 0 && $avg_duration < 900) ? 'good' : ($avg_duration >= 900 ? 'warn' : ''); ?>">
                    <?php echo $avg_duration > 0 ? ($avg_duration < 900 ? '✓ Under target' : '⚠ Over 15 min avg') : 'No data yet'; ?>
                </div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon">🔥</span>
                <div class="kpi-label">Peak Hour</div>
                <div class="kpi-value" style="font-size:1.5rem;color:#ffb74d;"><?php echo $peak_label; ?></div>
                <div class="kpi-sub"><?php echo $today_count > 0 ? $hourly[$peak_hour] . ' orders that hour' : 'No data yet'; ?></div>
            </div>
            <div class="kpi-card">
                <span class="kpi-icon">🏆</span>
                <div class="kpi-label">All-Time Orders</div>
                <div class="kpi-value"><?php echo $total_orders ?: 0; ?></div>
                <div class="kpi-sub">Since system launch</div>
            </div>
        </div>

        <!-- HOURLY BAR CHART -->
        <div class="chart-card">
            <div class="card-title">📊 Orders per Hour <span>— Today</span></div>
            <div class="bar-chart">
                <?php for ($h = 0; $h < 24; $h++):
                    $count  = $hourly[$h];
                    $pct    = round(($count / $hourly_max) * 100);
                    $is_peak = ($h === $peak_hour && $count > 0);
                    $bg     = $is_peak ? 'linear-gradient(180deg,#f9841f,#b85b0d)' : ($count > 0 ? 'rgba(61,40,100,0.9)' : 'rgba(30,17,51,0.5)');
                    $height = $count > 0 ? max($pct, 8) : 3;
                ?>
                <div class="bar-wrap">
                    <div class="bar-count"><?php echo $count > 0 ? $count : ''; ?></div>
                    <div class="bar-fill" style="height:<?php echo $height; ?>%;background:<?php echo $bg; ?>;<?php echo $is_peak ? 'box-shadow:0 0 8px rgba(249,132,31,0.4);' : ''; ?>"></div>
                    <div class="bar-lbl" style="position:static;"><?php printf('%02d', $h); ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- ORDER LOG TABLE -->
        <div class="table-card">
            <div class="card-title">📋 Order Log <span>— Today (most recent first)</span></div>
            <?php if (empty($completed)): ?>
                <div class="empty-state">
                    <span class="empty-icon">🍽️</span>
                    <p>No completed orders yet today.<br>Complete an order from the pickup screen to see it here.</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Placed</th>
                        <th>Completed</th>
                        <th>Duration</th>
                        <th>Speed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completed as $i => $o):
                        $dur = $o['duration_seconds'];
                        $dur_label = $dur ? floor($dur/60) . 'm ' . ($dur % 60) . 's' : '—';
                        if ($dur > 900)      { $badge_cls = 'badge-slow'; $badge_txt = '🐢 Slow'; }
                        elseif ($dur > 600)  { $badge_cls = 'badge-ok';   $badge_txt = '👌 OK'; }
                        else                 { $badge_cls = 'badge-fast';  $badge_txt = '⚡ Fast'; }
                    ?>
                    <tr>
                        <td style="color:#6b5a8a;font-size:0.8rem;"><?php echo $i + 1; ?></td>
                        <td class="order-num-cell">#<?php echo esc_html($o['order_number']); ?></td>
                        <td class="time-cell"><?php echo $o['created_at'] ? date('H:i', strtotime($o['created_at'])) : '—'; ?></td>
                        <td class="time-cell"><?php echo date('H:i', strtotime($o['completed_at'])); ?></td>
                        <td class="dur-cell"><?php echo $dur_label; ?></td>
                        <td><span class="badge <?php echo $badge_cls; ?>"><?php echo $badge_txt; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}