<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class ListShortcode {

    public static function register(): void {
        add_shortcode('jbg_ads', [self::class, 'render']);
    }

    /* ---------- helpers ---------- */

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

    /**
     * تعداد بازدید:
     * 1) ابتدا از متای جدید jbg_views_total می‌خوانیم
     * 2) اگر نبود/صفر بود، از جدول لاگ‌ها جمع می‌زنیم
     * 3) سپس هر دو متای jbg_views_total و jbg_views_count را با هم همگام می‌کنیم
     */
    private static function views_count(int $ad_id): int {
        $ad_id = absint($ad_id);
        if ($ad_id <= 0) return 0;

        // متای جدید
        $v = (int) get_post_meta($ad_id, 'jbg_views_total', true);
        if ($v > 0) return $v;

        global $wpdb;
        $table = $wpdb->prefix . 'jbg_views';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return 0;

        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ad_id = %d", $ad_id));

        // سینک هر دو کلید برای سازگاری
        update_post_meta($ad_id, 'jbg_views_total', $count);
        update_post_meta($ad_id, 'jbg_views_count', $count);
        wp_cache_delete($ad_id, 'post_meta');

        return $count;
    }

    /* ---------- shortcode ---------- */

    public static function render($atts = []): string {
        // اطمینان از لود استایل یک‌بار
        if (!wp_style_is('jbg-list', 'enqueued')) {
            $css = plugins_url('../../assets/css/jbg-list.css', __FILE__);
            wp_enqueue_style('jbg-list', $css, [], '0.1.3');
        }

        $a = shortcode_atts([
            'limit'    => 12,
            'brand'    => '',     // اسلاگ‌های برند با کاما
            'category' => '',     // اگر taxonomy دیگری ساختی
            'class'    => '',
        ], $atts, 'jbg_ads');

        $args = [
            'post_type'      => 'jbg_ad',
            'posts_per_page' => max(1, (int)$a['limit']),
            'no_found_rows'  => true,
            'meta_query'     => [
                ['key'=>'jbg_cpv', 'compare'=>'EXISTS'],
            ],
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
        // فیلتر دسته‌بندی سفارشی (اگر jbg_category داری)
        if (taxonomy_exists('jbg_category') && !empty($a['category'])) {
            $cats = array_filter(array_map('sanitize_title', array_map('trim', explode(',', $a['category']))));
            if ($cats) {
                $args['tax_query'][] = [
                    'taxonomy' => 'jbg_category',
                    'field'    => 'slug',
                    'terms'    => $cats,
                ];
            }
        }

        $q = new \WP_Query($args);
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
            ];
        }
        wp_reset_postdata();

        // مرتب‌سازی: اول CPV نزولی، بعد بودجهٔ باقی‌مانده نزولی، بعد Boost
        usort($items, function($a, $b){
            if ($a['cpv'] === $b['cpv']) {
                if ($a['br'] === $b['br']) return ($b['boost'] <=> $a['boost']);
                return ($b['br'] <=> $a['br']);
            }
            return ($b['cpv'] <=> $a['cpv']);
        });

        // رندر کارت‌ها
        ob_start();
        echo '<div class="jbg-grid '.esc_attr($a['class']).'">';
        foreach ($items as $it) {
            $views  = self::views_count((int)$it['ID']);
            $viewsF = self::compact_num($views) . ' بازدید';
            $when   = self::relative_time((int)$it['ID']);
            $brand  = self::brand_name((int)$it['ID']);

            echo '<div class="jbg-card">';
            echo   '<a class="jbg-thumb" href="'.esc_url($it['link']).'"'.($it['thumb']?' style="background-image:url(\''.esc_url($it['thumb']).'\')"':'').'></a>';
            echo   '<div class="jbg-card-body">';
            echo     '<div class="jbg-card-title">'.esc_html($it['title']).'</div>';
            echo     '<div class="jbg-card-meta">';
            if ($brand) echo   '<span class="brand">'.esc_html($brand).'</span><span class="dot">•</span>';
            echo       '<span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span>';
            echo     '</div>';
            echo     '<a class="jbg-btn jbg-card-btn" href="'.esc_url($it['link']).'">مشاهده</a>';
            echo   '</div>';
            echo '</div>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}
