<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class PointsShortcode {

    public static function register(): void {
        add_shortcode('jbg_points', [self::class, 'render']);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) {
            return '<div class="jbg-points-box" style="direction:rtl">'
                 . 'برای مشاهدهٔ امتیازها ابتدا وارد شوید.'
                 . '</div>';
        }

        $a = shortcode_atts([
            'show_history' => '1', // 0 یا 1
            'limit'        => '20', // سقف آیتم‌های تاریخچه
            'title'        => 'امتیازهای من',
        ], $atts, 'jbg_points');

        $user_id = get_current_user_id();
        $total   = (int) get_user_meta($user_id, 'jbg_points_total', true);
        $hist    = get_user_meta($user_id, 'jbg_pts_awarded', true);
        if (!is_array($hist)) $hist = [];

        // مرتب‌سازی تاریخچه بر اساس زمانِ دریافت (جدیدتر اول)
        uasort($hist, function($x,$y){
            return (int)($y['time'] ?? 0) <=> (int)($x['time'] ?? 0);
        });
        $limit = max(1, (int) $a['limit']);

        ob_start();
        ?>
        <div class="jbg-points" style="direction:rtl">
            <style>
                .jbg-points .jbg-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin:10px 0}
                .jbg-points .jbg-title{font-weight:800;margin-bottom:8px}
                .jbg-points .jbg-total{font-size:20px;font-weight:800}
                .jbg-points table{width:100%;border-collapse:collapse;margin-top:10px}
                .jbg-points th,.jbg-points td{border-bottom:1px solid #f1f5f9;padding:8px;text-align:right}
                .jbg-points a{ text-decoration:none; border:0; box-shadow:none; background-image:none }
                .jbg-points a:before, .jbg-points a:after{ display:none; content:none }
            </style>
            <div class="jbg-card">
                <div class="jbg-title"><?php echo esc_html($a['title']); ?></div>
                <div><?php _e('مجموع امتیازها:', 'jbg-ads'); ?> <span class="jbg-total"><?php echo esc_html($total); ?></span></div>
            </div>
            <?php if (!empty($hist) && $a['show_history'] === '1'): ?>
            <div class="jbg-card">
                <div class="jbg-title"><?php _e('تاریخچه دریافت', 'jbg-ads'); ?></div>
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('ویدیو', 'jbg-ads'); ?></th>
                            <th><?php _e('امتیاز', 'jbg-ads'); ?></th>
                            <th><?php _e('زمان', 'jbg-ads'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=0; foreach ($hist as $ad_id => $row): if (++$i>$limit) break;
                            $title = get_the_title($ad_id);
                            $perma = get_permalink($ad_id);
                            $pts   = (int)($row['points'] ?? 0);
                            $time  = !empty($row['time']) ? date_i18n('Y/m/d H:i', (int)$row['time']) : '';
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url($perma); ?>"><?php echo esc_html($title); ?></a></td>
                            <td><?php echo esc_html($pts); ?></td>
                            <td><?php echo esc_html($time); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
