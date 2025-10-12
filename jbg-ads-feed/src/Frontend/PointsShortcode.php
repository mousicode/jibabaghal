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
        $post = get_post();
        if (!$post || stripos((string)$post->post_content, '[jbg_points') === false) return;

        wp_register_script('jbg-points-redeem', '', [], '1.2.1', true);
        $nonce = wp_create_nonce('wp_rest');
        $redeem_url = esc_url_raw(rest_url('jbg/v1/points/redeem'));

        $js = <<<JS
(function(){
  function on(e,t,cb){document.addEventListener(e,function(ev){var x=ev.target.closest(t); if(x) cb(ev,x);},true);}
  function nf(n){try{return new Intl.NumberFormat().format(n);}catch(e){return n;}}
  function toast(txt,type){
    var t=document.createElement('div'); t.className='jbg-toast '+(type||''); t.textContent=txt;
    document.body.appendChild(t); setTimeout(function(){t.classList.add('show');},10);
    setTimeout(function(){t.classList.remove('show'); setTimeout(function(){t.remove();},200);},3000);
  }

  on('click','.jbg-pts-redeem-btn',function(ev,btn){
    ev.preventDefault(); if(btn.disabled) return;
    btn.disabled = true; btn.classList.add('loading');
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
        box.innerHTML = '<div class="ok">کد شما: <strong class="code">'+d.code+
                        '</strong> (مبلغ: <strong>'+ nf(amt) +' تومان</strong>) '+
                        '<button type="button" class="copy">کپی</button></div>';
        var total = document.querySelector(".jbg-points-total");
        if(total && typeof d.total!=='undefined') total.textContent = nf(d.total);
        var tbody = document.querySelector(".jbg-coupons tbody");
        if(tbody){
          var tr=document.createElement("tr");
          tr.innerHTML = '<td>'+d.code+'</td><td>'+ nf(amt) +' تومان</td><td>'+d.expiry+'</td>';
          tbody.insertBefore(tr, tbody.firstChild);
        }
        toast('کد تخفیف ساخته شد','');
      } else {
        var msg = (d && d.reason) ? d.reason : 'خطا';
        box.innerHTML = '<div class="err">'+msg+'</div>';
        toast('خطا: '+msg,'err');
      }
    })
    .catch(function(){ toast('خطای شبکه','err'); })
    .finally(function(){ btn.disabled = false; btn.classList.remove('loading'); });
  });

  on('click','.jbg-pts-redeem-result .copy',function(ev,btn){
    var code = btn.parentElement.querySelector('.code')?.textContent || '';
    if(!code) return;
    navigator.clipboard.writeText(code).then(function(){ btn.textContent='کپی شد'; setTimeout(function(){btn.textContent='کپی';},1500); });
  });
})();
JS;
        wp_add_inline_script('jbg-points-redeem', $js);

        $css = <<<CSS
