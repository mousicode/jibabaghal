<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class AccessGate {

    public static function register(): void {
        add_action('template_redirect', [self::class, 'guard'], 9);
    }

    public static function guard(): void {
        if (!is_singular('jbg_ad')) return;
        if (!is_user_logged_in()) return; // مهمان‌ها: فقط از طریق UI محدود می‌شوند

        $uid        = get_current_user_id();
        $current_id = get_queried_object_id();

        // ترتیب همان آرشیو
        $items = self::ordered_items_for($current_id);
        if (empty($items)) return;

        // ایندکس فعلی
        $ids = array_map(fn($it)=> (int)$it['ID'], $items);
        $idx = array_search($current_id, $ids, true);
        if ($idx === false) return;

        // همه‌ی قبلی‌ها باید «قبولی آزمون» داشته باشند
        $first_locked = null;
        for ($i = 0; $i < $idx; $i++) {
            $ad_id = (int)$items[$i]['ID'];
            $quiz  = (bool) get_user_meta($uid, 'jbg_quiz_passed_'.$ad_id, true);
            if (!$quiz) { $first_locked = $items[$i]; break; }
        }

        if ($first_locked) {
            wp_safe_redirect(get_permalink((int)$first_locked['ID']));
            exit;
        }
    }

    private static function ordered_items_for(int $current_id): array {
        $tax_query = [];
        $terms = wp_get_post_terms($current_id, 'jbg_cat', ['fields'=>'ids']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $tax_query[] = [
                'taxonomy' => 'jbg_cat',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $terms),
            ];
        }

        $q = new \WP_Query([
            'post_type'      => 'jbg_ad',
            'posts_per_page' => 500,
            'no_found_rows'  => true,
            'meta_query'     => [['key'=>'jbg_cpv','compare'=>'EXISTS']],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => $tax_query ?: null,
        ]);

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => (int) $p->ID,
                'cpv'   => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'date'  => get_post_time('U', true, $p->ID),
            ];
        }
        wp_reset_postdata();

        usort($items, function($a, $b){
            if ($a['cpv'] === $b['cpv']) {
                if ($a['br'] === $b['br']) {
                    if ($a['boost'] === $b['boost']) return ($b['date'] <=> $a['date']);
                    return ($b['boost'] <=> $a['boost']);
                }
                return ($b['br'] <=> $a['br']);
            }
            return ($b['cpv'] <=> $a['cpv']);
        });

        return $items;
    }
}
