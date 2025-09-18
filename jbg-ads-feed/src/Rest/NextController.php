<?php
namespace JBG\Ads\Rest;
if (!defined('ABSPATH')) exit;

class NextController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/next', [
            'methods'  => 'GET',
            'permission_callback' => function () { return is_user_logged_in(); },
            'args' => [
                'current' => [
                    'type' => 'integer',
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
            'callback' => [self::class, 'handle'],
        ]);
    }

    public static function handle(\WP_REST_Request $req) {
        $current_id = absint($req->get_param('current'));
        if ($current_id <= 0 || get_post_type($current_id) !== 'jbg_ad') {
            return new \WP_Error('bad_current', 'Bad current ad id', ['status' => 400]);
        }

        $uid = get_current_user_id();
        // باید ویدیوی فعلی کامل دیده + آزمون پاس شده باشد
        $watched = (bool) get_user_meta($uid, 'jbg_watched_ok_' . $current_id, true);
        $billed  = (bool) get_user_meta($uid, 'jbg_billed_'     . $current_id, true);
        if (!($watched && $billed)) {
            return new \WP_Error('not_completed', 'Current ad not completed', ['status' => 403]);
        }

        $next = self::compute_next($current_id);
        if (!$next) {
            // هیچ ویدیوی بعدی‌ای نیست
            return new \WP_REST_Response(['id' => 0, 'url' => '', 'end' => true], 200);
        }

        return new \WP_REST_Response([
            'id'  => (int) $next['ID'],
            'url' => (string) get_permalink((int)$next['ID']),
            'end' => false,
        ], 200);
    }

    /** ترتیب: CPV ↓ → budget_remaining ↓ → boost ↓ → date ↓ (در همان دسته‌های jbg_cat) */
    private static function compute_next(int $current_id): ?array {
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
            'tax_query'      => $tax_query ?: [],
            'fields'         => 'ids',
        ]);

        $items = [];
        foreach ($q->posts as $pid) {
            $items[] = [
                'ID'    => (int) $pid,
                'cpv'   => (int) get_post_meta($pid, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($pid, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($pid, 'jbg_priority_boost', true),
                'date'  => get_post_time('U', true, $pid),
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

        $idx = -1;
        foreach ($items as $i => $it) { if ($it['ID'] === $current_id) { $idx = $i; break; } }
        if ($idx < 0) return null;
        return $items[$idx+1] ?? null;
    }
}
