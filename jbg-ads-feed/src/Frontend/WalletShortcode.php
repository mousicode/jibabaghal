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

        wp_register_script('jbg-wallet', '', [], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        $url_initiate = esc_url_raw(rest_url('jbg/v1/wallet/initiate'));
        $js = <<<JS
(function(){
  function on(e,t,cb){document.addEventListener(e,function(ev){var x=ev.target.closest(t); if(x) cb(ev,x);},true);}
  on('submit','.jbg-wallet-topup-form',function(ev,form){
    ev.preventDefault();
    var amt = parseInt(form.querySelector('[name="amount"]').value||'0',10);
    var brand = parseInt(form.querySelector('[name="brand_id"]').value||'0',10);
    var box = form.querySelector('.jbg-wallet-msg');
    if(amt<=0 || brand<=0){ box.textContent='مبلغ و برند را وارد کنید.'; return; }
    form.querySelector('button[type="submit"]').disabled = true;
    fetch("$url_initiate", {
      method: "POST",
      headers: {"X-WP-Nonce":"$nonce","Content-Type":"application/json"},
      credentials: "same-origin",
      body: JSON.stringify({amount:amt, brand_id:brand})
    }).then(r=>r.json()).then(function(d){
      if(d && d.ok && d.redirect){
        window.location.href = d.redirect; // رفتن به درگاه (از طریق هوک یا افزونه‌ی شما تامین می‌شود)
      } else {
        box.textContent = (d && d.message) ? d.message : 'خطا در شروع پرداخت';
      }
    }).catch(function(){ box.textContent='خطای شبکه'; })
    .finally(function(){ form.querySelector('button[type="submit"]').disabled = false; });
  });
})();
JS;
        wp_add_inline_script('jbg-wallet', $js);
        wp_enqueue_script('jbg-wallet');
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return '<div>برای مشاهده کیف‌پول وارد شوید.</div>';
        if (!current_user_can('jbg_view_reports')) return '<div>شما اسپانسر نیستید یا دسترسی ندارید.</div>';

        $uid = get_current_user_id();
        $brand_ids = get_user_meta($uid, 'jbg_sponsor_brand_ids', true);
        if (!is_array($brand_ids) || empty($brand_ids)) return '<div>برندی به شما اختصاص داده نشده است.</div>';

        $balances = [];
        foreach ($brand_ids as $bid) {
            $bid = (int) $bid;
            $balances[] = [
                'brand_id' => $bid,
                'brand'    => get_term($bid)->name ?? ('#'.$bid),
                'balance'  => Wallet::get_balance($uid, $bid),
            ];
        }

        ob_start();
        echo '<style>
          .jbg-wallet{direction:rtl}
          .jbg-wallet table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
          .jbg-wallet th,.jbg-wallet td{padding:10px;border-bottom:1px solid #f3f4f6;text-align:right}
          .jbg-wallet th{background:#f9fafb;font-weight:700}
          .jbg-wallet .toman::after{content:" تومان"}
          .jbg-wallet form{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
          .jbg-wallet input, .jbg-wallet select{padding:8px;border:1px solid #d1d5db;border-radius:8px}
          .jbg-wallet button{padding:8px 12px;border-radius:8px;border:1px solid #111827;background:#111827;color:#fff;cursor:pointer}
          .jbg-wallet .jbg-wallet-msg{margin-top:8px;color:#374151}
        </style>';

        echo '<div class="jbg-wallet">';
        echo '<table><thead><tr><th>برند</th><th>موجودی</th></tr></thead><tbody>';
        foreach ($balances as $row) {
            echo '<tr><td>'.esc_html($row['brand']).'</td><td class="toman">'.esc_html(number_format_i18n($row['balance'])).'</td></tr>';
        }
        echo '</tbody></table>';

        // فرم شارژ
        echo '<form class="jbg-wallet-topup-form">';
        echo '<label>مبلغ شارژ (تومان) <input type="number" name="amount" min="1000" step="1000" required></label>';
        echo '<label>برند ';
        echo '<select name="brand_id">';
        foreach ($balances as $row) {
            echo '<option value="'.(int)$row['brand_id'].'">'.esc_html($row['brand']).'</option>';
        }
        echo '</select></label>';
        echo '<button type="submit">پرداخت و شارژ</button>';
        echo '<div class="jbg-wallet-msg"></div>';
        echo '</form>';

        echo '</div>';
        return (string) ob_get_clean();
    }
}
