<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

class Access {

    /* ───────────────────── غیرفعال‌سازی موقت قفل‌ها ─────────────────────
     * ⚠️ نسخه‌ی موقت: قفل‌ها «کاملاً» غیرفعال هستند و همهٔ ویدیوها بازند.
     * برای بازگردانی حالت عادی، مقدار بازگشتی این تابع را false کنید.
     */
    private static function disabled(): bool {
        return true; // ← موقتاً همه چیز باز است
    }

    /* ---------------------- Signature & ordering ---------------------- */

    public static function content_sig(): string {
        $sig = get_option('jbg_ads_progress_sig', '');
        if (!$sig) {
            $count = 0; $last = '0';
            $posts = get_posts([
                'post_type'      => 'jbg_ad',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => 50,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $count = is_array($posts) ? count($posts) : 0;
            if (!empty($posts)) {
                $p = get_post($posts[0]);
                $last = $p ? (string) strtotime($p->post_modified_gmt ?: $p->post_date_gmt) : '0';
            }
            $sig = md5($count . ':' . $last);
            update_option('jbg_ads_progress_sig', $sig, false);
        }
        return (string) $sig;
    }

    public static function bump_progress_sig(): void {
        update_option('jbg_ads_progress_sig', md5((string) time()), false);
        delete_transient('jbg_seq_map');
    }

    public static function seq_map(): array {
        $map = get_transient('jbg_seq_map');
        if (is_array($map) && !empty($map)) return $map;

        $ids = get_posts([
            'post_type'       => 'jbg_ad',
            'post_status'     => 'publish',
            'fields'          => 'ids',
            'posts_per_page'  => -1,
            'suppress_filters'=> true,
        ]);

        $items = [];
        foreach ($ids as $id) {
            $items[] = [
                'id'    => (int) $id,
                'cpv'   => (int) get_post_meta($id, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($id, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($id, 'jbg_priority_boost', true),
            ];
        }

        // ترتیب: CPV↓ → BR↓ → Boost↓
        usort($items, function($a,$b){
            if ($a['cpv'] !== $b['cpv'])   return ($b['cpv']   <=> $a['cpv']);
            if ($a['br']  !== $b['br'])    return ($b['br']    <=> $a['br']);
            return ($b['boost'] <=> $a['boost']);
        });

        $map = []; $seq = 1;
        foreach ($items as $it) $map[(int)$it['id']] = $seq++;
        set_transient('jbg_seq_map', $map, 60);
        return $map;
    }

    public static function seq(int $ad_id): int {
        $map = self::seq_map();
        return isset($map[$ad_id]) ? (int) $map[$ad_id] : 0;
    }

    public static function max_seq(): int {
        $map = self::seq_map();
        return (int) max(1, count($map));
    }

    /* ---------------------- Passed helpers ---------------------- */

    /** لیست تمام آگهی‌های پاس‌شده‌ی کاربر (idها) */
    private static function passed_ids(int $user_id): array {
        if ($user_id <= 0) return [];
        $passed = [];

        $list = get_user_meta($user_id, 'jbg_quiz_passed_ids', true);
        if (is_array($list)) $passed = array_map('intval', $list);

        // متاهای تکی قدیمی (سازگاری عقبگرد)
        $all = get_user_meta($user_id);
        foreach ($all as $k => $_) {
            if (preg_match('/^jbg_(?:quiz_passed|points_awarded)_(\d+)$/', (string)$k, $m)) {
                $passed[] = (int) $m[1];
            }
        }

        // یکتا + فقط آیتم‌های موجود در ترتیب فعلی
        $passed = array_values(array_unique(array_filter($passed)));
        $map = self::seq_map();
        return array_values(array_filter($passed, function($pid) use ($map){
            return isset($map[(int)$pid]);
        }));
    }

    /** آیا کاربر این آگهی را قبلاً پاس کرده است؟ */
    public static function has_passed(int $user_id, int $ad_id): bool {
        if ($user_id <= 0 || $ad_id <= 0) return false;
        if (get_user_meta($user_id, 'jbg_quiz_passed_' . $ad_id, true)) return true;
        $list = self::passed_ids($user_id);
        return in_array($ad_id, $list, true);
    }

    /** تعداد آگهی‌های پاس‌شدهٔ موجود در ترتیب فعلی */
    private static function passed_count_current(int $user_id): int {
        return count(self::passed_ids($user_id));
    }

    /** «رتبهٔ مجاز» فعلی: تعداد پاس‌شده‌ها + ۱ (کَپ شده به تعداد کل) */
    private static function allowed_rank(int $user_id): int {
        if ($user_id <= 0) return 1;
        $rank = self::passed_count_current($user_id) + 1;
        $max  = self::max_seq();
        if ($rank > $max) $rank = $max;
        if ($rank < 1)    $rank = 1;
        return (int) $rank;
    }

    /* ---------------------- Public API ---------------------- */

    /**
     * گیت نهایی:
     *  - حالت موقت: قفل‌ها خاموش → همیشه true
     *  - حالت عادی: اگر قبلاً پاس شده یا رتبهٔ موردنظر ≤ allowed_rank باشد → true
     */
    public static function is_unlocked(int $user_id, int $ad_id): bool {
        // ★ موقت: همهٔ ویدیوها باز هستند
        if (self::disabled()) return true;

        $seq = self::seq($ad_id);
        if ($seq <= 0) return false;      // خارج از ترتیب

        if ($user_id <= 0) {
            // مهمان فقط رتبه ۱
            return ($seq === 1);
        }

        if (self::has_passed($user_id, $ad_id)) {
            return true;
        }

        $allowed = self::unlocked_max($user_id); // = allowed_rank با سینک متا
        return ($seq <= $allowed);
    }

    /** برای سازگاری با کدهای دیگر: مقدار «مرحلهٔ باز» را برمی‌گرداند. */
    public static function unlocked_max(int $user_id): int {
        // ★ موقت: در حالت خاموش بودن قفل‌ها، همهٔ مراحل باز فرض می‌شود
        if (self::disabled()) return self::max_seq();

        if ($user_id <= 0) return 1;

        $cur_sig = self::content_sig();
        $usr_sig = (string) get_user_meta($user_id, 'jbg_progress_sig', true);

        // در هر حالت allowed_rank را از روی تعداد پاس‌شده‌ها حساب می‌کنیم
        $allowed = self::allowed_rank($user_id);

        // اگر امضا عوض شده بود یا مقدار ذخیره‌شده متفاوت است، متا را به‌روز کن
        $stored = (int) get_user_meta($user_id, 'jbg_unlocked_max_seq', true);
        if ($stored !== $allowed || $usr_sig !== $cur_sig) {
            update_user_meta($user_id, 'jbg_unlocked_max_seq', $allowed);
            update_user_meta($user_id, 'jbg_progress_sig',     $cur_sig);
        }

        return $allowed;
    }

    public static function next_ad_id(int $current_id): int {
        $map  = self::seq_map();
        $seq  = self::seq($current_id);
        if ($seq <= 0) return 0;
        $next = $seq + 1;
        if ($next > count($map)) return 0;
        $flip = array_flip($map); // seq => id
        return isset($flip[$next]) ? (int) $flip[$next] : 0;
    }

    /** پس از پاس‌شدن آزمون/بیلینگ: متای مرحلهٔ باز را بر اساس «تعداد پاس‌شده‌ها + ۱» به‌روز کن. */
    public static function promote_after_pass(int $user_id, int $ad_id): void {
        if ($user_id <= 0 || $ad_id <= 0) return;

        // allowed_rank خودش با توجه به تعداد پاس‌شده‌ها حساب می‌شود
        $allowed = self::allowed_rank($user_id);
        update_user_meta($user_id, 'jbg_unlocked_max_seq', $allowed);
        update_user_meta($user_id, 'jbg_progress_sig', self::content_sig());
    }

    /* ---------------------- Head CSS (پنهان‌سازی ظاهر قفل) ---------------------- */

    /** 
     * چاپ یک CSS سبک در <head> تا هر «نشان/باکس قفل» پنهان شود.
     * با این کار هم badge «قفل» در کارت‌ها مخفی می‌شود، هم باکس هشدار در صفحهٔ تکی.
     */
    public static function print_global_css(): void {
        if (is_admin()) return;
        echo '<style id="jbg-hide-lock-ui">
        /* پنهان‌سازی نشان قفل در همهٔ صفحات/کارت‌ها */
        .jbg-badge.lock{display:none!important;visibility:hidden!important;opacity:0!important}
        /* پنهان‌سازی باکس اطلاع‌رسانی قفل در صفحهٔ تکی (لاین نقطه‌چین) */
        .jbg-locked{display:none!important;visibility:hidden!important;opacity:0!important}
        </style>';
    }

    /* ---------------------- Bootstrapping ---------------------- */

    public static function bootstrap(): void {
        add_action('save_post_jbg_ad', function(){ self::bump_progress_sig(); }, 999);
        add_action('deleted_post', function($post_id){
            if (get_post_type($post_id) === 'jbg_ad') self::bump_progress_sig();
        }, 10, 1);

        // بعد از پاس آزمون یا بیلینگ
        add_action('jbg_quiz_passed', function($user_id, $ad_id){
            self::promote_after_pass((int)$user_id, (int)$ad_id);
        }, 10, 2);
        add_action('jbg_billed', function($user_id, $ad_id){
            self::promote_after_pass((int)$user_id, (int)$ad_id);
        }, 10, 2);

        // ★ تزریق CSS سراسری برای پنهان‌سازی UI مربوط به قفل
        add_action('wp_head', [self::class, 'print_global_css'], 5);
    }
}
