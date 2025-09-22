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

    /** اسلاگ‌های معتبر یک taxonomy را برمی‌گرداند */
    private static function valid_slugs(string $tax, array $slugs): array {
        if (!taxonomy_exists($tax)) return [];
        $slugs = array_filter(array_map('sanitize_title', array_map('trim', $slugs)));
        if (!$slugs) return [];
        $terms = get_terms(['taxonomy'=>$tax, 'hide_empty'=>false, 'slug'=>$slugs]);
        if (is_wp_error($terms) || empty($terms)) return [];
        return array_map(fn($t)=>$t->slug, $terms);
    }

    public static function render($atts = []): string {
        if (!wp_style_is('jbg-list', 'enqueued')) {
            $css = plugins_url('../../assets/css/jbg-list.css', __FILE__);
            wp_enqueue_style('jbg-list', $css, [], '0.2.0');
        }

        $a = shortcode_atts([
            'limit'    => 12,
            'brand'    => '',
            'category' => '',
            'class'    => '',
        ], $atts, 'jbg_ads');

        // فیلترها (فقط اسلاگ معتبر)
        $brand_slugs = $a['brand']    ? self::valid_slugs('jbg_brand', array_map('trim', explode(',', $a['brand']))) : [];
        $cat_slugs   = $a['category'] ? self::valid_slugs('jbg_cat',   array_map('trim', explode(',', $a['category']))) : [];

        // پایهٔ کوئری — بدون meta_key/meta_value (تا INNER JOIN نخورد)
        $base = [
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'posts_per_page'      => max(1, (int)$a['limit']),
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'suppress_filters'    => true,
            'orderby'             => ['menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC'],
        ];
        // چندزبانه
        $base['lang'] = 'all';

        // --- مرحله 1: با CPV + tax
        $args = $base;
        $args['meta_query'] = [['key'=>'jbg_cpv','compare'=>'EXISTS']];
        if ($brand_slugs) $args['tax_query'][] = ['taxonomy'=>'jbg_brand','field'=>'slug','terms'=>$brand_slugs];
        if ($cat_slugs)   $args['tax_query'][] = ['taxonomy'=>'jbg_cat','field'=>'slug','terms'=>$cat_slugs];
        $q = new \WP_Query($args);

        // --- مرحله 2: حذف شرط CPV
        if (!$q->have_posts()) {
            unset($args['meta_query']);
            $q = new \WP_Query($args);
        }

        // --- مرحله 3: حذف فیلترهای tax
        if (!$q->have_posts() && !empty($args['tax_query'])) {
            unset($args['tax_query']);
            $q = new \WP_Query($args);
        }

        // --- مرحله 4: اجازهٔ فیلترها (برای سازگاری با بعضی افزونه‌ها)
        if (!$q->have_posts()) {
            $args4 = $args; $args4['suppress_filters'] = false;
            $q = new \WP_Query($args4);
            if ($q->have_posts()) $args = $args4;
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
                'seq'    => Access::seq($p->ID), // مرتب‌سازی صحیح در PHP
            ];
        }
        wp_reset_postdata();

        if (empty($items)) {
            if (current_user_can('manage_options')) {
                global $wpdb; $db_count = (int)$wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish'", 'jbg_ad')
                );
                $sql = isset($q) && isset($q->request) ? $q->request : '(no-sql)';
                echo "\n<!-- jbg_ads: EMPTY after 4-stage fallback.\n"
                   . "post_type_exists(jbg_ad)=".(post_type_exists('jbg_ad')?'yes':'NO')."\n"
                   . "db_count(publish jbg_ad)={$db_count}\n"
                   . "final args:\n".esc_html(print_r($args, true))."\n"
                   . "sql:\n".esc_html($sql)."\n-->\n";
            }
            return '';
        }

        // مرتب‌سازی نهایی با seq
        usort($items, function($a,$b){
    if ($a['cpv'] !== $b['cpv'])   return ($b['cpv']   <=> $a['cpv']);
    if ($a['br']  !== $b['br'])    return ($b['br']    <=> $a['br']);
    return ($b['boost'] <=> $a['boost']);
});


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

        if (current_user_can('manage_options')) {
            echo "\n<!-- jbg_ads: rendered ".count($items)." items. -->\n";
        }

        return (string) ob_get_clean();
    }
}
