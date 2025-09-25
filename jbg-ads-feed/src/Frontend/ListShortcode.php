<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

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
        $a = shortcode_atts([
            'limit' => 12,
            'title' => '',
        ], $atts, 'jbg_ads');

        $args = [
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'posts_per_page'      => max(1, (int)$a['limit']),
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'suppress_filters'    => true,
            // مهم: هیچ meta_key/orderby روی DB نگذار—مرتب‌سازی را در PHP انجام می‌دهیم
            'orderby'             => ['date' => 'ASC', 'ID' => 'ASC'],
            'lang'                => 'all',
        ];

        $q = new \WP_Query($args);

        if (!$q->have_posts()) {
            if (current_user_can('manage_options')) {
                echo "\n<!-- jbg_ads: empty; final args:\n".esc_html(print_r($args, true))."\n-->\n";
            }
            return '';
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
            ];
        }
        wp_reset_postdata();

        // مرتب‌سازی نهایی: CPV ↓ → BR ↓ → Boost ↓
        usort($items, function($a,$b){
            if ($a['cpv'] !== $b['cpv'])   return ($b['cpv']   <=> $a['cpv']);
            if ($a['br']  !== $b['br'])    return ($b['br']    <=> $a['br']);
            return ($b['boost'] <=> $a['boost']);
        });

        $user_id = get_current_user_id();

        ob_start();

        // CSS سبک برای قفل
        static $css_once = false;
        if (!$css_once) {
            $css_once = true;
            echo '<style id="jbg-ads-cards-css">
            .jbg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;direction:rtl}
            .jbg-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 6px 16px rgba(0,0,0,.04)}
            .jbg-card-thumb{display:block;width:100%;padding-top:56%;background:#f3f4f6;background-size:cover;background-position:center}
            .jbg-card-body{padding:12px 14px}
            .jbg-card .meta{color:#6b7280;font-size:12px;margin-top:4px}
            .jbg-btn{display:inline-block;background:#2563eb;color:#fff;border-radius:10px;padding:8px 14px;font-weight:600}
            .jbg-card.is-locked{opacity:.55}
            .jbg-card.is-locked .jbg-btn{background:#9ca3af;pointer-events:none}
            .jbg-lock-badge{display:inline-block;background:#fef3c7;color:#92400e;border-radius:9999px;padding:2px 8px;font-size:11px;margin-left:6px}
            </style>';
        }

        if (!empty($a['title'])) {
            echo '<div class="jbg-related-title" style="margin:8px 0 12px">'.esc_html($a['title']).'</div>';
        }

        echo '<div class="jbg-grid">';
        foreach ($items as $it) {
            $open   = Access::is_unlocked($user_id, (int)$it['ID']);
            $href   = $open ? esc_url($it['link']) : '#';
            $cardCl = $open ? 'jbg-card' : 'jbg-card is-locked';

            $views  = method_exists('\\JBG\\Ads\\Frontend\\Helpers','views_count')
                        ? \JBG\Ads\Frontend\Helpers::views_count((int)$it['ID']) : 0;
            $brand  = self::brand_name((int)$it['ID']);
            $when   = self::relative_time((int)$it['ID']);

            echo '<div class="'.$cardCl.'">';
            echo   '<a class="jbg-card-thumb" href="'.$href.'"'
                 . ($it['thumb'] ? ' style="background-image:url(\''.esc_url($it['thumb']).'\')"' : '')
                 . ($open ? '' : ' aria-disabled="true" tabindex="-1"')
                 . '></a>';
            echo   '<div class="jbg-card-body">';
            echo     '<div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">';
            if (!$open) echo '<span class="jbg-lock-badge">قفل</span>';
            echo       '<a href="'.$href.'" '.($open?'': 'aria-disabled="true" tabindex="-1"').'>'.esc_html($it['title']).'</a>';
            echo     '</div>';
            echo     '<div class="meta">';
            if ($brand) echo '<span>'.$brand.'</span><span style="padding:0 6px">•</span>';
            echo       '<span>'.esc_html(self::compact_num((int)$views)).' بازدید</span><span style="padding:0 6px">•</span><span>'.esc_html($when).'</span>';
            echo     '</div>';
            echo     '<div style="margin-top:10px"><a class="jbg-btn" href="'.$href.'" '.($open?'': 'aria-disabled="true" tabindex="-1"').'>مشاهده</a></div>';
            echo   '</div>';
            echo '</div>';
        }
        echo '</div>';

        if (current_user_can('manage_options')) {
            echo "\n<!-- jbg_ads: rendered ".count($items)." cards; gating by Access::is_unlocked enabled. -->\n";
        }

        return (string) ob_get_clean();
    }
}
