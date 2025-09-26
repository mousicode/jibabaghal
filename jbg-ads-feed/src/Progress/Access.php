<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

class Access {

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

    /** شمارهٔ مرحلهٔ آگهی. اگر در ترتیب فعلی وجود ندارد، 0 برمی‌گردد. */
    public static function seq(int $ad_id): int {
        $map = self::seq_map();
        return isset($map[$ad_id]) ? (int) $map[$ad_id] : 0;
    }

    public static function max_seq(): int {
        $map = self::seq_map();
        return (int) max(1, count($map));
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

    public static function is_unlocked(int $user_id, int $ad_id): bool {
        $allow = ($user_id > 0) ? self::unlocked_max($user_id) : 1;
        return (self::seq($ad_id) > 0) && (self::seq($ad_id) <= $allow);
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
        update_user_meta($user_id, 'jbg_progress_sig', self::content_sig());
    }

    public static function bootstrap(): void {
        add_action('save_post_jbg_ad', function(){ self::bump_progress_sig(); }, 999);
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
