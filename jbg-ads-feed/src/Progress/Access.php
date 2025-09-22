<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

class Access {

    private static array $ranked = []; // کش بر اساس CPV/BR/Boost

    private static function ranked_ids(): array {
        if (!empty(self::$ranked)) return self::$ranked;

        $q = new \WP_Query([
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'suppress_filters'    => true,
            'lang'                => 'all',
        ]);

        $rows = [];
        foreach ($q->posts as $pid) {
            $rows[] = [
                'id'    => (int) $pid,
                'cpv'   => (int) get_post_meta($pid, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($pid, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($pid, 'jbg_priority_boost', true),
            ];
        }
        wp_reset_postdata();

        usort($rows, function($a,$b){
            if ($a['cpv'] !== $b['cpv'])   return ($b['cpv']   <=> $a['cpv']);
            if ($a['br']  !== $b['br'])    return ($b['br']    <=> $a['br']);
            return ($b['boost'] <=> $a['boost']);
        });

        self::$ranked = array_map(fn($r)=>$r['id'], $rows);
        return self::$ranked;
    }

    public static function seq(int $ad_id): int {
        $ids = self::ranked_ids();
        $pos = array_search($ad_id, $ids, true);
        return ($pos === false) ? 1 : ($pos + 1);
    }

    public static function unlocked_max(int $user_id): int {
        $v = (int) get_user_meta($user_id, 'jbg_unlocked_max_seq', true);
        return max(1, $v);
    }

    public static function is_unlocked(?int $user_id, int $ad_id): bool {
        $seq = self::seq($ad_id);
        if ($user_id <= 0) return ($seq <= 1);
        return $seq <= self::unlocked_max((int)$user_id);
    }

    public static function next_ad_id(int $current_id): int {
        $ids = self::ranked_ids();
        $i   = array_search($current_id, $ids, true);
        return ($i !== false && isset($ids[$i+1])) ? (int) $ids[$i+1] : 0;
    }
}
