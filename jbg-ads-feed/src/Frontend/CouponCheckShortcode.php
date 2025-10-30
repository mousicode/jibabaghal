<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class CouponCheckShortcode {
    public static function register(): void {
        add_shortcode('jbg_coupon_check', [self::class, 'render']);
    }

    public static function render(): string {
        if (!is_user_logged_in()) return '<p>برای استعلام وارد شوید.</p>';
        ob_start(); ?>
        <style>
            .jbg-coupon-check{display:flex;flex-direction:column;gap:10px;max-width:350px;margin:20px 0}
            .jbg-coupon-check input{padding:8px 10px;border:1px solid #ccc;border-radius:8px}
            .jbg-coupon-check button{background:#111827;color:#fff;border:none;padding:10px 16px;border-radius:9999px;cursor:pointer;transition:background .2s}
            .jbg-coupon-check button:hover{background:#1f2937}
            #jbg_coupon_result{margin-top:10px;font-weight:600}
            #jbg_coupon_result .ok{color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:8px;border-radius:8px}
            #jbg_coupon_result .err{color:#991b1b;background:#fef2f2;border:1px solid #fecaca;padding:8px;border-radius:8px}
        </style>
        <div class="jbg-coupon-check">
            <label>کد تخفیف:</label>
            <input type="text" id="jbg_coupon_code" placeholder="مثلاً JBG-123ABC">
            <button id="jbg_coupon_btn">استعلام</button>
            <div id="jbg_coupon_result"></div>
        </div>
        <script>
        (function(){
          const btn  = document.getElementById('jbg_coupon_btn');
          const code = document.getElementById('jbg_coupon_code');
          const box  = document.getElementById('jbg_coupon_result');
          function fmt(s){return (s||'').trim().toLowerCase();}
          btn.onclick = async function(){
            const c = fmt(code.value);
            if(!c){alert('کد را وارد کنید'); return;}
            try{
              const res = await fetch(`/wp-json/jbg/v1/coupon/check?code=${encodeURIComponent(c)}`);
              const d = await res.json();
              if(d.ok){
                box.innerHTML = `<div class="ok">✅ معتبر است. مبلغ: ${d.amount} تومان${d.expiry ? `، انقضا: ${d.expiry}` : ''}${d.used_up ? ' (استفاده‌شده)' : ''}</div>`;
              }else{
                let reason = d.reason || (d.expired ? 'expired' : 'invalid');
                if(d.used_up) reason = 'used';
                const map = {not_found:'کد یافت نشد', expired:'کد منقضی شده', used:'سقف استفاده تمام شده', invalid:'نامعتبر'};
                box.innerHTML = `<div class="err">❌ ${map[reason]||'نامعتبر یا منقضی‌شده'}</div>`;
              }
            }catch(e){
              box.innerHTML = `<div class="err">❌ خطای شبکه</div>`;
            }
          };
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
