<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

class NextController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/next', [
            'methods'  => 'GET',
            'permission_callback' => function(){ return is_user_logged_in(); },
            'args' => [
                'current' => ['type'=>'integer', 'required'=>true, 'minimum'=>1],
            ],
            'callback' => [self::class, 'handle'],
        ]);
    }

    public static function handle(\WP_REST_Request $req) {
        $uid = get_current_user_id();
        if (!$uid) return new \WP_Error('jbg_auth','Login required',['status'=>401]);

        $current = (int) $req->get_param('current');
        if ($current <= 0 || get_post_type($current) !== 'jbg_ad') {
            return new \WP_Error('jbg_bad_ad','Invalid ad_id',['status'=>400]);
        }

        // شرط تکمیل: تماشای کامل + ( بیلینگ یا قبولی آزمون )
        $watched = (bool) get_user_meta($uid, 'jbg_watched_ok_'.$current, true);
        $billed  = (bool) get_user_meta($uid, 'jbg_billed_'.$current, true);
        $quiz    = (bool) get_user_meta($uid, 'jbg_quiz_passed_'.$current, true);
        if (!($watched && ($billed || $quiz))) {
            return new \WP_REST_Response(['ok'=>false,'reason'=>'incomplete'], 403);
        }

        // ترتیب همان ترتیبی است که الان دارید (cpv↓, budget↓, boost↓, date↓)
        $items = self::sorted_items_for_current($current);
        if (empty($items)) {
            return new \WP_REST_Response(['ok'=>true,'end'=>true,'id'=>0,'url'=>''], 200);
        }

        $ids = array_map(fn($it)=> (int)$it['ID'], $items);
        $idx = array_search($current, $ids, true);
        if ($idx === false || $idx === count($ids) - 1) {
            return new \WP_REST_Response(['ok'=>true,'end'=>true,'id'=>0,'url'=>''], 200);
        }

        $next = (int) $ids[$idx + 1];
        return new \WP_REST_Response([
            'ok'  => true,
            'end' => false,
            'id'  => $next,
            'url' => get_permalink($next),
        ], 200);
    }

    private static function sorted_items_for_current(int $current_id): array {
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
            'meta_query'     => [ ['key'=>'jbg_cpv', 'compare'=>'EXISTS'] ],
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
