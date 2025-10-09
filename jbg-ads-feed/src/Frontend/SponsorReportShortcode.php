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
            return '<div class="jbg-sponsor-report">برای مشاهدهٔ گزارش وارد شوید.</div>';
        }

        if (!current_user_can('jbg_view_reports')) {
            return '<div class="jbg-sponsor-report">شما دسترسی لازم برای مشاهدهٔ گزارش را ندارید.</div>';
        }

        $user_id = get_current_user_id();
        $brand_ids = get_user_meta($user_id, 'jbg_sponsor_brand_ids', true);
        if (!is_array($brand_ids) || empty($brand_ids)) {
            return '<div class="jbg-sponsor-report">هیچ برندی برای شما تنظیم نشده است. لطفاً با ادمین هماهنگ کنید.</div>';
        }

        // همهٔ آگهی‌های برند(ها)
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
            return '<div class="jbg-sponsor-report">ویدئویی برای برند(های) شما یافت نشد.</div>';
        }

        // آمار دقیق از جدول لاگ بیلینگ (jbg_views): تعداد ردیف‌ها = بازدیدهای قابل پرداخت، SUM(amount) = هزینه
        // ساخت نقشه‌ی ad_id => [views,sum]
        global $wpdb;
        $table = $wpdb->prefix . 'jbg_views'; // ساختار/وجود جدول در سرویس بیلینگ تضمین شده است
        // SELECT ad_id, COUNT(*) views, SUM(amount) spend FROM wp_jbg_views WHERE ad_id IN (...) GROUP BY ad_id
        $in = implode(',', array_map('intval', $ad_ids));
        $rows = $wpdb->get_results("SELECT ad_id, COUNT(*) AS views, COALESCE(SUM(amount),0) AS spend
                                    FROM {$table}
                                    WHERE ad_id IN ($in)
                                    GROUP BY ad_id", ARRAY_A);

        $agg = [];
        foreach ((array)$rows as $r) {
            $aid = (int)$r['ad_id'];
            $agg[$aid] = [
                'views' => (int)$r['views'],
                'spend' => (int)$r['spend'],
            ];
        }

        // فرمت عدد
        $fmt = function(int $n): string {
            return number_format_i18n($n);
        };

        ob_start();
        ?>
        <style>
            .jbg-sponsor-report{direction:rtl}
            .jbg-sponsor-report table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}
            .jbg-sponsor-report th,.jbg-sponsor-report td{padding:10px;border-bottom:1px solid #f3f4f6;text-align:right}
            .jbg-sponsor-report th{background:#f9fafb;font-weight:700}
            .jbg-sponsor-report tr:last-child td{border-bottom:0}
            .jbg-sponsor-report .muted{color:#6b7280}
            .jbg-sponsor-report .toman::after{content:" تومان"}
            .jbg-sponsor-report .total{font-weight:700}
        </style>
        <div class="jbg-sponsor-report">
            <table>
                <thead>
                    <tr>
                        <th>ویدئو</th>
                        <th>برند</th>
                        <th>CPV</th>
                        <th>تعداد بازدید</th>
                        <th>هزینهٔ کل</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $grand_views = 0;
                $grand_spend = 0;

                foreach ($ad_ids as $aid) {
                    $ttl   = get_the_title($aid);
                    $link  = get_permalink($aid);
                    $cpv   = (int) get_post_meta($aid, 'jbg_cpv', true);
                    $brand_names = wp_get_post_terms($aid, 'jbg_brand', ['fields'=>'names']);
                    if (is_wp_error($brand_names)) $brand_names = [];

                    $views = $agg[$aid]['views'] ?? 0;
                    $spend = $agg[$aid]['spend'] ?? 0;

                    // اگر جدول لاگ برای این آگهی ردیفی نداشت، برای نمایش خالی نشود
                    if ($views === 0) {
                        // fallback: از شمارندهٔ متا صرفاً برای نمایش تعداد (بی‌اثر در هزینه)
                        $views_meta = (int) get_post_meta($aid, 'jbg_views_count', true);
                        $views = max($views, $views_meta);
                        // هزینه را فقط از log می‌گیریم؛ عدم افزایش دقت محاسبات را تضمین می‌کند
                    }

                    $grand_views += $views;
                    $grand_spend += $spend;

                    echo '<tr>';
                    echo   '<td><a href="'.esc_url($link).'">'.esc_html($ttl).'</a></td>';
                    echo   '<td class="muted">'.esc_html(implode('، ', $brand_names)).'</td>';
                    echo   '<td class="toman">'.esc_html($fmt($cpv)).'</td>';
                    echo   '<td>'.esc_html($fmt($views)).'</td>';
                    echo   '<td class="toman">'.esc_html($fmt($spend)).'</td>';
                    echo '</tr>';
                }

                echo '<tr class="total"><td>مجموع</td><td></td><td></td>';
                echo '<td>'.esc_html($fmt($grand_views)).'</td>';
                echo '<td class="toman">'.esc_html($fmt($grand_spend)).'</td></tr>';
                ?>
                </tbody>
            </table>
            <p class="muted" style="margin-top:8px">
                مبنای محاسبهٔ «هزینهٔ کل»، مجموع مقادیر <code>amount</code> ثبت‌شده در لاگ بیلینگ است؛
                ساختار و ثبت لاگ در سرویس بیلینگ انجام می‌شود. 
            </p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
