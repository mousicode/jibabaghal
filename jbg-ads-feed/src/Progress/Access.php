<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

/**
 * مدیریت ترتیب، قفل/بازشدن مراحل، و بازسازی پیشرفت کاربر
 */
class Access {

    /** امضای محتوایی (برای بازسازی پیشرفت در صورت تغییر لیست/ترتیب) */
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

    /** تغییر امضا و پاک کردن کش ترتیب */
    public static function bump_progress_sig(): void {
        update_option('jbg_ads_progress_sig', md5((string) time()), false);
        delete_transient('jbg_seq_map');
    }

    /** نقشهٔ ترتیب: ad_id => seq (مرتب‌سازی: CPV↓ → BR↓ → Boost↓) */
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

    /** شمارهٔ مرحلهٔ آگهی. اگر در ترتیب فعلی وجود ندارد، 0 برمی‌گردد. */
    public static function seq(int $ad_id): int {
        $map = self::seq_map();
        return isset($map[$ad_id]) ? (int) $map[$ad_id] : 0;
    }

    /** بیشینهٔ مرحله (تعداد مراحل فعلی) */
    public static function max_seq(): int {
        $map = self::seq_map();
        return (int) max(1, count($map));
    }

    /** آیا کاربر این آگهی را پاس کرده است؟ */
    private static function has_passed(int $user_id, int $ad_id): bool {
        if ($user_id <= 0 || $ad_id <= 0) return false;
        if (get_user_meta($user_id, 'jbg_quiz_passed_' . $ad_id, true)) return true;
        $list = get_user_meta($user_id, 'jbg_quiz_passed_ids', true);
        if (is_array($list) && in_array($ad_id, array_map('intval', $list), true)) return true;
        return false;
    }

    /** بازسازی پیشرفت از متاهای پاس‌شده (فقط آگهی‌های موجود در ترتیب فعلی لحاظ می‌شوند) */
    private static function reconstruct_progress_from_passed(int $user_id): int {
        if ($user_id <= 0) return 1;

        $passed_ids = [];
        $list = get_user_meta($user_id, 'jbg_quiz_passed_ids', true);
        if (is_array($list)) $passed_ids = array_map('intval', $list);

        $all = get_user_meta($user_id);
        foreach ($all as $k => $_) {
            if (preg_match('/^jbg_(?:quiz_passed|points_awarded)_(\d+)$/', (string)$k, $m)) {
                $passed_ids[] = (int) $m[1];
            }
        }
        $passed_ids = array_values(array_unique(array_filter($passed_ids)));
        if (empty($passed_ids)) return 1;

        $max_seq_seen = 0;
        foreach ($passed_ids as $ad_id) {
            $s = self::seq((int)$ad_id);
            if ($s > 0 && $s > $max_seq_seen) $max_seq_seen = $s;
        }
        if ($max_seq_seen <= 0) return 1;

        $candidate = $max_seq_seen + 1;
        $cap = self::max_seq();
        if ($candidate > $cap) $candidate = $cap;
        if ($candidate < 1)    $candidate = 1;

        return (int) $candidate;
    }

    /** آخرین مرحلهٔ بازِ کاربر (با بازسازی خودکار در صورت تغییر محتوا) */
    public static function unlocked_max(int $user_id): int {
        if ($user_id <= 0) return 1;

        $user_max = (int) get_user_meta($user_id, 'jbg_unlocked_max_seq', true);
        if ($user_max < 1) $user_max = 1;

        $cur_sig = self::content_sig();
        $usr_sig = (string) get_user_meta($user_id, 'jbg_progress_sig', true);

        if ($usr_sig !== $cur_sig) {
            $rebuilt = self::reconstruct_progress_from_passed($user_id);
            if ($rebuilt > $user_max) $user_max = $rebuilt;

            $max_now = self::max_seq();
            if ($user_max > $max_now) $user_max = $max_now;

            update_user_meta($user_id, 'jbg_unlocked_max_seq', $user_max);
            update_user_meta($user_id, 'jbg_progress_sig',     $cur_sig);
        } else {
            $max_now = self::max_seq();
            if ($user_max > $max_now) {
                $user_max = $max_now;
                update_user_meta($user_id, 'jbg_unlocked_max_seq', $user_max);
            }
        }
        return $user_max;
    }

    /**
     * گیت قفل:
     * 1) seq باید <= unlocked_max باشد
     * 2) اگر محتوا «بعد از» آخرین پیشرفت کاربر منتشر شده و هنوز پاس نشده → قفل
     */
    public static function is_unlocked(int $user_id, int $ad_id): bool {
        $seq = self::seq($ad_id);
        if ($seq <= 0) return false; // خارج از ترتیب

        $allow = ($user_id > 0) ? self::unlocked_max($user_id) : 1;
        if ($seq > $allow) return false;

        // قفلِ محتوای تازه نسبت به مهر پیشرفت کاربر
        if ($user_id > 0) {
            $mark = (int) get_user_meta($user_id, 'jbg_progress_mark', true);
            if ($mark > 0) {
                // زمان انتشار/ویرایش (GMT)
                $ad_ts = (int) get_post_time('U', true, $ad_id);
                if ($ad_ts <= 0) {
                    $p = get_post($ad_id);
                    if ($p) $ad_ts = (int) strtotime($p->post_modified_gmt ?: $p->post_date_gmt);
                }
                if ($ad_ts > $mark && !self::has_passed($user_id, $ad_id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** ID ویدئوی بعدی نسبت به فعلی (در ترتیب) */
    public static function next_ad_id(int $current_id): int {
        $map  = self::seq_map();
        $seq  = self::seq($current_id);
        if ($seq <= 0) return 0;
        $next = $seq + 1;
        if ($next > count($map)) return 0;
        $flip = array_flip($map); // seq => id
        return isset($flip[$next]) ? (int) $flip[$next] : 0;
    }

    /** ارتقای مرحله پس از پاس‌شدن آزمون/بیلینگ و ثبت مهر پیشرفت */
    public static function promote_after_pass(int $user_id, int $ad_id): void {
        if ($user_id <= 0 || $ad_id <= 0) return;

        $cur  = self::unlocked_max($user_id);
        $seq  = self::seq($ad_id);
        if ($seq >= $cur) {
            $new = $seq + 1;
            $max = self::max_seq();
            if ($new > $max) $new = $max;
            update_user_meta($user_id, 'jbg_unlocked_max_seq', $new);
        }

        // مهر زمانی آخرین پیشرفت (برای قفل محتواهای تازه)
        update_user_meta($user_id, 'jbg_progress_mark', time());
        // امضای محتوایی فعلی
        update_user_meta($user_id, 'jbg_progress_sig', self::content_sig());
    }

    /** هوک‌ها: تغییر محتوا، پاس آزمون، بیلینگ */
    public static function bootstrap(): void {
        add_action('save_post_jbg_ad', function(){
            self::bump_progress_sig();
        }, 999);

        add_action('deleted_post', function($post_id){
            if (get_post_type($post_id) === 'jbg_ad') self::bump_progress_sig();
        }, 10, 1);

        add_action('jbg_quiz_passed', function($user_id, $ad_id){
            self::promote_after_pass((int)$user_id, (int)$ad_id);
        }, 10, 2);

        add_action('jbg_billed', function($user_id, $ad_id){
            self::promote_after_pass((int)$user_id, (int)$ad_id);
        }, 10, 2);
    }
}
