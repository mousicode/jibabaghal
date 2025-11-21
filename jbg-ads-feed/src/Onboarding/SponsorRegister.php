<?php
namespace JBG\Ads\Onboarding;
if (!defined('ABSPATH')) exit;

/**
 * ثبت‌نام جداگانه برای «اسپانسرها/برندها» بدون تغییر در ثبت‌نام معمولی (Digits).
 *
 * فلو:
 *   1) صفحه اول (شورتکد [jbg_sponsor_register]):
 *      - دریافت نام، کد ملی، نام برند و ...
 *      - ذخیره موقت در transient
 *      - هدایت (با JS) به صفحه انتخاب پلن
 *
 *   2) صفحه انتخاب پلن (شورتکد [jbg_sponsor_plans]):
 *      - نمایش سه پلن (رایگان / اقتصادی / سازمانی)
 *      - کلیک روی هر پلن → رفتن به صفحه لاگین/ثبت‌نام Digits با redirect_to
 *
 *   3) بعد از تأیید شماره (Digits) → بازگشت به همین صفحه پلن با token + plan
 *      - اعمال داده‌ها روی user_meta
 *      - ثبت نقش jbg_sponsor
 *      - ذخیره پلن انتخاب‌شده
 *
 * نکته مهم: در هیچ نقطه‌ای از header() یا wp_redirect استفاده نمی‌کنیم
 * تا با خروجی دیگر پلاگین‌ها (مثل Elementor) تداخل نداشته باشد.
 */
class SponsorRegister {

    /** آدرس صفحه‌ی لاگین/ثبت‌نام Digits (صفحه‌ی اصلی ورود کاربران) */
    private const DIGITS_LOGIN_URL = '/my-account/'; // ← اگر اسلاگ صفحه ورود چیز دیگری است این را عوض کن

    /** اسلاگ صفحه انتخاب پلن (جایی که شورتکد [jbg_sponsor_plans] را می‌گذاری) */
    private const PLANS_PAGE_SLUG  = '/brands-plans/'; // ← صفحه «پلن‌ها» را با این اسلاگ بساز یا این مقدار را با اسلاگ واقعی هماهنگ کن

    /** پیشوند ترنزینت برای نگه‌داری موقت دیتا */
    private const TRANSIENT_PREFIX = 'jbg_sponsor_reg_';

    /** نام نقش اختصاصی اسپانسر */
    private const ROLE = 'jbg_sponsor';

    /* --------------------------------------------------------------------
     * رجیستر هوک‌ها و شورتکدها
     * -------------------------------------------------------------------- */
    public static function register(): void {
        add_action('init', [self::class, 'maybe_add_role']);

        // شورتکد صفحه اول (فرم اطلاعات اسپانسر)
        add_shortcode('jbg_sponsor_register', [self::class, 'shortcode_register']);

        // شورتکد صفحه دوم (انتخاب پلن + تکمیل نهایی بعد از لاگین)
        add_shortcode('jbg_sponsor_plans',    [self::class, 'shortcode_plans']);
    }

    /** اگر نقش اسپانسر وجود نداشته باشد، آن را (بر پایه "subscriber") ایجاد کن */
    public static function maybe_add_role(): void {
        if (!get_role(self::ROLE)) {
            $subscriber = get_role('subscriber');
            $caps = $subscriber ? $subscriber->capabilities : [];
            add_role(self::ROLE, 'اسپانسر/برند', $caps);
        }
    }

    /* --------------------------------------------------------------------
     * شورتکد صفحه اول: فرم اطلاعات اسپانسر
     * -------------------------------------------------------------------- */

    /** نمایش فرم / هندل ارسال فرم */
    public static function shortcode_register(): string {
        // اگر فرم ارسال شده است
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jbg_sponsor_submit'])) {
            return self::handle_register_submit();
        }

