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
        else { return (string) $n; }
        $num = floor($num*10)/10;
        return rtrim(rtrim(number_format($num,1,'.',''), '0'), '.') . $u;
    }

    private static function relative_time(int $post_id): string {
        $t = get_post_time('U', true, $post_id);
        $d = time() - (int)$t;
        if ($d < 60)        return 'لحظاتی پیش';
        if ($d < 3600)      return floor($d/60) . ' دقیقه پیش';
        if ($d < 86400)     return floor($d/3600) . ' ساعت پیش';
        if ($d < 86400*30)  return floor($d/86400) . ' روز پیش';
        return get_the_date('', $post_id);
    }

    private static function brand_name(int $post_id): string {
        $terms = wp_get_post_terms($post_id, 'jbg_brand', ['fields'=>'names']);
        $names = (!is_wp_error($terms)) ? $terms : [];
        return (!empty($names)) ? (string) $names[0] : '';
    }

    public static function render($atts = []): string {
        // استایل لیست + full-bleed فقط یک‌بار
        if (!wp_style_is('jbg-list', 'enqueued')) {
            $css = plugins_url('../../assets/css/jbg-list.css', __FILE__);
            wp_enqueue_style('jbg-list', $css, [], '0.1.4');
        }

        static $once_css = false;
        $css_inline = '';
        if (!$once_css) {
            $once_css = true;
            $css_inline = '<style id="jbg-list-fw">
              .jbg-full-bleed{position:relative;left:50%;right:50%;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);width:100vw}
              .jbg-container{max-width:1312px;margin:0 auto;padding-left:16px;padding-right:16px;box-sizing:border-box}
            </style>';
        }

        $a = shortcode_atts([
            'limit'    => 12,
            'brand'    => '',     // اسلاگ برندها با کاما
            'category' => '',
            'class'    => '',
        ], $atts, 'jbg_ads');

        $args = [
            'post_type'      => 'jbg_ad',
            'posts_per_page' => max(1, (int)$a['limit']),
            'post_status'    => 'publish',
            'meta_query'     => [
                ['key'=>'jbg_cpv', 'compare'=>'EXISTS'],
            ],
            'orderby'        => ['meta_value_num' => 'ASC', 'date' => 'ASC'],
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
        // فیلتر دسته سفارشی (در صورت وجود)
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
        while ($q->have_posts()) {
            $q->the_post();
            $thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            $items[] = [
                'ID'     => get_the_ID(),
                'title'  => get_the_title(),
                'link'   => get_permalink(),
                'thumb'  => $thumb ?: '',
                'cpv'    => (int) get_post_meta(get_the_ID(), 'jbg_cpv', true),
                'br'     => (int) get_post_meta(get_the_ID(), 'jbg_budget_remaining', true),
                'boost'  => (int) get_post_meta(get_the_ID(), 'jbg_priority_boost', true),
                'seq'    => Access::seq(get_the_ID()),
            ];
        }
        wp_reset_postdata();

        // مرتب‌سازی نهایی (با حفظ رفتار قبلی)
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
        echo $css_inline;
        echo '<div class="jbg-full-bleed"><div class="jbg-container">';
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
        echo '</div>'; // .jbg-grid
        echo '</div></div>'; // .jbg-container .jbg-full-bleed
        return (string) ob_get_clean();
    }
}
