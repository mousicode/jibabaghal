<?php
namespace JBG\Ads\Frontend;

use JBG\Ads\Progress\Access;

if (!defined('ABSPATH')) exit;

class ListShortcode {

    public static function register(): void {
        add_shortcode('jbg_ads', [self::class, 'render']);
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
        if (!wp_style_is('jbg-list', 'enqueued')) {
            $css = plugins_url('../../assets/css/jbg-list.css', __FILE__);
            wp_enqueue_style('jbg-list', $css, [], '0.1.4');
        }

        $a = shortcode_atts([
            'limit'    => 12,
            'brand'    => '',
            'category' => '',
            'class'    => '',
        ], $atts, 'jbg_ads');

        // --- Query (با CPV، ترتیب بر اساس seq سپس تاریخ)
        $args = [
            'post_type'      => 'jbg_ad',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, (int)$a['limit']),
            'no_found_rows'  => true,
            'meta_query'     => [
                ['key'=>'jbg_cpv', 'compare'=>'EXISTS'],
            ],
            'orderby'        => [
                'meta_value_num' => 'ASC',
                'date'           => 'ASC',
            ],
            'meta_key'       => 'jbg_seq',
        ];

        // فیلتر برند
        if (!empty($a['brand'])) {
            $brands = array_filter(array_map('sanitize_title', array_map('trim', explode(',', $a['brand']))));
            if ($brands) {
                $args['tax_query'][] = [
                    'taxonomy' => 'jbg_brand',
                    'field'    => 'slug',
                    'terms'    => $brands,
                ];
            }
        }

        // ✅ فیلتر دسته‌بندی روی taxonomy درست: jbg_cat
        if (taxonomy_exists('jbg_cat') && !empty($a['category'])) {
            $cats = array_filter(array_map('sanitize_title', array_map('trim', explode(',', $a['category']))));
            if ($cats) {
                $args['tax_query'][] = [
                    'taxonomy' => 'jbg_cat',
                    'field'    => 'slug',
                    'terms'    => $cats,
                ];
            }
        }

        $q = new \WP_Query($args);

        // --- Fallback: اگر نتیجه صفر بود، شرط CPV را حذف کن تا کارت‌ها خالی نماند
        if (!$q->have_posts()) {
            unset($args['meta_query']);
            $q = new \WP_Query($args);
        }

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'     => $p->ID,
                'title'  => get_the_title($p),
                'link'   => get_permalink($p),
                'thumb'  => get_the_post_thumbnail_url($p->ID, 'large') ?: '',
                'cpv'    => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'     => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost'  => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'seq'    => Access::seq($p->ID),
            ];
        }
        wp_reset_postdata();

        // مرتب‌سازی: seq سپس CPV/BR/Boost
        usort($items, function($a, $b){
            if ($a['seq'] !== $b['seq']) return ($a['seq'] <=> $b['seq']);
            if ($a['cpv'] === $b['cpv']) {
                if ($a['br'] === $b['br']) return ($b['boost'] <=> $a['boost']);
                return ($b['br'] <=> $a['br']);
            }
            return ($b['cpv'] <=> $a['cpv']);
        });

        // اگر باز هم آیتمی نبود، خروجی خالی بده (نه باکس سفید)
        if (empty($items)) return '';

        $user_id = get_current_user_id();

        ob_start();
        echo '<div class="jbg-grid '.esc_attr($a['class']).'">';
        foreach ($items as $it) {
            $views  = Helpers::views_count((int)$it['ID']);
            $viewsF = self::compact_num($views) . ' بازدید';
            $when   = self::relative_time((int)$it['ID']);
            $brand  = self::brand_name((int)$it['ID']);
            $open   = Access::is_unlocked($user_id, (int)$it['ID']);

            $cls  = 'jbg-card' . ($open ? '' : ' locked');
            $href = $open ? esc_url($it['link']) : '#';

            echo '<div class="'.$cls.'">';
            echo   '<a class="jbg-thumb" href="'.$href.'"'.($it['thumb']?' style="background-image:url(\''.esc_url($it['thumb']).'\')"':'').'></a>';
            echo   '<div class="jbg-card-body">';
            echo     '<div class="jbg-card-title">'.esc_html($it['title']).'</div>';
            echo     '<div class="jbg-card-meta">';
            if ($brand) echo   '<span class="brand">'.esc_html($brand).'</span><span class="dot">•</span>';
            echo       '<span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span>';
            echo     '</div>';
            echo     '<a class="jbg-btn jbg-card-btn" href="'.$href.'"'.($open?'':' tabindex="-1" aria-disabled="true"').'>مشاهده</a>';
            echo   '</div>';
            echo '</div>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}