/* کارت‌های مشترک */
.jbg-points-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:12px 0}
.jbg-points-title{font-weight:800;margin:0 0 10px 0}
.jbg-points-table{width:100%;border-collapse:collapse}
.jbg-points-table th,.jbg-points-table td{border-bottom:1px solid #f1f5f9;padding:10px;text-align:right}
.jbg-pts-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:6px 0}
.jbg-pts-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:9999px;padding:2px 10px;font-size:12px}
.jbg-pts-redeem-btn{padding:8px 12px;border-radius:8px;border:1px solid #111827;background:#111827;color:#fff;cursor:pointer}
.jbg-pts-redeem-btn.loading{opacity:.7}
.jbg-pts-redeem-result .ok{color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:8px;border-radius:8px;margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.jbg-pts-redeem-result .ok .copy{padding:4px 8px;border:1px solid #10b981;background:#10b981;color:#fff;border-radius:6px;cursor:pointer}
.jbg-pts-redeem-result .err{color:#991b1b;background:#fef2f2;border:1px solid #fecaca;padding:8px;border-radius:8px;margin-top:8px}
.jbg-toast{position:fixed;right:16px;bottom:16px;opacity:0;transform:translateY(10px);transition:all .2s ease;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;z-index:9999}
.jbg-toast.err{background:#991b1b}
.jbg-toast.show{opacity:1;transform:translateY(0)}
CSS;
        wp_register_style('jbg-points-css', false, [], '1.2.1');
        wp_add_inline_style('jbg-points-css', $css);
        wp_enqueue_style('jbg-points-css');
        wp_enqueue_script('jbg-points-redeem');
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) {
            // حتی در حالت مهمان هم رَپر را نگذاریم تا فقط وقتی شورت‌کد رندر کامل دارد، عرض تغییر کند.
            return '<div class="jbg-points-card">برای مشاهدهٔ امتیازها وارد شوید.</div>';
        }

        $a = shortcode_atts(['limit'=>10,'title'=>'امتیازهای من'], $atts, 'jbg_points');

        $uid   = get_current_user_id();
        $total = Points::total($uid);
        $log   = Points::log($uid);
        $limit = max(1, (int)$a['limit']);
        $log   = array_slice(array_reverse($log), 0, $limit);

        $cfg = class_exists('\\JBG\\Ads\\Admin\\PointsDiscountSettings')
            ? PointsDiscountSettings::get()
            : ['points_per_unit'=>1000,'toman_per_unit'=>100000,'min_points'=>1000,'expiry_days'=>7];

        $ppu = max(1,   (int)($cfg['points_per_unit'] ?? 1000));
        $tpu = max(0,   (int)($cfg['toman_per_unit']  ?? 100000));
        $min = max(0,   (int)($cfg['min_points']      ?? 1000));

        $units   = intdiv(max(0,$total), $ppu);
        $can_amt = $units * $tpu;

        ob_start();

        // --- CSS و رَپر full-bleed/کانتینر فقط یک‌بار در صفحه ---
        static $fw_once = false;
        if (!$fw_once) {
            $fw_once = true;
            echo '<style id="jbg-points-fw">
              .jbg-full-bleed{position:relative;left:50%;right:50%;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);width:100vw}
              .jbg-container{max-width:1312px;margin:0 auto;padding-left:16px;padding-right:16px;box-sizing:border-box}
            </style>';
        }
        echo '<div class="jbg-full-bleed"><div class="jbg-container">';

        echo '<div class="jbg-points-wrap" style="direction:rtl">';
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html($a['title']) .'</div>';
        echo '      <div>مجموع امتیاز: <span class="jbg-points-total">'. esc_html(number_format_i18n($total)) .'</span></div>';
        echo '  </div>';

        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">تبدیل امتیاز به کد تخفیف</div>';
        echo '      <div class="jbg-pts-row">';
        echo '          <span class="jbg-pts-badge">'. sprintf('هر %s امتیاز = %s تومان', number_format_i18n($ppu), number_format_i18n($tpu)) .'</span>';
        echo '          <span class="jbg-pts-badge">'. sprintf('حداقل امتیاز: %s', number_format_i18n($min)) .'</span>';
        echo '      </div>';

        if ($units > 0 && $total >= $min && $tpu > 0) {
            echo '  <p style="margin:10px 0">می‌توانید کدی به ارزش <strong>'. esc_html(number_format_i18n($can_amt)) .' تومان</strong> دریافت کنید.</p>';
            echo '  <button type="button" class="jbg-pts-redeem-btn">دریافت کد تخفیف</button>';
            echo '  <div class="jbg-pts-redeem-result"></div>';
        } else {
            echo '  <p style="margin:10px 0">امتیاز شما برای تبدیل کافی نیست.</p>';
        }
        echo '  </div>';

        // کدهای دریافت‌شده
        $coupons_meta = get_user_meta($uid, 'jbg_coupons', true);
        $coupons = is_array($coupons_meta) ? array_reverse(array_values(array_filter($coupons_meta,'is_array'))) : [];

        echo '  <div class="jbg-points-card jbg-coupons">';
        echo '      <div class="jbg-points-title">کدهای دریافت‌شده</div>';
        echo '      <table class="jbg-points-table"><thead><tr><th>کد</th><th>مبلغ</th><th>انقضا</th></tr></thead><tbody>';
        if (empty($coupons)) {
            echo '<tr><td colspan="3">هنوز کدی نگرفته‌اید.</td></tr>';
        } else {
            foreach ($coupons as $c) {
                $code   = isset($c['code'])   ? (string)$c['code']   : '';
                $amount = isset($c['amount']) ? (int)$c['amount']    : 0;
                $expiry = isset($c['expiry']) ? (string)$c['expiry'] : '';
                echo '<tr><td>'.esc_html($code).'</td><td>'.esc_html(number_format_i18n($amount)).' تومان</td><td>'.esc_html($expiry).'</td></tr>';
            }
        }
        echo '      </tbody></table>';
        echo '  </div>';

        // تاریخچه امتیاز
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">آخرین امتیازها</div>';
        if (empty($log)) {
            echo '<div>هنوز امتیازی ندارید.</div>';
        } else {
            echo '<table class="jbg-points-table"><thead><tr><th>ویدیو</th><th>امتیاز</th><th>تاریخ</th></tr></thead><tbody>';
            foreach ($log as $row) {
                $title = isset($row['title']) ? (string)$row['title'] : '';
                $pid   = isset($row['ad_id']) ? (int)$row['ad_id'] : 0;
                $pts   = isset($row['points']) ? (int)$row['points'] : 0;
                $time  = isset($row['time']) ? (int)$row['time'] : time();
                $link  = $pid ? get_permalink($pid) : '';
                $tlink = $link ? '<a href="'.esc_url($link).'" target="_blank" rel="noopener">'.esc_html($title).'</a>' : esc_html($title);
                echo '<tr><td>'.$tlink.'</td><td><span class="jbg-pts-badge">'.esc_html(number_format_i18n($pts)).'</span></td><td>'. esc_html( date_i18n(get_option('date_format').' '.get_option('time_format'), $time) ).'</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '  </div>';

        echo '</div>'; // .jbg-points-wrap

        // بستن رَپر کانتینر/فول-بِلید
        echo '</div></div>'; // .jbg-container .jbg-full-bleed

        return (string) ob_get_clean();
    }
}
