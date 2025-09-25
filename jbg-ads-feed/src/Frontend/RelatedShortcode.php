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

        // فیلتر دسته‌ی همان پست (اگر داشت)
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

        $base = [
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'posts_per_page'      => max(1, (int)$a['limit']),
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'suppress_filters'    => true,
            'orderby'             => ['date' => 'ASC', 'ID' => 'ASC'],
            'post__not_in'        => $current_id ? [$current_id] : [],
            'lang'                => 'all',
        ];

        // 1) ترجیحاً در همان دسته و با داشتن CPV
        $args = $base;
        if ($tax_query) $args['tax_query'] = $tax_query;
        $args['meta_query'] = [['key'=>'jbg_cpv','compare'=>'EXISTS']];
        $q = new \WP_Query($args);

        // 2) بدون CPV
        if (!$q->have_posts()) {
            unset($args['meta_query']);
            $q = new \WP_Query($args);
        }

        // 3) بدون tax
        if (!$q->have_posts() && !empty($args['tax_query'])) {
            unset($args['tax_query']);
            $q = new \WP_Query($args);
        }

        // 4) اجازهٔ فیلترها در صورت نیاز
        if (!$q->have_posts()) {
            $args4 = $args; $args4['suppress_filters'] = false;
            $q = new \WP_Query($args4);
            if ($q->have_posts()) $args = $args4;
        }

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => (int) $p->ID,
                'title' => get_the_title($p),
                'link'  => get_permalink($p),
                'thumb' => get_the_post_thumbnail_url($p->ID, 'medium') ?: '',
                'cpv'   => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'seq'   => Access::seq((int)$p->ID),
            ];
        }
        wp_reset_postdata();

        if (empty($items)) {
            return '';
        }

        // مرتب‌سازی نهایی: CPV ↓ → BR ↓ → Boost ↓
        usort($items, function($a,$b){
            if ($a['cpv'] !== $b['cpv'])   return ($b['cpv']   <=> $a['cpv']);
            if ($a['br']  !== $b['br'])    return ($b['br']    <=> $a['br']);
            return ($b['boost'] <=> $a['boost']);
        });

        $user_id     = get_current_user_id();
        $unlockedMax = $user_id ? Access::unlocked_max($user_id) : 1;

        ob_start();

        // CSS اضافه برای «دیده‌شده» و «مشاهده مجدد»
        static $css_once = false;
        if (!$css_once) {
            $css_once = true;
            echo '<style id="jbg-related-watched-css">
              .jbg-related {direction:rtl}
              .jbg-related-title {font-weight:700;margin:10px 6px}
              .jbg-related-list {display:flex;flex-direction:column;gap:10px}
              .jbg-related-item {display:flex;gap:10px;align-items:center;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px}
              .jbg-related-thumb{flex:0 0 72px;height:48px;background:#f3f4f6;background-size:cover;background-position:center;border-radius:8px}
              .jbg-related-meta{display:flex;flex-direction:column}
              .jbg-related-sub{color:#6b7280;font-size:12px;margin-top:2px}
              .jbg-related-item.is-locked{opacity:.55;pointer-events:none}
              .jbg-badge-watched{background:#DCFCE7;color:#166534;border-radius:9999px;padding:2px 8px;font-size:11px;margin-left:6px}
              .jbg-rel-actions{margin-top:6px}
              .jbg-rel-rewatch{display:inline-block;border:1px solid #e5e7eb;border-radius:10px;padding:4px 10px;font-size:12px}
            </style>';
        }

        echo '<div class="jbg-related">';
        echo '<div class="jbg-related-title">'.esc_html($a['title']).'</div>';
        echo '<div class="jbg-related-list">';

        foreach ($items as $it) {
            $views  = Helpers::views_count((int)$it['ID']);
            $viewsF = self::compact_num($views) . ' بازدید';
            $when   = self::relative_time((int)$it['ID']);
            $brand  = self::brand_name((int)$it['ID']);

            $open   = Access::is_unlocked($user_id, (int)$it['ID']);
            $watched = $user_id && ($it['seq'] < $unlockedMax); // قبلاً کامل دیده و آزمون را پاس کرده

            $href = $open ? esc_url($it['link']) : '#';
            $lockAttr = $open ? '' : ' style="opacity:.6;pointer-events:none"';

            echo '<a class="jbg-related-item'.($open?'':' is-locked').'" href="'.$href.'"'.$lockAttr.'>';
            echo   '<span class="jbg-related-thumb"'.($it['thumb']?' style="background-image:url(\''.esc_url($it['thumb']).'\')"':'').'></span>';
            echo   '<span class="jbg-related-meta">';
            echo     '<span class="jbg-related-title-text">';
            if ($watched) echo '<span class="jbg-badge-watched">دیده‌شده</span>';
            echo       esc_html($it['title']);
            echo     '</span>';
            echo     '<span class="jbg-related-sub">';
            if ($brand) echo '<span class="brand">'.esc_html($brand).'</span><span class="dot">•</span>';
            echo       '<span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span>';
            echo     '</span>';

            if ($watched) {
                // دکمه‌ی مشاهده مجدد (داخل همان لینک، فقط استایل دکمه دارد)
                echo   '<span class="jbg-rel-actions"><span class="jbg-rel-rewatch">مشاهده مجدد</span></span>';
            }

            echo   '</span>'; // meta
            echo '</a>';
        }

        echo '</div>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}
