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

    /** اسکریپت سبک برای دکمه «دریافت کد» */
    public static function enqueue(): void {
        if (!is_user_logged_in()) return;

        // فقط وقتی صفحه فعلی شورت‌کد را دارد اسکریپت را تزریق کن
        $post = get_post();
        if (!$post || stripos((string)$post->post_content, '[jbg_points') === false) return;

        wp_register_script('jbg-points-redeem', '', [], '1.1.0', true);
        $nonce = wp_create_nonce('wp_rest');
        $redeem_url = esc_url_raw(rest_url('jbg/v1/points/redeem'));

        $js = <<<JS
(function(){
  function on(e,t,cb){document.addEventListener(e,function(ev){var x=ev.target.closest(t); if(x) cb(ev,x);},true);}
  on('click','.jbg-pts-redeem-btn',function(ev,btn){
    ev.preventDefault();
    if(btn.disabled) return;
    btn.disabled = true;
    fetch("{$redeem_url}", {
      method: "POST",
      headers: {"X-WP-Nonce":"{$nonce}"},
      credentials: "same-origin"
    })
    .then(r=>r.json().catch(()=>({})))
    .then(function(d){
      var box = document.querySelector(".jbg-pts-redeem-result");
      if(!box) return;
      if(d && d.ok){
        var amt = (typeof d.amount==='number') ? d.amount : 0;
        box.innerHTML = '<div class="ok">کد شما: <strong>'+d.code+
                        '</strong> (مبلغ: <strong>'+ new Intl.NumberFormat().format(amt) +' تومان</strong>)</div>';
        var total = document.querySelector(".jbg-points-total");
        if(total && typeof d.total!=='undefined') total.textContent = new Intl.NumberFormat().format(d.total);
        var tbody = document.querySelector(".jbg-coupons tbody");
        if(tbody){
          var tr=document.createElement("tr");
          tr.innerHTML = '<td>'+d.code+'</td><td>'+ new Intl.NumberFormat().format(amt) +' تومان</td><td>'+d.expiry+'</td>';
          tbody.insertBefore(tr, tbody.firstChild);
        }
      } else {
        var msg = (d && d.reason) ? d.reason : 'خطا';
        box.innerHTML = '<div class="err">'+msg+'</div>';
      }
    })
    .catch(function(){})
    .finally(function(){ btn.disabled = false; });
  });
})();
JS;
        wp_add_inline_script('jbg-points-redeem', $js);
        wp_enqueue_script('jbg-points-redeem');
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) {
            return '<div class="jbg-points-card">'. esc_html__('Please log in to see your points.', 'jbg-ads') .'</div>';
        }

        $a = shortcode_atts([
            'limit' => 10,          // تعداد سطرهای تاریخچه
            'title' => 'امتیازهای من',
        ], $atts, 'jbg_points');

        $uid   = get_current_user_id();
        $total = Points::total($uid);
        $log   = Points::log($uid);
        $limit = max(1, (int)$a['limit']);
        $log   = array_slice(array_reverse($log), 0, $limit);

        // تنظیمات تبدیل امتیاز→مبلغ (اگر صفحه تنظیمات نصب شده باشد)
        $cfg = class_exists('\\JBG\\Ads\\Admin\\PointsDiscountSettings')
            ? PointsDiscountSettings::get()
            : [
                'points_per_unit' => 1000,     // هر ۱۰۰۰ امتیاز
                'toman_per_unit'  => 100000,   // = ۱۰۰٬۰۰۰ تومان
                'max_toman'       => 5000000,  // سقف هر کد
                'min_points'      => 1000,
                'expiry_days'     => 7,
            ];

        $ppu = max(1, (int)$cfg['points_per_unit']);
        $tpu = max(0, (int)$cfg['toman_per_unit']);
        $max = max(0, (int)$cfg['max_toman']);
        $min = max(0, (int)$cfg['min_points']);

        // محاسبه مقدار قابل تبدیل بر اساس مجموع امتیاز فعلی
        $units    = intdiv(max(0,$total), $ppu);
        $can_amt  = $units * $tpu;
        if ($max > 0) $can_amt = min($can_amt, $max);

        // خواندن لیست کدهای قبلی (ایمن در برابر دادهٔ غیرآرایه)
        $coupons_meta = get_user_meta($uid, 'jbg_coupons', true);
        $coupons = is_array($coupons_meta) ? $coupons_meta : [];

        ob_start();

        // استایل کوچک و مستقل
        static $printed = false;
        if (!$printed) {
            $printed = true;
            echo '<style id="jbg-points-css">
                .jbg-points-wrap{direction:rtl;max-width:900px;margin:16px auto;padding:0 10px}
                .jbg-points-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:16px}
                .jbg-points-title{font-weight:800;margin:0 0 8px 0}
                .jbg-points-total{font-size:22px;font-weight:700}
                .jbg-points-table{width:100%;border-collapse:collapse;margin-top:10px}
                .jbg-points-table th,.jbg-points-table td{border-bottom:1px solid #f1f5f9;padding:8px 6px;text-align:right}
                .jbg-pts-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:6px 0}
                .jbg-pts-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:9999px;padding:2px 10px;font-size:12px}
                .jbg-pts-redeem-btn{padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#111827;color:#fff;cursor:pointer}
                .jbg-pts-redeem-btn:disabled{opacity:.6;cursor:not-allowed}
                .jbg-pts-redeem-result .ok{color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:8px;border-radius:8px;margin-top:8px}
                .jbg-pts-redeem-result .err{color:#991b1b;background:#fef2f2;border:1px solid #fecaca;padding:8px;border-radius:8px;margin-top:8px}
            </style>';
        }

        echo '<div class="jbg-points-wrap">';

        // کارت مجموع امتیاز
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html($a['title']) .'</div>';
        echo '      <div>'. esc_html__('Total Points:', 'jbg-ads') .' <span class="jbg-points-total">'. esc_html(number_format_i18n($total)) .'</span></div>';
        echo '  </div>';

        // کارت تبدیل به کد تخفیف (مبتنی بر تومان)
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html__('تبدیل امتیاز به کد تخفیف', 'jbg-ads') .'</div>';
        echo '      <div class="jbg-pts-row">';
        echo '          <span class="jbg-pts-badge">'. sprintf('هر %s امتیاز = %s تومان', number_format_i18n($ppu), number_format_i18n($tpu)) .'</span>';
        if ($max > 0) {
            echo '      <span class="jbg-pts-badge">'. sprintf('سقف هر کد: %s تومان', number_format_i18n($max)) .'</span>';
        }
        echo '          <span class="jbg-pts-badge">'. sprintf('حداقل امتیاز: %s', number_format_i18n($min)) .'</span>';
        echo '      </div>';

        if ($units > 0 && $total >= $min && $tpu > 0) {
            echo '  <p style="margin:10px 0">در حال حاضر می‌توانید کدی به ارزش <strong>'. esc_html(number_format_i18n($can_amt)) .' تومان</strong> دریافت کنید.</p>';
            echo '  <button type="button" class="jbg-pts-redeem-btn">'. esc_html__('دریافت کد تخفیف', 'jbg-ads') .'</button>';
            echo '  <div class="jbg-pts-redeem-result"></div>';
        } else {
            echo '  <p style="margin:10px 0">'. esc_html__('امتیاز شما برای تبدیل کافی نیست.', 'jbg-ads') .'</p>';
        }
        echo '  </div>';

        // کارت «کدهای دریافت‌شده»
        echo '  <div class="jbg-points-card jbg-coupons">';
        echo '      <div class="jbg-points-title">'. esc_html__('کدهای دریافت‌شده', 'jbg-ads') .'</div>';
        echo '      <table class="jbg-points-table"><thead><tr><th>کد</th><th>مبلغ</th><th>انقضا</th></tr></thead><tbody>';

        if (!empty($coupons)) {
            // فقط آیتم‌های آرایه‌ای را رندر کن
            $rows = array_values(array_filter($coupons, 'is_array'));
            if (empty($rows)) {
                echo '<tr><td colspan="3">'. esc_html__('کدی ثبت نشده است.', 'jbg-ads') .'</td></tr>';
            } else {
                // جدیدها اول
                $rows = array_reverse($rows);
                foreach ($rows as $c) {
                    $code   = isset($c['code'])   ? (string)$c['code']   : '';
                    $amount = isset($c['amount']) ? (int)$c['amount']    : 0;
                    $expiry = isset($c['expiry']) ? (string)$c['expiry'] : '';
                    if ($code === '' && $amount === 0 && $expiry === '') continue;

                    echo '<tr>'
                       . '<td>'. esc_html($code) .'</td>'
                       . '<td>'. esc_html(number_format_i18n($amount)) .' تومان</td>'
                       . '<td>'. esc_html($expiry) .'</td>'
                       . '</tr>';
                }
            }
        } else {
            echo '<tr><td colspan="3">'. esc_html__('هنوز کدی نگرفته‌اید.', 'jbg-ads') .'</td></tr>';
        }

        echo '      </tbody></table>';
        echo '  </div>';

        // کارت تاریخچه امتیاز
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html__('Recent awards', 'jbg-ads') .'</div>';

        if (empty($log)) {
            echo '<div>'. esc_html__('No points yet.', 'jbg-ads') .'</div>';
        } else {
            echo '<table class="jbg-points-table"><thead><tr>';
            echo '<th>'. esc_html__('Video', 'jbg-ads') .'</th>';
            echo '<th>'. esc_html__('Points', 'jbg-ads') .'</th>';
            echo '<th>'. esc_html__('Date', 'jbg-ads') .'</th>';
            echo '</tr></thead><tbody>';
            foreach ($log as $row) {
                $title = isset($row['title']) ? (string)$row['title'] : '';
                $pid   = isset($row['ad_id']) ? (int)$row['ad_id'] : 0;
                $pts   = isset($row['points']) ? (int)$row['points'] : 0;
                $time  = isset($row['time']) ? (int)$row['time'] : time();

                $link  = $pid ? get_permalink($pid) : '';
                $tlink = $link ? '<a href="'.esc_url($link).'" target="_blank" rel="noopener">'.esc_html($title).'</a>' : esc_html($title);

                echo '<tr>';
                echo '<td>'. $tlink .'</td>';
                echo '<td><span class="jbg-points-badge">'. esc_html(number_format_i18n($pts)) .'</span></td>';
                echo '<td>'. esc_html( date_i18n(get_option('date_format').' '.get_option('time_format'), $time) ) .'</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '  </div>'; // کارت تاریخچه
        echo '</div>';   // wrap

        return (string) ob_get_clean();
    }
}
