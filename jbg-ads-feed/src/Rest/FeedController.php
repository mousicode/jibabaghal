<?php
namespace JBG\Ads\Rest;

class FeedController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/feed', [
            'methods'  => 'GET',
            'callback' => [self::class, 'get_feed'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit'   => ['type'=>'integer','default'=>10,'minimum'=>1,'maximum'=>50],
                'min_cpv' => ['type'=>'integer','required'=>false],
                'max_cpv' => ['type'=>'integer','required'=>false],
                'cat'     => ['type'=>'string','required'=>false],
                'cat_id'  => ['type'=>'integer','required'=>false],
            ],
        ]);
    }

    public static function get_feed(\WP_REST_Request $req) {
        try {
            $limit   = max(1, min(50, (int) $req->get_param('limit')));
            $min_cpv = (int) $req->get_param('min_cpv');
            $max_cpv = (int) $req->get_param('max_cpv');
            $catSlug = (string) $req->get_param('cat');
            $catId   = (int) $req->get_param('cat_id');

            if (!post_type_exists('jbg_ad')) {
                return new \WP_Error('jbg_no_cpt', 'Ad post type not found', ['status'=>500]);
            }

            // --- فیلترهای متا + کلیدهای مرتب‌سازی به‌صورت clause
            $meta_query = [
                'relation'    => 'AND',
                'fundable'    => ['key'=>'jbg_is_fundable','value'=>1,'compare'=>'=','type'=>'NUMERIC'],
                'cpv_clause'  => ['key'=>'jbg_cpv','type'=>'NUMERIC'],
                'br_clause'   => ['key'=>'jbg_budget_remaining','type'=>'NUMERIC'],
                'pb_clause'   => ['key'=>'jbg_priority_boost','type'=>'NUMERIC'],
            ];
            if ($min_cpv) $meta_query[] = ['key'=>'jbg_cpv','value'=>$min_cpv,'compare'=>'>=','type'=>'NUMERIC'];
            if ($max_cpv) $meta_query[] = ['key'=>'jbg_cpv','value'=>$max_cpv,'compare'=>'<=','type'=>'NUMERIC'];

            $tax_query = [];
            if ($catSlug) $tax_query[] = ['taxonomy'=>'jbg_cat','field'=>'slug','terms'=>[$catSlug]];
            if ($catId)   $tax_query[] = ['taxonomy'=>'jbg_cat','field'=>'term_id','terms'=>[$catId]];

            $args = [
                'post_type'      => 'jbg_ad',
                'posts_per_page' => $limit,
                'no_found_rows'  => true,
                'meta_query'     => $meta_query,
                'orderby'        => [
                    'cpv_clause' => 'DESC',               // 1) CPV
                    'br_clause'  => 'DESC',               // 2) Budget Remaining
                    'pb_clause'  => 'DESC',               // 3) Priority Boost (اختیاری)
                    'date'       => 'DESC',               // 4) تساوی کامل → جدیدتر جلوتر
                ],
            ];
            if ($tax_query) $args['tax_query'] = $tax_query;

            $q = new \WP_Query($args);

            $items = [];
            foreach ($q->posts as $p) {
                $cpv   = (int) get_post_meta($p->ID, 'jbg_cpv', true);
                $br    = (int) get_post_meta($p->ID, 'jbg_budget_remaining', true);
                $boost = (int) get_post_meta($p->ID, 'jbg_priority_boost', true);

                if ($cpv <= 0 || $br < $cpv) continue;

                $items[] = [
                    'id'                => $p->ID,
                    'title'             => get_the_title($p),
                    'permalink'         => get_permalink($p),
                    'cpv'               => $cpv,
                    'budget_remaining'  => $br,
                    'priority_boost'    => $boost,
                    'thumb'             => get_the_post_thumbnail_url($p, 'medium') ?: '',
                    'cats'              => wp_get_post_terms($p->ID, 'jbg_cat', ['fields'=>'names']),
                ];
            }

            return new \WP_REST_Response([
                'count' => count($items),
                'items' => array_values($items),
            ], 200);

        } catch (\Throwable $e) {
            if (function_exists('error_log')) error_log('JBG feed fatal: '.$e->getMessage());
            return new \WP_REST_Response(['error'=>'internal_error','message'=>'Feed crashed. Check PHP error log.'], 500);
        }
    }
}
