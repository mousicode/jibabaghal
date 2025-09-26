<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Points;

class PointsShortcode {

    public static function register(): void {
        add_shortcode('jbg_points', [self::class, 'render']);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) {
            return '<div class="jbg-points-card">'. esc_html__('Please log in to see your points.', 'jbg-ads') .'</div>';
        }
        $a = shortcode_atts([
            'limit' => 10,          // تعداد سطرهای تاریخچه
            'title' => 'امتیازهای من',
        ], $atts, 'jbg_points');

        $uid   = get_current_user_id();
        $total = Points::total($uid);
        $log   = Points::log($uid);
        $limit = max(1, (int)$a['limit']);
        $log   = array_reverse($log); // آخرین‌ها بالا
        $log   = array_slice($log, 0, $limit);

        ob_start();

        // استایل کوچک و مستقل
        static $printed = false;
        if (!$printed) {
            $printed = true;
            echo '<style id="jbg-points-css">
                .jbg-points-wrap{direction:rtl;max-width:800px;margin:16px auto;padding:0 10px}
                .jbg-points-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:16px}
                .jbg-points-title{font-weight:800;margin:0 0 8px 0}
                .jbg-points-total{font-size:22px;font-weight:700}
                .jbg-points-table{width:100%;border-collapse:collapse;margin-top:10px}
                .jbg-points-table th,.jbg-points-table td{border-bottom:1px solid #f1f5f9;padding:8px 6px;text-align:right}
                .jbg-points-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:9999px;padding:2px 10px;font-size:12px}
            </style>';
        }

        echo '<div class="jbg-points-wrap">';
        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html($a['title']) .'</div>';
        echo '      <div>'. esc_html__('Total Points:', 'jbg-ads') .' <span class="jbg-points-total">'. esc_html(number_format_i18n($total)) .'</span></div>';
        echo '  </div>';

        echo '  <div class="jbg-points-card">';
        echo '      <div class="jbg-points-title">'. esc_html__('Recent awards', 'jbg-ads') .'</div>';

        if (empty($log)) {
            echo '<div>'. esc_html__('No points yet.', 'jbg-ads') .'</div>';
        } else {
            echo '<table class="jbg-points-table"><thead><tr>';
            echo '<th>'. esc_html__('Video', 'jbg-ads') .'</th>';
            echo '<th>'. esc_html__('Points', 'jbg-ads') .'</th>';
            echo '<th>'. esc_html__('Date', 'jbg-ads') .'</th>';
            echo '</tr></thead><tbody>';
            foreach ($log as $row) {
                $title = isset($row['title']) ? (string)$row['title'] : '';
                $pid   = isset($row['ad_id']) ? (int)$row['ad_id'] : 0;
                $pts   = isset($row['points']) ? (int)$row['points'] : 0;
                $time  = isset($row['time']) ? (int)$row['time'] : time();

                $link  = $pid ? get_permalink($pid) : '';
                $tlink = $link ? '<a href="'.esc_url($link).'" target="_blank" rel="noopener">'.esc_html($title).'</a>' : esc_html($title);

                echo '<tr>';
                echo '<td>'. $tlink .'</td>';
                echo '<td><span class="jbg-points-badge">'. esc_html(number_format_i18n($pts)) .'</span></td>';
                echo '<td>'. esc_html( date_i18n(get_option('date_format').' '.get_option('time_format'), $time) ) .'</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '  </div>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}
