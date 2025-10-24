<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class ListShortcode {

    public static function register(): void {
        add_shortcode('jbg_ads', [self::class, 'render']);
    }

    /* ---------- utils ---------- */

    private static function compact_num(int $n): string {
        if ($n >= 1000000000) { $num=$n/1000000000; $u=' میلیارد'; }
        elseif ($n >= 1000000){ $num=$n/1000000;    $u=' میلیون'; }
        elseif ($n >= 1000)   { $num=$n/1000;       $u=' هزار'; }
        else return number_format_i18n($n);
        $s = number_format_i18n($num,1);
        $s = preg_replace('/([0-9۰-۹]+)[\.\,٫]0$/u', '$1', $s);
        return $s.$u;
    }

    private static function relative_time(int $post_id): string {
        return trim(human_time_diff(get_the_time('U',$post_id), current_time('timestamp'))).' پیش';
    }

    private static function brand_name(int $post_id): string {
        $names = wp_get_post_terms($post_id, 'jbg_brand', ['fields'=>'names']);
        return (!is_wp_error($names) && !empty($names)) ? (string) $names[0] : '';
    }

    /* ---------- query with robust fallbacks ---------- */

    private static function fetch_posts(array $atts): array {
        $limit = max(1, (int)($atts['limit'] ?? 12));

        $base = [
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'fields'              => 'all',
        ];

        if (!empty($atts['brand'])) {
            $base['tax_query'][] = [
                'taxonomy' => 'jbg_brand',
                'field'    => is_numeric($atts['brand']) ? 'term_id' : 'slug',
                'terms'    => is_numeric($atts['brand']) ? (int)$atts['brand'] : sanitize_title($atts['brand']),
            ];
        }
        if (!empty($atts['cat'])) {
            $base['tax_query'][] = [
                'taxonomy' => 'jbg_cat',
                'field'    => is_numeric($atts['cat']) ? 'term_id' : 'slug',
                'terms'    => is_numeric($atts['cat']) ? (int)$atts['cat'] : sanitize_title($atts['cat']),
            ];
        }

        $args1 = $base + ['suppress_filters' => true,  'lang' => 'all'];
        $q = new \WP_Query($args1);

        if (!$q->have_posts()) {
            $args2 = $base + ['suppress_filters' => false, 'lang' => 'all'];
            $q = new \WP_Query($args2);
            if ($q->have_posts()) {
                $base = $args2;
            }
        }

        if (!$q->have_posts()) {
            $args3 = $base;
            unset($args3['lang']);
            $q = new \WP_Query($args3);
            if ($q->have_posts()) {
                $base = $args3;
            }
        }

        $posts = $q->posts ?: [];
        wp_reset_postdata();

        if (empty($posts) && current_user_can('manage_options')) {
            echo "\n<!-- jbg_ads: EMPTY after multi-fallback; final args:\n"
               . esc_html(print_r($base,true)) . "\n-->\n";
        }

        return $posts;
    }

    /* ---------- render ---------- */

    public static function render($atts = []): string {
        $a = shortcode_atts([
            'limit'   => 12,
            'title'   => '',
            'orderby' => '',
            'cat'     => '',  // ✅ اضافه شد
            'brand'   => '',  // ✅ اضافه شد
        ], $atts, 'jbg_ads');

        $posts = self::fetch_posts($a);
        if (empty($posts)) return '';

        $orderby       = strtolower(trim((string)$a['orderby']));
        $sort_by_views = ($orderby === 'views');
        $sort_by_date  = ($orderby === 'date');

        if ($sort_by_date) {
            usort($posts, function($pA, $pB){
                $tA = get_post_time('U', true, $pA);
                $tB = get_post_time('U', true, $pB);
                return $tB <=> $tA;
            });
        }

        if ($sort_by_views) {
            usort($posts, function($pA, $pB){
                $va = (int) Helpers::views_count((int)$pA->ID);
                $vb = (int) Helpers::views_count((int)$pB->ID);
                return $vb <=> $va;
            });
        }

        $items = [];
        foreach ($posts as $p) {
            $items[] = [
                'ID'        => (int) $p->ID,
                'title'     => get_the_title($p),
                'link'      => get_permalink($p),
                'thumb'     => get_the_post_thumbnail_url($p->ID, 'medium') ?: '',
                'cpv'       => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'        => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost'     => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'likes'     => (int) get_post_meta($p->ID, 'jbg_like_count', true),
                'dislikes'  => (int) get_post_meta($p->ID, 'jbg_dislike_count', true),
                'seq'       => Access::seq((int)$p->ID),
            ];
        }

        if (!$sort_by_views && !$sort_by_date) {
            usort($items, function($a,$b){
                if ($a['cpv'] !== $b['cpv'])   return ($b['cpv']   <=> $a['cpv']);
                if ($a['br']  !== $b['br'])    return ($b['br']    <=> $a['br']);
                return ($b['boost'] <=> $a['boost']);
            });
        }

        $user_id = get_current_user_id();

        static $css_once = false;
        ob_start();
        if (!$css_once) {
            $css_once = true;
            echo '<style id="jbg-ads-list-css">
                .jbg-ads-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;direction:rtl}
                .jbg-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;display:flex;flex-direction:column}
                .jbg-card-thumb{background:#f3f4f6;height:150px;background-size:cover;background-position:center}
                .jbg-card-body{padding:12px 14px}
                .jbg-card-title{font-weight:700;margin:0}
                .jbg-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
                .jbg-card-sub{color:#6b7280;font-size:12px;margin-bottom:10px}
                .jbg-badges{display:flex;gap:6px;align-items:center;margin-bottom:6px}
                .jbg-badge{border-radius:9999px;padding:2px 8px;font-size:11px;border:1px solid #e5e7eb;background:#f9fafb}
                .jbg-badge.lock{color:#be185d;background:#fff1f2;border-color:#fecdd3}
                .jbg-badge.watched{color:#166534;background:#dcfce7;border-color:#bbf7d0}
                .jbg-card-actions{margin-top:auto;padding:0 14px 14px}
                .jbg-btn{display:inline-block;background:rgb(1,111,135);color:#fff;border-radius:9999px;padding:9px 16px;font-size:14px;text-align:center;transition:filter .15s}
                .jbg-btn:hover{filter:brightness(0.95)}
                .jbg-btn[disabled]{opacity:.5;cursor:not-allowed}
                .jbg-card a,.jbg-card a:visited,.jbg-card a:hover,.jbg-card a:focus{text-decoration:none!important}
            </style>';
        }

        echo '<div class="jbg-ads-grid">';

        foreach ($items as $it) {
            $id     = (int)$it['ID'];
            $open   = Access::is_unlocked($user_id, $id);
            $watched= ($user_id>0) ? Access::has_passed($user_id,$id) : false;

            $views  = Helpers::views_count($id);
            $viewsF = self::compact_num((int)$views).' بازدید';
            $when   = self::relative_time($id);
            $brand  = self::brand_name($id);

            echo '<div class="jbg-card">';

            $thumbStyle = $it['thumb'] ? ' style="background-image:url(\''.esc_url($it['thumb']).'\')"' : '';
            echo '<div class="jbg-card-thumb"'.$thumbStyle.'></div>';

            echo '<div class="jbg-card-body">';

            echo '<div class="jbg-badges">';
            if ($watched) {
                echo '<span class="jbg-badge watched">دیده‌شده</span>';
            } elseif (!$open) {
                echo '<span class="jbg-badge lock">قفل</span>';
            }
            echo '</div>';

            echo '<div class="jbg-card-top">';
            echo '<div class="jbg-card-title">'.esc_html($it['title']).'</div>';
            echo '</div>';

            echo '<div class="jbg-card-sub">';
            if ($brand) echo '<span>'.esc_html($brand).'</span><span> • </span>';
            echo '<span>'.esc_html($when).'</span><span> • </span><span>'.esc_html($viewsF).'</span>';
            echo '</div>';

            echo '</div>'; // body

            echo '<div class="jbg-card-actions">';
            if ($open) {
                echo '<a class="jbg-btn" href="'.esc_url($it['link']).'">مشاهده</a>';
            } else {
                echo '<a class="jbg-btn" href="'.esc_url($it['link']).'" aria-disabled="true">مشاهده</a>';
            }
            echo '</div>';

            echo '</div>'; // card
        }

        echo '</div>'; // grid

        return ob_get_clean();
    }
}
