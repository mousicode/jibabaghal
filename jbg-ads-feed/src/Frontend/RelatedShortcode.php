<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class RelatedShortcode {

    public static function register(): void {
        add_shortcode('jbg_related', [self::class, 'render']);
    }

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

    public static function render($atts = []): string {
        $a = shortcode_atts([
            'limit' => 8,
            'title' => 'ویدیوهای مرتبط',
        ], $atts, 'jbg_related');

        $current_id = is_singular('jbg_ad') ? get_the_ID() : 0;
        $tax_query = [];
        if ($current_id) {
            $terms = wp_get_post_terms($current_id, 'jbg_cat', ['fields'=>'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $tax_query[] = [
                    'taxonomy' => 'jbg_cat',
                    'field'    => 'term_id',
                    'terms'    => array_map('intval', $terms),
                ];
            }
        }

        // Query 1: فقط با CPV
        $args = [
            'post_type'      => 'jbg_ad',
            'posts_per_page' => max(1, (int)$a['limit']),
            'no_found_rows'  => true,
            'post__not_in'   => $current_id ? [$current_id] : [],
            'meta_query'     => [
                ['key'=>'jbg_cpv', 'compare'=>'EXISTS'],
            ],
            'orderby'        => ['meta_value_num' => 'ASC', 'date' => 'ASC'],
            'meta_key'       => 'jbg_seq',
        ];
        if ($tax_query) $args['tax_query'] = $tax_query;

        $q = new \WP_Query($args);

        // Fallback: اگر نتیجه صفر بود، بدون meta_query
        if (!$q->have_posts()) {
            unset($args['meta_query']);
            $q = new \WP_Query($args);
        }

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => $p->ID,
                'title' => get_the_title($p),
                'link'  => get_permalink($p),
                'thumb' => get_the_post_thumbnail_url($p->ID, 'medium') ?: '',
                'cpv'   => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'seq'   => Access::seq($p->ID),
            ];
        }
        wp_reset_postdata();

        usort($items, function($a, $b){
            if ($a['seq'] !== $b['seq']) return ($a['seq'] <=> $b['seq']);
            if ($a['cpv'] === $b['cpv']) {
                if ($a['br'] === $b['br']) return ($b['boost'] <=> $a['boost']);
                return ($b['br'] <=> $a['br']);
            }
            return ($b['cpv'] <=> $a['cpv']);
        });

        $user_id = get_current_user_id();

        ob_start();
        echo '<div class="jbg-related">';
        echo '<div class="jbg-related-title">'.esc_html($a['title']).'</div>';
        echo '<div class="jbg-related-list">';
        foreach ($items as $it) {
            $views  = Helpers::views_count((int)$it['ID']);
            $viewsF = self::compact_num($views) . ' بازدید';
            $when   = self::relative_time((int)$it['ID']);
            $brand  = self::brand_name((int)$it['ID']);
            $open   = Access::is_unlocked($user_id, (int)$it['ID']);

            $href = $open ? esc_url($it['link']) : '#';
            $lock = $open ? '' : ' style="opacity:.6;pointer-events:none"';

            echo '<a class="jbg-related-item" href="'.$href.'"'.$lock.'>';
            echo   '<span class="jbg-related-thumb"'.($it['thumb']?' style="background-image:url(\''.esc_url($it['thumb']).'\')"':'').'></span>';
            echo   '<span class="jbg-related-meta">';
            echo     '<span class="jbg-related-title-text">'.esc_html($it['title']).'</span>';
            echo     '<span class="jbg-related-sub">';
            if ($brand) echo '<span class="brand">'.esc_html($brand).'</span><span class="dot">•</span>';
            echo       '<span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span>';
            echo     '</span>';
            echo   '</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';
        return (string) ob_get_clean();
    }
}
