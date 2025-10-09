<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class SponsorReportShortcode
{
    public static function register(): void
    {
        add_shortcode('jbg_sponsor_report', [self::class, 'render']);
    }

    public static function render($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="jbg-card">برای مشاهدهٔ گزارش وارد شوید.</div>';
        }
        if (!current_user_can('jbg_view_reports')) {
            return '<div class="jbg-card">شما دسترسی لازم برای مشاهدهٔ گزارش را ندارید.</div>';
        }

        $user_id = get_current_user_id();
        $brand_ids = get_user_meta($user_id, 'jbg_sponsor_brand_ids', true);
        if (!is_array($brand_ids) || empty($brand_ids)) {
            return '<div class="jbg-card">هیچ برندی برای شما تنظیم نشده است. لطفاً با ادمین هماهنگ کنید.</div>';
        }

        $q = new \WP_Query([
            'post_type'      => 'jbg_ad',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'tax_query'      => [[
                'taxonomy' => 'jbg_brand',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $brand_ids),
            ]],
        ]);

        $ad_ids = array_map('intval', (array) $q->posts);
        if (empty($ad_ids)) {
            return '<div class="jbg-card">ویدئویی برای برند(های) شما یافت نشد.</div>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jbg_views';
        $in = implode(',', array_map('intval', $ad_ids));
        $rows = $wpdb->get_results("SELECT ad_id, COUNT(*) AS views, COALESCE(SUM(amount),0) AS spend
                                    FROM {$table}
                                    WHERE ad_id IN ($in)
                                    GROUP BY ad_id", ARRAY_A);

        $agg = [];
        foreach ((array)$rows as $r) {
            $aid = (int)$r['ad_id'];
            $agg[$aid] = ['views'=>(int)$r['views'], 'spend'=>(int)$r['spend']];
        }

        $fmt = function(int $n): string { return number_format_i18n($n); };

        ob_start();
        echo '<style>
            .jbg-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:12px 0}
            .jbg-title{font-weight:800;margin:0 0 10px 0}
            .jbg-table{width:100%;border-collapse:collapse}
            .jbg-table th,.jbg-table td{padding:10px;border-bottom:1px solid #f3f4f6;text-align:right}
            .jbg-table th{background:#f9fafb;font-weight:700}
            .muted{color:#6b7280}
            .toman::after{content:" تومان"}
            .total{font-weight:700;background:#fcfcfc}
        </style>';
        echo '<div class="jbg-card" style="direction:rtl">';
        echo '<div class="jbg-title">گزارش اسپانسر</div>';
        echo '<table class="jbg-table"><thead><tr><th>ویدیو</th><th>برند</th><th>CPV</th><th>بازدید</th><th>هزینهٔ کل</th></tr></thead><tbody>';

        $grand_views = 0; $grand_spend = 0;
        foreach ($ad_ids as $aid) {
            $ttl   = get_the_title($aid);
            $link  = get_permalink($aid);
            $cpv   = (int) get_post_meta($aid, 'jbg_cpv', true);
            $brand_names = wp_get_post_terms($aid, 'jbg_brand', ['fields'=>'names']);
            if (is_wp_error($brand_names)) $brand_names = [];

            $views = $agg[$aid]['views'] ?? 0;
            $spend = $agg[$aid]['spend'] ?? 0;

            if ($views === 0) {
                $views_meta = (int) get_post_meta($aid, 'jbg_views_count', true);
                $views = max($views, $views_meta);
            }

            $grand_views += $views;
            $grand_spend += $spend;

            echo '<tr>';
            echo   '<td><a href="'.esc_url($link).'" target="_blank" rel="noopener">'.esc_html($ttl).'</a></td>';
            echo   '<td class="muted">'.esc_html(implode('، ', $brand_names)).'</td>';
            echo   '<td class="toman">'.esc_html($fmt($cpv)).'</td>';
            echo   '<td>'.esc_html($fmt($views)).'</td>';
            echo   '<td class="toman">'.esc_html($fmt($spend)).'</td>';
            echo '</tr>';
        }
        echo '<tr class="total"><td>مجموع</td><td></td><td></td><td>'.esc_html($fmt($grand_views)).'</td><td class="toman">'.esc_html($fmt($grand_spend)).'</td></tr>';
        echo '</tbody></table>';
        echo '<p class="muted" style="margin-top:8px">مبنای هزینه، مجموع <code>amount</code> در لاگ بیلینگ است.</p>';
        echo '</div>';
        return (string) ob_get_clean();
    }
}
