<?php
namespace JBG\Ads\Onboarding;
if (!defined('ABSPATH')) exit;

/**
 * ثبت‌نام جداگانه برای «اسپانسرها/برندها» بدون تغییر در ثبت‌نام معمولی (Digits).
 * فلو:
 *  1) فرم سفارشی (شورتکد [jbg_sponsor_register]) اطلاعات تکمیلی را می‌گیرد.
 *  2) پس از ارسال، داده‌ها موقتاً در transient ذخیره می‌شود و کاربر به صفحه‌ی لاگین/ثبت‌نام Digits
 *     هدایت می‌گردد (OTP).
 *  3) Digits پس از احراز هویت، کاربر را به همان صفحه با token برمی‌گرداند؛
 *     ما دیتا را به user_meta ذخیره کرده و نقش «jbg_sponsor» ست می‌کنیم.
 *
 * نکته: ثبت‌نام/ورود معمولی سایت تغییری نمی‌کند؛ این مسیر فقط مخصوص صفحه‌ای است
 * که شورتکد روی آن قرار می‌گیرد.
 */
class SponsorRegister {

    /** آدرس صفحه‌ی لاگین/ثبت‌نام Digits (در صورت نیاز تنظیم کن) */
    private const DIGITS_LOGIN_URL = '/login/'; // ← اگر URL دیگری داری، همین‌جا عوض کن

    /** کلید ترنزینت‌ها */
    private const TRANSIENT_PREFIX = 'jbg_sponsor_reg_';

    /** نقش اختصاصی اسپانسر */
    private const ROLE = 'jbg_sponsor';

    /** رجیستر هوک‌ها و شورتکد */
    public static function register(): void {
        add_action('init', [self::class, 'maybe_add_role']);
        add_shortcode('jbg_sponsor_register', [self::class, 'shortcode']);
    }

    /** در صورت نبود، نقش اسپانسر را ایجاد می‌کنیم (بر پایهٔ مشترک) */
    public static function maybe_add_role(): void {
        if (!get_role(self::ROLE)) {
            $subscriber = get_role('subscriber');
            $caps = $subscriber ? $subscriber->capabilities : [];
            add_role(self::ROLE, 'اسپانسر/برند', $caps);
        }
    }

    /** هندل شورتکد: نمایش فرم / پردازش نهایی پس از لاگین / هندل ارسال فرم */
    public static function shortcode(): string {
        // اگر پس از لاگین برگشته و token داریم، داده‌ها را به یوزر لاگین‌شده نسبت بده
        if (is_user_logged_in() && isset($_GET['jbg_sponsor_token'])) {
            return self::complete_for_logged_user(sanitize_text_field($_GET['jbg_sponsor_token']));
        }

        // ارسال فرم (بدون استفاده از header redirect تا خطای "headers already sent" نگیریم)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jbg_sponsor_submit'])) {
            return self::handle_submit();
        }

