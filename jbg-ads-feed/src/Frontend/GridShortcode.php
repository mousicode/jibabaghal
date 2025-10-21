<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * [jbg_ads_grid]
 * نمایش کارت‌های ویدیو در شبکهٔ 4 ستونه، مرتب‌شده بر اساس جدیدترین
 * این کلاس هیچ فیلتر/هوک سراسری روی WP_Query اعمال نمی‌کند.
 */
class GridShortcode
{
    public static function register(): void
    {
        add_shortcode('jbg_ads_grid', [self::class, 'render']);
    }

    public static function render($atts = []): string
    {
        $a = shortcode_atts([
            'cols'      => 4,      // تعداد ستون‌ها در دسکتاپ
            'per_page'  => 12,     // تعداد آیتم‌ها
            'brand'     => '',     // اسلاگ برند اختیاری
            'category'  => '',     // اسلاگ دسته اختیاری
        ], $atts, 'jbg_ads_grid');

        $cols     = max(1, min(6, (int) $a['cols']));
        $per_page = max(1, min(48, (int) $a['per_page']));

        // کوئری مجزا فقط برای این شورت‌کد؛ ترتیب: جدیدترین
        $args = [
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'orderby'             => 'date',
            'order'               => 'DESC',
            'posts_per_page'      => $per_page,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ];

        // فیلترهای اختیاری
        $tax_query = [];
        if (!empty($a['brand'])) {
            $tax_query[] = [
                'taxonomy' => 'jbg_brand',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_title', explode(',', (string) $a['brand'])),
            ];
        }
        if (!empty($a['category'])) {
            $tax_query[] = [
                'taxonomy' => 'jbg_cat',
                'field'    => 'slug',
                'terms'    => array_map('sanitize_title', explode(',', (string) $a['category'])),
            ];
        }
        if ($tax_query) $args['tax_query'] = $tax_query;

        $q = new \WP_Query($args);
        if (!$q->have_posts()) return '';

        // استایل سبک، یک بار
        $style = '';
        static $printed = false;
        if (!$printed) {
            $style = '<style id="jbg-ads-grid-css">
                .jbg-ads-grid{display:grid;gap:16px}
                @media(min-width:640px){.jbg-ads-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
                @media(min-width:992px){.jbg-ads-grid{grid-template-columns:repeat(VAR_COLS,minmax(0,1fr))}}
                .jbg-card{border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;background:#fff;display:flex;flex-direction:column}
                .jbg-card .thumb{aspect-ratio:16/9;background:#f3f4f6;display:block;overflow:hidden}
                .jbg-card .thumb img{width:100%;height:100%;object-fit:cover;display:block}
                .jbg-card .body{padding:12px 14px;display:flex;flex-direction:column;gap:6px}
                .jbg-card .title{margin:0;font-size:15px;line-height:1.5;font-weight:700;color:#111827}
                .jbg-card .meta{font-size:12.5px;color:#6b7280;display:flex;gap:8px;flex-wrap:wrap}
                .jbg-card .brand{font-size:12px;background:#f1f5f9;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;color:#111827}
            </style>';
            $printed = true;
        }

        // جایگزینی تعداد ستون‌های دسکتاپ در CSS
        $style = str_replace('VAR_COLS', (string)$cols, $style);

        ob_start();
        echo $style;
        echo '<div class="jbg-ads-grid">';

        while ($q->have_posts()) {
            $q->the_post();
            $pid   = get_the_ID();
            $link  = get_permalink($pid);
            $title = esc_html(get_the_title($pid));

            // تصویر شاخص
            $thumb = get_the_post_thumbnail($pid, 'medium', ['alt' => $title, 'loading' => 'lazy']);
            if (empty($thumb)) {
                $thumb = '<img src="'.esc_url(includes_url('images/media/default.png')).'" alt="" loading="lazy" />';
            }

            // برند
            $brand_names = wp_get_post_terms($pid, 'jbg_brand', ['fields'=>'names']);
            $brand_html  = (!is_wp_error($brand_names) && !empty($brand_names))
                ? '<span class="brand">'.esc_html($brand_names[0]).'</span>' : '';

            // متا: تاریخ نسبی و بازدید
            $when  = human_time_diff(get_post_time('U', true, $pid), current_time('timestamp')) . ' پیش';
            $views = (int) get_post_meta($pid, 'jbg_views_count', true);
            $meta  = '<span>'.number_format_i18n($views).' بازدید</span><span>•</span><span>'.$when.'</span>';

            echo '<article class="jbg-card">';
            echo '  <a class="thumb" href="'.esc_url($link).'">'.$thumb.'</a>';
            echo '  <div class="body">';
            echo '    <h3 class="title"><a href="'.esc_url($link).'">'.$title.'</a></h3>';
            echo '    <div class="meta">'.$meta.'</div>';
            if ($brand_html) echo '    '.$brand_html;
            echo '  </div>';
            echo '</article>';
        }
        echo '</div>';
        \wp_reset_postdata();

        return ob_get_clean();
    }
}