        // حالت عادی: فقط فرم را نمایش بده
        return self::render_form();
    }

    /**
     * پردازش فرم صفحه اول:
     *  - اعتبارسنجی
     *  - ذخیره در transient
     *  - ارسال کاربر به صفحه انتخاب پلن (با token)
     */
    private static function handle_register_submit(): string {
        // نانس برای جلوگیری از CSRF
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'jbg_sponsor_reg')) {
            return self::msg('درخواست نامعتبر است. لطفاً صفحه را مجدداً بارگذاری کنید.', 'error')
                 . self::render_form($_POST);
        }

        // خواندن ورودی‌ها
        $full_name  = isset($_POST['full_name'])   ? sanitize_text_field($_POST['full_name'])   : '';
        $national   = isset($_POST['national_id']) ? sanitize_text_field($_POST['national_id']) : '';
        $brand_name = isset($_POST['brand_name'])  ? sanitize_text_field($_POST['brand_name'])  : '';
        $company    = isset($_POST['company'])     ? sanitize_text_field($_POST['company'])     : '';
        $phone_hint = isset($_POST['phone_hint'])  ? sanitize_text_field($_POST['phone_hint'])  : '';

        // چک کردن فیلدهای الزامی
        if ($full_name === '' || $national === '' || $brand_name === '') {
            return self::msg('لطفاً «نام و نام خانوادگی»، «کد ملی» و «نام برند» را تکمیل کنید.', 'error')
                 . self::render_form($_POST);
        }

        // ساخت توکن یکتا و ذخیره‌ی موقت دیتا
        $token = wp_generate_password(20, false, false);
        set_transient(
            self::TRANSIENT_PREFIX . $token,
            [
                'full_name'  => $full_name,
                'national'   => $national,
                'brand_name' => $brand_name,
                'company'    => $company,
                'phone_hint' => $phone_hint,
                'created'    => time(),
            ],
            30 * MINUTE_IN_SECONDS // ← اعتبار ۳۰ دقیقه برای تکمیل فرآیند
        );

        // آدرس صفحه انتخاب پلن + token
        $plans_url = add_query_arg(
            ['jbg_sponsor_token' => $token],
            home_url(self::PLANS_PAGE_SLUG)
        );
        $plans_url = esc_url($plans_url);

        // پیام + هدایت JS به صفحه پلن‌ها (بدون دست‌کاری header)
        $html  = self::msg('اطلاعات اولیه شما ثبت شد. لطفاً در مرحله بعد یک پلن اسپانسری انتخاب کنید.', 'success');
        $html .= '<p style="direction:rtl;text-align:right;margin-top:10px">';
        $html .= '<a href="' . $plans_url . '" class="jbg-sponsor-next-link" style="display:inline-block;background:#016f87;color:#fff;border-radius:9999px;padding:10px 18px;font-weight:700;text-decoration:none;">';
        $html .= 'رفتن به صفحه انتخاب پلن';
        $html .= '</a>';
        $html .= '</p>';
        $html .= '<script>window.location.href="' . esc_js($plans_url) . '";</script>';

        return $html;
    }

    /* --------------------------------------------------------------------
     * شورتکد صفحه دوم: انتخاب پلن + تکمیل بعد از لاگین
     * -------------------------------------------------------------------- */

    public static function shortcode_plans(): string {
        $token = isset($_GET['jbg_sponsor_token']) ? sanitize_text_field($_GET['jbg_sponsor_token']) : '';

        // بدون token امکان ادامه نیست
        if ($token === '') {
            return self::msg('توکن ثبت‌نام یافت نشد. لطفاً ابتدا فرم ثبت‌نام اسپانسر را تکمیل کنید.', 'error');
        }

        // اگر از Digits برگشته و کاربر لاگین شده و پلن هم مشخص است → تکمیل نهایی
        if (is_user_logged_in() && isset($_GET['jbg_sponsor_plan'])) {
            $plan = sanitize_text_field($_GET['jbg_sponsor_plan']);
            return self::complete_for_logged_user($token, $plan);
        }

        // در غیر این صورت، هنوز لاگین نشده؛ فقط پلن‌ها را نمایش بده
        $data = get_transient(self::TRANSIENT_PREFIX . $token);
        if (!$data || !is_array($data)) {
            return self::msg('اطلاعات ثبت‌نام موقتی پیدا نشد یا منقضی شده است. لطفاً دوباره فرم ثبت‌نام اسپانسر را تکمیل کنید.', 'error');
        }

        return self::render_plans_ui($token);
    }

    /**
     * رندر سه کارت پلن و تولید لینک‌های رفتن به صفحه لاگین Digits.
     * بعد از لاگین، کاربر به همین صفحه (پلن‌ها) با token + plan برمی‌گردد.
     */
    private static function render_plans_ui(string $token): string {
        // آدرس صفحه فعلی (پلن‌ها) با token فعلی
        $base_url = self::current_url();

        // آدرس صفحه لاگین/ثبت‌نام Digits
        $digits_login = home_url(self::DIGITS_LOGIN_URL);

        // برای هر پلن یک redirect_to جداگانه می‌سازیم
$plans = [
    'free'   => [
        'title' => 'پلن رایگان',
        'desc'  => 'شروع همکاری با حداقل امکانات، مناسب تست و آشنایی اولیه.',
    ],
    'eco'    => [
        'title' => 'پلن اقتصادی',
        'desc'  => '۱۰۰ تومان برای هر بازدید ویدیو',
    ],
    'corp'   => [
        'title' => 'پلن سازمانی',
        'desc'  => 'برای اطلاعات بیشتر با ما تماس بگیرید',
    ],
];


        // استایل ساده برای گرید پلن‌ها
        ob_start();
        ?>
        <style>
        .jbg-plans-wrap{direction:rtl;max-width:960px;margin:16px auto;padding:8px}
        .jbg-plans-title{font-weight:800;font-size:20px;margin:0 0 12px}
        .jbg-plans-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
        .jbg-plan-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(15,23,42,.04);display:flex;flex-direction:column}
        .jbg-plan-card h4{margin:0 0 8px;font-weight:800;font-size:18px}
        .jbg-plan-desc{font-size:13px;color:#4b5563;margin-bottom:12px;min-height:40px}
        .jbg-plan-footer{margin-top:auto;display:flex;justify-content:space-between;align-items:center}
        .jbg-plan-badge{font-size:12px;color:#6b7280}
        .jbg-plan-btn{display:inline-block;background:#016f87;color:#fff;border-radius:9999px;padding:8px 14px;font-size:14px;font-weight:700;text-decoration:none;white-space:nowrap}
        </style>
        <div class="jbg-plans-wrap">
            <h3 class="jbg-plans-title">انتخاب پلن اسپانسری</h3>
            <p style="font-size:13px;color:#6b7280;margin:0 0 12px">
                لطفاً یکی از پلن‌های زیر را انتخاب کنید. در مرحله بعدی، شماره موبایل شما از طریق پیامک (Digits) تأیید می‌شود.
            </p>
            <div class="jbg-plans-grid">
                <?php foreach ($plans as $key => $info): ?>
                    <?php
                    // URL بازگشت بعد از لاگین برای این پلن
                    $return_url = add_query_arg([
                        'jbg_sponsor_token' => $token,
                        'jbg_sponsor_plan'  => $key,
                    ], $base_url);
                    // URL صفحه لاگین Digits با redirect_to
                    $login_url = add_query_arg(
                        ['redirect_to' => rawurlencode($return_url)],
                        $digits_login
                    );
                    $login_url = esc_url($login_url);
                    ?>
                    <div class="jbg-plan-card">
                        <h4><?php echo esc_html($info['title']); ?></h4>
                        <div class="jbg-plan-desc"><?php echo esc_html($info['desc']); ?></div>
                        <div class="jbg-plan-footer">
                            <span class="jbg-plan-badge">
                                <?php
                                if ($key === 'free') echo 'بدون هزینه ابتدایی';
                                elseif ($key === 'eco') echo 'تعرفه پیشنهادی برای اکثر برندها';
                                else echo 'شخصی‌سازی بر اساس قرارداد';
                                ?>
                            </span>
                            <a href="<?php echo $login_url; ?>" class="jbg-plan-btn">انتخاب این پلن</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* --------------------------------------------------------------------
     * تکمیل نهایی بعد از لاگین (بازگشت از Digits)
     * -------------------------------------------------------------------- */

    /**
     * بعد از این‌که کاربر از Digits برگشت و لاگین شد:
     *  - داده‌ی موقت (فرم) را می‌خوانیم
     *  - روی user_meta ذخیره می‌کنیم
     *  - نقش jbg_sponsor را اضافه می‌کنیم
     *  - پلن انتخابی را هم ثبت می‌کنیم
     */
    private static function complete_for_logged_user(string $token, string $plan): string {
        $data = get_transient(self::TRANSIENT_PREFIX . $token);
        if (!$data || !is_array($data)) {
            return self::msg('اطلاعات ثبت‌نام موقتی پیدا نشد یا منقضی شده است. لطفاً دوباره فرم ثبت‌نام اسپانسر را تکمیل کنید.', 'error');
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return self::msg('ابتدا وارد حساب کاربری شوید.', 'error');
        }

        // ذخیره متاهای پروفایل اسپانسر
        update_user_meta($user_id, 'jbg_sponsor_full_name',   (string) $data['full_name']);
        update_user_meta($user_id, 'jbg_sponsor_national_id', (string) $data['national']);
        update_user_meta($user_id, 'jbg_brand_name',          (string) $data['brand_name']);
        update_user_meta($user_id, 'jbg_company',             (string) $data['company']);
        if (!empty($data['phone_hint'])) {
            update_user_meta($user_id, 'jbg_phone_hint',      (string) $data['phone_hint']);
        }

        // ثبت پلن انتخاب‌شده
        update_user_meta($user_id, 'jbg_sponsor_plan', (string) $plan);

        // اضافه کردن نقش اسپانسر (بدون حذف نقش‌های قبلی)
        $user = get_userdata($user_id);
        if ($user && !in_array(self::ROLE, (array) $user->roles, true)) {
            $user->add_role(self::ROLE);
        }

        // ترنزینت را پاک کن
        delete_transient(self::TRANSIENT_PREFIX . $token);

        // پیام موفقیت
        $plan_label = ($plan === 'free') ? 'رایگان' : (($plan === 'eco') ? 'اقتصادی' : 'سازمانی');
        $msg = 'ثبت‌نام اسپانسر/برند با پلن «' . $plan_label . '» با موفقیت تکمیل شد. کارشناسان ما در صورت نیاز با شما تماس خواهند گرفت.';

        return self::msg($msg, 'success');
    }

    /* --------------------------------------------------------------------
     * رندر فرم صفحه اول
     * -------------------------------------------------------------------- */

    /** فرم ثبت‌نام اسپانسر (صفحه اول) */
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
                    <div class="note">تأیید هویت شما همچنان از طریق صفحه‌ی ورود / ثبت‌نام (Digits) و پیامک انجام می‌شود.</div>
                </div>
            </div>
            <?php wp_nonce_field('jbg_sponsor_reg'); ?>
            <button type="submit" name="jbg_sponsor_submit" value="1">مرحله بعد (انتخاب پلن)</button>
        </form>
        <?php
        return ob_get_clean();
    }

    /* --------------------------------------------------------------------
     * ابزارهای کمکی
     * -------------------------------------------------------------------- */

    /** ساخت HTML پیام وضعیت */
    private static function msg(string $text, string $type = 'success'): string {
        return '<div class="jbg-msg ' . esc_attr($type) . '">' . esc_html($text) . '</div>';
    }

    /** URL فعلی بدون fragment (برای استفاده در redirect_to) */
    private static function current_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = strtok($_SERVER['REQUEST_URI'] ?? '', '#');
        return esc_url_raw($scheme . '://' . $host . $uri);
    }
}
