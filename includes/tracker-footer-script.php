<?php
/**
 * Order progress tracker script (polling, toasts, cancel/restore) for 'track-your-order'
 *
 * @package TV_Board_Plugin
 */

if (!defined('ABSPATH')) exit; // No direct access

add_action('wp_footer', function() {
    if (strpos($_SERVER['REQUEST_URI'], 'track-your-order') === false) return;
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        function updateHeaderClock() {
            const el = document.getElementById('headerClock'); if (!el) return;
            const now = new Date(); el.textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        }
        updateHeaderClock(); setInterval(updateHeaderClock, 1000);

        let orderNumber = null, pollInterval = null;

        function injectTrackerUI(num) {
            const existing = document.querySelector('.order-progress-container'); if (existing) existing.remove();
            const tracker = document.createElement('div');
            tracker.className = 'order-progress-container visible';
            tracker.innerHTML = `
                <div class="order-header">
                    <h3>Your Order</h3><div class="order-number">${num}</div>
                    <p style="margin:0;color:#ccc;font-size:0.95rem;">Estimated wait time: <span id="wait-time">10-15 minutes</span></p>
                </div>
                <div class="progress-timeline in-progress" id="progress-timeline" style="--progress-width:0%">
                    <div class="progress-step"><div class="progress-step-circle">✓</div><div class="progress-step-label">Order Confirmed</div></div>
                    <div class="progress-step"><div class="progress-step-circle">👨‍🍳</div><div class="progress-step-label">Preparing Food</div></div>
                    <div class="progress-step"><div class="progress-step-circle">🛍️</div><div class="progress-step-label">Ready for Pickup</div></div>
                </div>
                <div class="order-status-message">
                    <div class="status-text" id="status-display">Fetching your order status...</div>
                    <p style="margin:0.5rem 0 0;color:#ccc;font-size:0.9rem;">Last updated: <span id="last-update">just now</span></p>
                </div>
            `;
            const otpBlock = document.querySelector('.ewd-otp-full-display');
            if (otpBlock) {
                otpBlock.parentNode.insertBefore(tracker, otpBlock);
            } else {
                const content = document.querySelector('.entry-content, main');
                if (content) content.prepend(tracker);
            }
            if (!document.getElementById('cancelToast')) {
                const toast = document.createElement('div');
                toast.id = 'cancelToast';
                toast.className = 'cancel-toast';
                toast.innerHTML = '<div class="cancel-toast-icon">🚫</div><div class="cancel-toast-body"><div class="cancel-toast-title">Order Canceled</div><div class="cancel-toast-sub">This order has been canceled by the staff.</div></div>';
                document.body.appendChild(toast);
            }
        }

        let isTerminal = false;

        function playCanceledSound(){
            try {
                const ctx=new(window.AudioContext||window.webkitAudioContext)();
                const now=ctx.currentTime;
                [[523,0,0.15],[440,0.12,0.15],[349,0.24,0.15],[261,0.36,0.3]].forEach(([f,t,d])=>{
                    const o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);
                    o.type='triangle';o.frequency.value=f;
                    g.gain.setValueAtTime(0.3,now+t);
                    g.gain.exponentialRampToValueAtTime(0.001,now+t+d);
                    o.start(now+t);o.stop(now+t+d+0.05);
                });
                if(navigator.vibrate) navigator.vibrate([100,50,100,50,100,50,250]);
            } catch(e){}
        }

        function showCancelToast(msg){
            const t=document.getElementById('cancelToast');
            if(!t) return;
            const parts=msg.split('. ');
            t.querySelector('.cancel-toast-title').textContent=parts[0]||'Order Canceled';
            t.querySelector('.cancel-toast-sub').textContent=parts.slice(1).join('. ')||'This order has been canceled by the staff.';
            t.classList.add('show');
            setTimeout(()=>t.classList.remove('show'),6000);
        }

        function updateProgress(status) {
            let step = 0, pct = '0%', msg = 'Order received! We are putting your ticket into the queue.', wait = '12-15 minutes';
            
            if (status === 'Order Confirmed' || status.includes('Confirmed')) {
                step = 0;
                pct = '0%';
                msg = 'Order received! Your ticket is in the kitchen queue.';
                wait = '10-15 minutes';
            }
            else if (status.includes('Preparing Food') || status.includes('Preparing')) { 
                step = 1; 
                pct = '50%'; 
                msg = 'Your order is being smashed and prepared by our chefs right now!'; 
                wait = '5-8 minutes'; 
            }
            else if (status.includes('Ready for Pickup') || status.includes('Ready')) { 
                step = 2; 
                pct = '100%'; 
                msg = 'Your order is hot and ready! Please come up to the counter.'; 
                wait = 'Ready now'; 
            }
            else if (status.includes('Canceled') || status.includes('Sent Back') || status === 'NOT_FOUND') { 
                isTerminal = true;
                step = -1; 
                pct = '0%'; 
                msg = status === 'NOT_FOUND' 
                    ? 'This order could not be found. It may have been canceled.' 
                    : 'This order has been canceled. Please place a new order at the counter.'; 
                wait = 'Canceled'; 
            }

            const timeline = document.getElementById('progress-timeline');
            if (!timeline) return;
            document.querySelectorAll('.progress-step').forEach((el, i) => {
                const c = el.querySelector('.progress-step-circle');
                el.classList.remove('active', 'completed'); c.classList.remove('active', 'completed');
                if (i < step) { el.classList.add('completed'); c.classList.add('completed'); }
                else if (i === step) { el.classList.add('active'); c.classList.add('active'); }
            });
            timeline.style.setProperty('--progress-width', pct);
            if (isTerminal) { 
                timeline.classList.remove('in-progress'); timeline.classList.add('canceled'); 
                playCanceledSound();
                showCancelToast(msg);
            }
            if (document.getElementById('status-display')) document.getElementById('status-display').innerText = msg;
            if (document.getElementById('wait-time')) document.getElementById('wait-time').innerText = wait;
            if (document.getElementById('last-update')) document.getElementById('last-update').innerText = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        }

        async function poll() {
            if (!orderNumber || isTerminal) return;
            try {
                let r = await fetch(`<?php echo home_url(); ?>/?api_action=get_order_status&order_id=${orderNumber}&_t=` + Date.now(), { cache: 'no-store' });
                let j = await r.json();
                if (j.success && j.data.status) {
                    updateProgress(j.data.status);
                    if (isTerminal) { clearInterval(pollInterval); pollInterval = null; }
                } else {
                    isTerminal = true;
                    clearInterval(pollInterval); pollInterval = null;
                    const statusDisplay = document.getElementById('status-display');
                    if (statusDisplay) statusDisplay.innerText = "This order could not be found. It may have been canceled.";
                    playCanceledSound();
                    showCancelToast('This order could not be found. It may have been canceled.');
                }
            } catch (e) { console.error("Poll error:", e); }
        }

        function startTracking(num) { 
            orderNumber = num; 
            injectTrackerUI(num); 
            poll(); 
            clearInterval(pollInterval); 
            pollInterval = setInterval(poll, 1500);
        }

        // Catch values from DOM containers rendered dynamically
        const otpDisplay = document.querySelector('.ewd-otp-full-display');
        if (otpDisplay) { const match = otpDisplay.innerText.match(/\b(\d{2,6})\b/); if (match) orderNumber = match[1]; }
        
        if (!orderNumber) { 
            const urlParams = new URLSearchParams(window.location.search); 
            orderNumber = urlParams.get('otp_order_number') || urlParams.get('order_id'); 
        }
        
        if (orderNumber) startTracking(orderNumber);

        // Core Intercept Option: Isolate the plugin's native form elements completely
        function interceptManualInput() {
            const forms = document.querySelectorAll('.ewd-otp-tracking-form-div form');
            forms.forEach(form => {
                // Remove inline submit behaviors
                form.removeAttribute('action');
                form.removeAttribute('method');
                
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const input = form.querySelector('input[type="text"], input[type="number"]');
                    if (input && input.value.trim() !== "") { 
                        startTracking(input.value.trim()); 
                    }
                    return false;
                }, true);
            });

            // Target submit elements directly to guarantee click capture
            const submits = document.querySelectorAll('.ewd-otp-tracking-form-div input[type="submit"], .ewd-otp-tracking-form-div button[type="submit"]');
            submits.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const input = document.querySelector('.ewd-otp-tracking-form-div input[type="text"], .ewd-otp-tracking-form-div input[type="number"]');
                    if (input && input.value.trim() !== "") { 
                        startTracking(input.value.trim()); 
                    }
                    return false;
                }, true);
            });
        }

        // Run interception immediately and check back on slower rendering setups
        interceptManualInput();
        setTimeout(interceptManualInput, 1000);
    });
    </script>
    <?php
});

