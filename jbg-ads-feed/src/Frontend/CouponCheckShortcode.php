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
            .jbg-coupon-check {
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 350px;
                margin: 20px 0;
            }
            .jbg-coupon-check input {
                padding: 8px 10px;
                border: 1px solid #ccc;
                border-radius: 8px;
            }
            .jbg-coupon-check button {
                background: #111827;
                color: #fff;
                border: none;
                padding: 8px 14px;
                border-radius: 9999px; /* دکمه کاملاً گرد */
                cursor: pointer;
                transition: background 0.2s;
            }
            .jbg-coupon-check button:hover {
                background: #1f2937;
            }
            #jbg_coupon_result {
                margin-top: 10px;
                font-weight: 600;
            }
        </style>
        <div class="jbg-coupon-check">
            <label>کد تخفیف:</label>
            <input type="text" id="jbg_coupon_code" placeholder="مثلاً JBG-123ABC">
            <button id="jbg_coupon_btn">استعلام</button>
            <div id="jbg_coupon_result"></div>
        </div>
        <script>
        document.getElementById('jbg_coupon_btn').onclick = async function(){
            const code = document.getElementById('jbg_coupon_code').value.trim();
            if(!code){alert('کد را وارد کنید');return;}
            const res = await fetch(`/wp-json/jbg/v1/coupon/check?code=${encodeURIComponent(code)}`);
            const d = await res.json();
            const box = document.getElementById('jbg_coupon_result');
            if(d.ok) box.innerHTML = `<p style="color:green">✅ معتبر است (مبلغ: ${d.amount} تومان، انقضا: ${d.expiry||'-'})</p>`;
            else box.innerHTML = `<p style="color:red">❌ نامعتبر یا منقضی‌شده</p>`;
        };
        </script>
        <?php
        return ob_get_clean();
    }
}