        // نمایش فرم در حالت اولیه
        return self::render_form();
    }

    /**
     * ذخیره‌ی داده‌ها در transient و تولید اسکریپت جاوااسکریپت برای هدایت کاربر
     * به صفحه لاگین Digits (بدون تغییر header های PHP).
     */
    private static function handle_submit(): string {
        // امنیت پایه: نانس
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'jbg_sponsor_reg')) {
            return self::msg('درخواست نامعتبر است. صفحه را مجدداً بارگذاری کنید.', 'error') . self::render_form($_POST);
        }

        // ورودی‌ها
        $full_name  = isset($_POST['full_name'])  ? sanitize_text_field($_POST['full_name'])  : '';
        $national   = isset($_POST['national_id'])? sanitize_text_field($_POST['national_id']): '';
        $brand_name = isset($_POST['brand_name']) ? sanitize_text_field($_POST['brand_name']) : '';
        $company    = isset($_POST['company'])    ? sanitize_text_field($_POST['company'])    : '';
        $phone_hint = isset($_POST['phone_hint']) ? sanitize_text_field($_POST['phone_hint']) : '';

        // اعتبارسنجی ساده
        if ($full_name === '' || $national === '' || $brand_name === '') {
            return self::msg('لطفاً «نام و نام خانوادگی»، «کد ملی» و «نام برند» را تکمیل کنید.', 'error') . self::render_form($_POST);
        }

        // ساخت token و ذخیره‌ی موقت در transient
        $token = wp_generate_password(20, false, false);
        set_transient(self::TRANSIENT_PREFIX . $token, [
            'full_name'  => $full_name,
            'national'   => $national,
            'brand_name' => $brand_name,
            'company'    => $company,
            'phone_hint' => $phone_hint,
            'created'    => time(),
        ], 30 * MINUTE_IN_SECONDS); // ← اعتبار ۳۰ دقیقه

        // آدرس بازگشت بعد از لاگین Digits (همان صفحه فعلی + token)
        $return_url = add_query_arg(['jbg_sponsor_token' => $token], self::current_url());

        // آدرس صفحه لاگین/ثبت‌نام Digits با پارامتر redirect_to
        $login_url = add_query_arg(
            ['redirect_to' => rawurlencode($return_url)],
            home_url(self::DIGITS_LOGIN_URL)
        );
        $login_url = esc_url_raw($login_url); // ← پاکسازی جهت استفاده در JS

        // به‌جای wp_redirect/exit که header را تغییر می‌دهند، از جاوااسکریپت استفاده می‌کنیم
        // تا با هیچ خروجی قبلی (مثلاً Elementor) تداخلی نداشته باشد.
        $js = '<script>
            // هدایت کاربر به صفحهٔ ورود/ثبت‌نام Digits برای تأیید شماره
            window.location.href = "' . esc_js($login_url) . '";
        </script>';

        // برای اطمینان، یک پیام هم قبل از اسکریپت نمایش داده می‌شود
        return self::msg('در حال انتقال به صفحهٔ تأیید شماره همراه...', 'success') . $js;
    }

    /** پس از لاگین کاربر: اعمال داده‌های اسپانسر به پروفایل و نقش */
    private static function complete_for_logged_user(string $token): string {
        $data = get_transient(self::TRANSIENT_PREFIX . $token);
        if (!$data || !is_array($data)) {
            return self::msg('اطلاعات ثبت‌نام موقتی پیدا نشد یا منقضی شده است. لطفاً دوباره فرم را ارسال کنید.', 'error');
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return self::msg('ابتدا وارد حساب کاربری شوید.', 'error');
        }

        // ذخیره‌ی متاها
        update_user_meta($user_id, 'jbg_sponsor_full_name',  (string)$data['full_name']);
        update_user_meta($user_id, 'jbg_sponsor_national_id',(string)$data['national']);
        update_user_meta($user_id, 'jbg_brand_name',         (string)$data['brand_name']);
        update_user_meta($user_id, 'jbg_company',            (string)$data['company']);
        if (!empty($data['phone_hint'])) {
            update_user_meta($user_id, 'jbg_phone_hint', (string)$data['phone_hint']);
        }

        // نقش اسپانسر را به کاربر اضافه می‌کنیم (بدون حذف نقش‌های دیگر)
        $user = get_userdata($user_id);
        if ($user && !in_array(self::ROLE, (array)$user->roles, true)) {
            $user->add_role(self::ROLE);
        }

        // پاکسازی transient
        delete_transient(self::TRANSIENT_PREFIX . $token);

        return self::msg('ثبت‌نام اسپانسر/برند با موفقیت تکمیل شد. کارشناسان ما در صورت نیاز با شما تماس خواهند گرفت.', 'success');
    }

    /** رندر HTML فرم ثبت‌نام اسپانسر */
    private static function render_form(array $old = []): string {
        ob_start();
        ?>
        <style>
        /* استایل سبک و RTL برای فرم اسپانسر */
        .jbg-sponsor-form{direction:rtl;max-width:720px;margin:16px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px}
        .jbg-sponsor-form h3{margin:0 0 12px;font-weight:800}
        .jbg-sponsor-form .row{display:flex;gap:12px;flex-wrap:wrap}
        .jbg-sponsor-form .row .col{flex:1 1 240px}
        .jbg-sponsor-form label{display:block;font-size:13px;color:#374151;margin:8px 0 6px}
        .jbg-sponsor-form input[type="text"]{width:100%;height:40px;border:1px solid #e5e7eb;border-radius:8px;padding:0 10px}
        .jbg-sponsor-form .note{font-size:12px;color:#6b7280;margin-top:6px}
        .jbg-sponsor-form button{margin-top:12px;background:#016f87;color:#fff;border:none;border-radius:9999px;padding:10px 18px;font-weight:700;cursor:pointer}
        .jbg-msg{margin:12px 0;padding:10px 12px;border-radius:10px}
        .jbg-msg.error{background:#fef2f2;color:#7f1d1d;border:1px solid #fecaca}
        .jbg-msg.success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        </style>
        <form method="post" class="jbg-sponsor-form">
            <h3>فرم ثبت‌نام اسپانسر / برند</h3>
            <div class="row">
                <div class="col">
                    <label>نام و نام خانوادگی *</label>
                    <input type="text" name="full_name" value="<?php echo esc_attr($old['full_name'] ?? ''); ?>" required>
                </div>
                <div class="col">
                    <label>کد ملی *</label>
                    <input type="text" name="national_id" value="<?php echo esc_attr($old['national_id'] ?? ''); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <label>نام برند *</label>
                    <input type="text" name="brand_name" value="<?php echo esc_attr($old['brand_name'] ?? ''); ?>" required>
                </div>
                <div class="col">
                    <label>نام شرکت (اختیاری)</label>
                    <input type="text" name="company" value="<?php echo esc_attr($old['company'] ?? ''); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <label>شماره تماس (جهت هماهنگی – اختیاری)</label>
                    <input type="text" name="phone_hint" value="<?php echo esc_attr($old['phone_hint'] ?? ''); ?>">
                    <div class="note">تأیید هویت شما همچنان از طریق صفحه‌ی Digits و پیامک انجام می‌شود.</div>
                </div>
            </div>
            <?php wp_nonce_field('jbg_sponsor_reg'); ?>
            <button type="submit" name="jbg_sponsor_submit" value="1">مرحله بعد (تأیید شماره با پیامک)</button>
        </form>
        <?php
        return ob_get_clean();
    }

    /** ساخت پیام وضعیت */
    private static function msg(string $text, string $type = 'success'): string {
        return '<div class="jbg-msg ' . esc_attr($type) . '">' . esc_html($text) . '</div>';
    }

    /** URL فعلی بدون fragment */
    private static function current_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = strtok($_SERVER['REQUEST_URI'] ?? '', '#');
        return esc_url_raw($scheme . '://' . $host . $uri);
    }
}
