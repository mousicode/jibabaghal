<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Points;
use JBG\Ads\Admin\PointsDiscountSettings;

class PointsShortcode {

    public static function register(): void {
        add_shortcode('jbg_points', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void {
        if (!is_user_logged_in()) return;
        wp_register_script('jbg-points-redeem', '', [], '1.0.0', true);
        $cfg = PointsDiscountSettings::get();
        wp_add_inline_script('jbg-points-redeem', '(function(){
            var btn = document.querySelector(".jbg-pts-redeem-btn");
            if(!btn) return;
            btn.addEventListener("click", function(e){
                e.preventDefault();
                btn.disabled = true;
                fetch("'. esc_url_raw(rest_url('jbg/v1/points/redeem')) .'", {
                  method:"POST",
                  headers: {"X-WP-Nonce":"'. esc_js(wp_create_nonce('wp_rest')) .'"},
                  credentials:"same-origin"
                }).then(r=>r.json()).then(function(d){
                    var box = document.querySelector(".jbg-pts-redeem-result");
                    if(!box) return;
                    if(d && d.ok){
                        box.innerHTML = "<div class=\"ok\">کد شما: <strong>"+d.code+"</strong> ("+d.percent+"%)</div>";
                        var total = document.querySelector(".jbg-points-total");
                        if(total && d.total!==undefined){ total.textContent = new Intl.NumberFormat().format(d.total); }
                        // prepend to list
                        var tbody = document.querySelector(".jbg-coupons tbody");
                        if(tbody){
                          var tr=document.createElement("tr");
                          tr.innerHTML = "<td>"+d.code+"</td><td>"+d.percent+"%</td><td>"+d.expiry+"</td>";
                          tbody.insertBefore(tr, tbody.firstChild);
                        }
                    } else {
                        box.innerHTML = "<div class=\"err\">"+(d && d.reason ? d.reason : "خطا")+"</div>";
                    }
                    btn.disabled = false;
                }).catch(function(){ btn.disabled=false; });
            });
        })();');
        wp_enqueue_script('jbg-points-redeem');
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) {
            return '<div class="jbg-points-card">'. esc_html__('Please log in to see your points.', 'jbg-ads') .'</div>';
        }

        $a = shortcode_atts([
            'limit' => 10,
            'title' => 'امتیازهای من',
        ], $atts, 'jbg_points');

        $uid   = get_current_user_id();
        $total = Points::total($uid);
        $log   = Points::log($uid);
        $log   = array_reverse($log);
        $log   = array_slice($log, 0, max(1,(int)$a['limit']));

        $cfg     = PointsDiscountSettings::get();
        $units   = intdiv(max(0,$total), max(1,$cfg['points_per_unit']));
        $can_pct = min($units * (int)$cfg['percent_per_unit'], (int)$cfg['max_percent']);
        $coupons = (array) get_user_meta($uid, 'jbg_coupons', true);

        ob_start();
        static $printed = false;
        if (!$printed) {
            $printed = true;
            echo '<style id="jbg-points-css">
                .jbg-points-wrap{direction:rtl;max-width:900px;margin:16px auto;padding:0 10px}
                .jbg-points-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:16px}
                .jbg-points-title{font-weight:800;margin:0 0 8px 0}
                .jbg-points-total{font-size:22px;font-weight:700}
                .jbg-pts-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
                .jbg-pts-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:9999px;padding:2px 10px;font-size:12px}
                .jbg-pts-redeem-btn{padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#111827;color:#fff;cursor:pointer}
                .jbg-pts-redeem-btn:disabled{opacity:.6;cursor:not-allowed}
                .jbg-pts-redeem-result .ok{color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:8px;border-radius:8px;margin-top:8px}
                .jbg-pts-redeem-result .err{color:#991b1b;background:#fef2f2;border:1px solid #fecaca;padding:8px;border-radius:8px;margin-top:8px}
                table.jbg-table{width:100%;border-collapse:collapse;margin-top:10px}
                table.jbg-table th,table.jbg-table td{border-bottom:1px solid #f1f5f9;padding:8px 6px;text-align:right}
            </style>';
        }

        echo '<div class="jbg-points-wrap">';

        // کارت مجموع
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html($a['title']) .'</div>';
        echo '      <div>'. esc_html__('Total Points:', 'jbg-ads') .' <span class="jbg-points-total">'. esc_html(number_format_i18n($total)) .'</span></div>';
        echo '  </div>';

        // کارت تبدیل به کد تخفیف
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html__('تبدیل امتیاز به کد تخفیف', 'jbg-ads') .'</div>';
        echo '      <div class="jbg-pts-row">';
        echo '          <span class="jbg-pts-badge">'. sprintf('هر %s امتیاز = %s%%', number_format_i18n($cfg['points_per_unit']), (int)$cfg['percent_per_unit']) .'</span>';
        echo '          <span class="jbg-pts-badge">'. sprintf('سقف هر کد: %s%%', (int)$cfg['max_percent']) .'</span>';
        echo '          <span class="jbg-pts-badge">'. sprintf('حداقل امتیاز: %s', number_format_i18n($cfg['min_points'])) .'</span>';
        echo '      </div>';
        if ($can_pct > 0 && $total >= $cfg['min_points']) {
            echo '  <p style="margin:10px 0">در حال حاضر می‌توانید تا <strong>'. esc_html($can_pct) .'%</strong> کد تخفیف دریافت کنید.</p>';
            echo '  <button type="button" class="jbg-pts-redeem-btn">'. esc_html__('دریافت کد تخفیف', 'jbg-ads') .'</button>';
            echo '  <div class="jbg-pts-redeem-result"></div>';
        } else {
            echo '  <p style="margin:10px 0">'. esc_html__('امتیاز شما برای تبدیل کافی نیست.', 'jbg-ads') .'</p>';
        }
        echo '  </div>';

        // کارت کدهای گرفته‌شده
        echo '  <div class="jbg-points-card jbg-coupons">';
        echo '      <div class="jbg-points-title">'. esc_html__('کدهای دریافت‌شده', 'jbg-ads') .'</div>';
        echo '      <table class="jbg-table"><thead><tr><th>کد</th><th>درصد</th><th>انقضا</th></tr></thead><tbody>';
        if (!empty($coupons)) {
            $coupons = array_reverse($coupons);
            foreach ($coupons as $c) {
                echo '<tr><td>'. esc_html($c['code']) .'</td><td>'. esc_html((int)$c['percent']) .'%</td><td>'. esc_html($c['expiry']) .'</td></tr>';
            }
        } else {
            echo '<tr><td colspan="3">'. esc_html__('هنوز کدی نگرفته‌اید.', 'jbg-ads') .'</td></tr>';
        }
        echo '      </tbody></table>';
        echo '  </div>';

        // کارت تاریخچه امتیاز
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html__('Recent awards', 'jbg-ads') .'</div>';
        echo '      <table class="jbg-table"><thead><tr><th>'. esc_html__('Date','jbg-ads').'</th><th>'. esc_html__('Points','jbg-ads').'</th><th>'. esc_html__('Video','jbg-ads').'</th></tr></thead><tbody>';
        foreach ($log as $row) {
            $pts = (int)($row['points'] ?? 0);
            $dt  = date_i18n('Y/m/d H:i', (int)($row['time'] ?? time()));
            $title = isset($row['post_id']) ? get_the_title((int)$row['post_id']) : '';
            $link  = $title ? get_permalink((int)$row['post_id']) : '';
            echo '<tr><td>'. esc_html($dt) .'</td><td>'. esc_html(number_format_i18n($pts)) .'</td><td>'. ($link?'<a href="'.esc_url($link).'">'.esc_html($title).'</a>':'-') .'</td></tr>';
        }
        echo '      </tbody></table>';
        echo '  </div>';

        echo '</div>';
        return (string) ob_get_clean();
    }
}
