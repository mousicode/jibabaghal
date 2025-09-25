<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

class Access {

    /** امضای محتوا (هر بار مجموعه ویدیوها عوض شود، این مقدار تغییر می‌کند) */
    public static function content_sig(): string {
        $sig = get_option('jbg_ads_progress_sig', '');
        if (!$sig) {
            // fallback سبک: تعداد + آخرین زمان ویرایش
            $count = 0; $last = '0';
            $posts = get_posts([
                'post_type'      => 'jbg_ad',
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => 50, // کافی است برای ساخت اولیه
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $count = is_array($posts) ? count($posts) : 0;
            if (!empty($posts)) {
                $p   = get_post($posts[0]);
                $last = $p ? (string) strtotime($p->post_modified_gmt ?: $p->post_date_gmt) : '0';
            }
            $sig = md5($count . ':' . $last);
            update_option('jbg_ads_progress_sig', $sig, false);
        }
        return (string) $sig;
    }

    /** وقتی محتوای jbg_ad ذخیره/حذف شد، سیگنچر را بالا ببریم */
    public static function bump_progress_sig(): void {
        update_option('jbg_ads_progress_sig', md5( (string) time() ), false);
        delete_transient('jbg_seq_map'); // ترتیب را دوباره بسازیم
    }

    /** نقشهٔ ترتیب: id => seq (مبنای CPV↓، بودجه↓، Boost↓) */
    public static function seq_map(): array {
        $map = get_transient('jbg_seq_map');
        if (is_array($map) && !empty($map)) return $map;

        $ids = get_posts([
            'post_type'      => 'jbg_ad',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
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

        // مرتب‌سازی: CPV ↓ → BR ↓ → Boost ↓
        usort($items, function($a,$b){
            if ($a['cpv'] !== $b['cpv'])   return ($b['cpv']   <=> $a['cpv']);
            if ($a['br']  !== $b['br'])    return ($b['br']    <=> $a['br']);
            return ($b['boost'] <=> $a['boost']);
        });

        $map = [];
        $seq = 1;
        foreach ($items as $it) {
            $map[(int)$it['id']] = $seq++;
        }

        set_transient('jbg_seq_map', $map, 60); // 60s کافی است
        return $map;
    }

    /** شمارهٔ مرحلهٔ این آگهی */
    public static function seq(int $ad_id): int {
        $map = self::seq_map();
        return isset($map[$ad_id]) ? (int) $map[$ad_id] : 999999;
    }

    /** بیشینهٔ مرحلهٔ موجود */
    public static function max_seq(): int {
        $map = self::seq_map();
        return (int) max(1, count($map));
    }

    /** بیشینهٔ مرحلهٔ بازِ کاربر (با کنترل امضای محتوا و کَلمپ) */
    public static function unlocked_max(int $user_id): int {
        if ($user_id <= 0) return 1;

        $user_max = (int) get_user_meta($user_id, 'jbg_unlocked_max_seq', true);
        if ($user_max < 1) $user_max = 1;

        $cur_sig = self::content_sig();
        $usr_sig = (string) get_user_meta($user_id, 'jbg_progress_sig', true);

        // اگر امضای کاربر با امضای فعلی فرق کرد، ریست امن به مرحله ۱
        if ($usr_sig !== $cur_sig) {
            $user_max = 1;
            update_user_meta($user_id, 'jbg_unlocked_max_seq', $user_max);
            update_user_meta($user_id, 'jbg_progress_sig',     $cur_sig);
        }

        // اگر قبلاً بیشتر از سقف فعلی شده، کَلمپ کن
        $max_now = self::max_seq();
        if ($user_max > $max_now) {
            $user_max = $max_now;
            update_user_meta($user_id, 'jbg_unlocked_max_seq', $user_max);
        }

        return $user_max;
    }

    /** آیا این آگهی برای کاربر باز است؟ */
    public static function is_unlocked(int $user_id, int $ad_id): bool {
        $allow = ($user_id > 0) ? self::unlocked_max($user_id) : 1;
        return (self::seq($ad_id) <= $allow);
    }

    /** مرحلهٔ بعدی (id آگهی بعدی) */
    public static function next_ad_id(int $current_id): int {
        $map  = self::seq_map();
        $seq  = self::seq($current_id);
        $next = $seq + 1;
        if ($next > count($map)) return 0;
        $flip = array_flip($map); // seq => id
        return isset($flip[$next]) ? (int) $flip[$next] : 0;
    }

    /** وقتی آزمون پاس شد، مرحلهٔ کاربر را بالا ببر */
    public static function promote_after_pass(int $user_id, int $ad_id): void {
        if ($user_id <= 0 || $ad_id <= 0) return;

        $cur  = self::unlocked_max($user_id);
        $seq  = self::seq($ad_id);
        if ($seq >= $cur) {
            $new = $seq + 1;
            $max = self::max_seq();
            if ($new > $max) $new = $max; // از سقف بیشتر نشود
            update_user_meta($user_id, 'jbg_unlocked_max_seq', $new);
        }
        // امضای کاربر را تضمین کنیم
        $sig = self::content_sig();
        update_user_meta($user_id, 'jbg_progress_sig', $sig);
    }

    /** بوت‌استرپ هوک‌ها */
    public static function bootstrap(): void {
        // وقتی محتوای jbg_ad تغییر کند، امضا را بالا ببر
        add_action('save_post_jbg_ad', function(){ self::bump_progress_sig(); }, 999);
        add_action('deleted_post', function($post_id){
            if (get_post_type($post_id) === 'jbg_ad') self::bump_progress_sig();
        }, 10, 1);

        // وقتی آزمون پاس شد، مرحله را بالا ببریم
        add_action('jbg_quiz_passed', function($user_id, $ad_id){
            self::promote_after_pass((int)$user_id, (int)$ad_id);
        }, 10, 2);

        // اگر بیلینگ شما هم ایونت جداگانه‌ای دارد (مثلاً jbg_billed)، اینجا هم می‌توانید promote کنید
        add_action('jbg_billed', function($user_id, $ad_id){
            self::promote_after_pass((int)$user_id, (int)$ad_id);
        }, 10, 2);
    }
}
