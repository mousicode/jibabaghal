<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Wallet\Wallet;

class WalletShortcode
{
    public static function register(): void {
        add_shortcode('jbg_wallet', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void {
        if (!is_user_logged_in()) return;
        $post = get_post();
        if (!$post || stripos((string)$post->post_content, '[jbg_wallet') === false) return;

        wp_register_script('jbg-wallet', '', [], '1.1.0', true);
        $nonce = wp_create_nonce('wp_rest');
        $url_initiate = esc_url_raw(rest_url('jbg/v1/wallet/initiate'));
        $js = <<<JS
(function(){
  function on(e,t,cb){document.addEventListener(e,function(ev){var x=ev.target.closest(t); if(x) cb(ev,x);},true);}
  function nf(n){try{return new Intl.NumberFormat().format(n);}catch(e){return n;}}
  function toast(txt,type){
    var t=document.createElement('div');
    t.className='jbg-toast '+(type||'');
    t.textContent=txt;
    document.body.appendChild(t);
    setTimeout(function(){t.classList.add('show');},10);
    setTimeout(function(){t.classList.remove('show'); setTimeout(function(){t.remove();},200);},3000);
  }

  on('submit','.jbg-wallet-topup-form',function(ev,form){
    ev.preventDefault();
    var btn=form.querySelector('button[type="submit"]');
    var spn=form.querySelector('.spin');
    var amt = parseInt(form.querySelector('[name="amount"]').value||'0',10);
    var brand = parseInt(form.querySelector('[name="brand_id"]').value||'0',10);
    if(amt<=0 || brand<=0){ toast('مبلغ و برند را وارد کنید.','err'); return; }
    btn.disabled = true; if(spn) spn.style.display='inline-block';
    fetch("$url_initiate", {
      method: "POST",
      headers: {"X-WP-Nonce":"$nonce","Content-Type":"application/json"},
      credentials: "same-origin",
      body: JSON.stringify({amount:amt, brand_id:brand})
    }).then(r=>r.json()).then(function(d){
      if(d && d.ok && d.redirect){
        window.location.href = d.redirect;
      } else {
        toast((d && d.message) ? d.message : 'خطا در شروع پرداخت','err');
      }
    }).catch(function(){ toast('خطای شبکه','err'); })
    .finally(function(){ btn.disabled = false; if(spn) spn.style.display='none'; });
  });

  // کپی موجودی/مقادیر (در صورت نیاز بعدا)
})();
JS;
        wp_add_inline_script('jbg-wallet', $js);

        // استایل مشترک زیبا و سبک
        $css = <<<CSS
.jbg-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:12px 0}
.jbg-title{font-weight:800;margin:0 0 10px 0}
.jbg-table{width:100%;border-collapse:collapse}
.jbg-table th,.jbg-table td{padding:10px;border-bottom:1px solid #f3f4f6;text-align:right}
.jbg-table th{background:#f9fafb;font-weight:700}
.jbg-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:9999px;padding:2px 10px;font-size:12px}
.jbg-wallet form{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.jbg-wallet input, .jbg-wallet select{padding:8px;border:1px solid #d1d5db;border-radius:8px}
.jbg-wallet button{padding:8px 12px;border-radius:8px;border:1px solid #111827;background:#111827;color:#fff;cursor:pointer}
.jbg-wallet .muted{color:#6b7280}
.toman::after{content:" تومان"}
.jbg-toast{position:fixed;right:16px;bottom:16px;opacity:0;transform:translateY(10px);transition:all .2s ease;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;z-index:9999}
.jbg-toast.err{background:#991b1b}
.jbg-toast.show{opacity:1;transform:translateY(0)}
.spin{display:none;width:16px;height:16px;border:2px solid #e5e7eb;border-top-color:#111827;border-radius:50%;animation:jbgspin .9s linear infinite}
@keyframes jbgspin{to{transform:rotate(360deg)}}
@media (max-width:640px){.jbg-table th,.jbg-table td{padding:8px}}
CSS;
        wp_register_style('jbg-wallet-css', false, [], '1.1.0');
        wp_add_inline_style('jbg-wallet-css', $css);
        wp_enqueue_style('jbg-wallet-css');
        wp_enqueue_script('jbg-wallet');
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return '<div class="jbg-card">برای مشاهده کیف‌پول وارد شوید.</div>';
        if (!current_user_can('jbg_view_reports')) return '<div class="jbg-card">شما اسپانسر نیستید یا دسترسی ندارید.</div>';

        $uid = get_current_user_id();
        $brand_ids = get_user_meta($uid, 'jbg_sponsor_brand_ids', true);
        if (!is_array($brand_ids) || empty($brand_ids)) return '<div class="jbg-card">برندی به شما اختصاص داده نشده است.</div>';

        $balances = [];
        foreach ($brand_ids as $bid) {
            $bid = (int) $bid;
            $term = get_term($bid);
            $balances[] = [
                'brand_id' => $bid,
                'brand'    => ($term && !is_wp_error($term)) ? $term->name : ('#'.$bid),
                'balance'  => Wallet::get_balance($uid, $bid),
            ];
        }

        // تراکنش‌های اخیر (فقط نمایش)
        $log = get_user_meta($uid, 'jbg_wallet_txn', true);
        $log = is_array($log) ? array_slice(array_reverse($log), 0, 10) : [];

        ob_start();
        echo '<div class="jbg-wallet" style="direction:rtl">';
        // کارت موجودی‌ها
        echo '<div class="jbg-card"><div class="jbg-title">موجودی برندها</div>';
        echo '<table class="jbg-table"><thead><tr><th>برند</th><th>موجودی</th></tr></thead><tbody>';
        foreach ($balances as $row) {
            echo '<tr><td>'.esc_html($row['brand']).'</td><td class="toman">'.esc_html(number_format_i18n($row['balance'])).'</td></tr>';
        }
        echo '</tbody></table></div>';

        // کارت شارژ
        echo '<div class="jbg-card"><div class="jbg-title">شارژ کیف‌پول</div>';
        echo '<form class="jbg-wallet-topup-form">';
        echo '<label>مبلغ شارژ (تومان) <input type="number" name="amount" min="1000" step="1000" required></label>';
        echo '<label>برند <select name="brand_id">';
        foreach ($balances as $row) {
            echo '<option value="'.(int)$row['brand_id'].'">'.esc_html($row['brand']).'</option>';
        }
        echo '</select></label>';
        echo '<button type="submit">پرداخت و شارژ <span class="spin" aria-hidden="true"></span></button>';
        echo '</form>';
        echo '<p class="muted" style="margin-top:6px">پس از پرداخت، موجودی برند انتخاب‌شده به اندازه مبلغ پرداخت افزایش می‌یابد.</p>';
        echo '</div>';

        // کارت تراکنش‌های اخیر
        echo '<div class="jbg-card"><div class="jbg-title">تراکنش‌های اخیر</div>';
        if (empty($log)) {
            echo '<div class="muted">هنوز تراکنشی ندارید.</div>';
        } else {
            echo '<table class="jbg-table"><thead><tr><th>تاریخ</th><th>برند</th><th>مبلغ</th><th>علت</th></tr></thead><tbody>';
            foreach ($log as $tx) {
                $tbrand = get_term((int)($tx['brand_id'] ?? 0));
                $bname  = ($tbrand && !is_wp_error($tbrand)) ? $tbrand->name : ('#'.(int)($tx['brand_id'] ?? 0));
                $amt    = (int) ($tx['amount'] ?? 0);
                $reason = (string) ($tx['reason'] ?? '');
                $ts     = (int) ($tx['time'] ?? time());
                echo '<tr>';
                echo '<td>'.esc_html( date_i18n(get_option('date_format').' '.get_option('time_format'), $ts) ).'</td>';
                echo '<td>'.esc_html($bname).'</td>';
                echo '<td class="'.($amt<0?'':'toman').'">'.esc_html(($amt<0?'-':'').number_format_i18n(abs($amt))).($amt<0?' تومان':'').'</td>';
                echo '<td><span class="jbg-badge">'.esc_html($reason).'</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '</div>';
        return (string) ob_get_clean();
    }
}
