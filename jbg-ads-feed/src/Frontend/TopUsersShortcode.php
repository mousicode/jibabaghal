<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * [jbg_top_users]
 * لیست کاربران با بیشترین مجموع امتیاز (jbg_points_total)
 * - بدون تغییر در بک‌اند امتیازدهی
 * - فقط خواندن user_meta و نمایش
 */
class TopUsersShortcode
{
    public static function register(): void
    {
        add_shortcode('jbg_top_users', [self::class, 'render']);
    }

    private static function nf($n): string
    {
        return function_exists('number_format_i18n') ? number_format_i18n((int)$n) : (string)(int)$n;
    }

    public static function render($atts = []): string
    {
        $a = shortcode_atts([
            'limit'      => 10,
            'avatars'    => 1,
            'link'       => 0,
            'min_points' => 1,
        ], $atts, 'jbg_top_users');

        $limit      = max(1, min(100, (int)$a['limit']));
        $min_points = max(0, (int)$a['min_points']);
        $show_avatar= ((int)$a['avatars']) === 1;
        $link_name  = ((int)$a['link']) === 1;

        // WP_User_Query بر اساس meta_key عددی
        $q = new \WP_User_Query([
            'number'       => $limit,
            'meta_key'     => 'jbg_points_total',
            'orderby'      => 'meta_value_num',
            'order'        => 'DESC',
            'fields'       => ['ID', 'display_name', 'user_nicename'],
            'count_total'  => false,
            'meta_query'   => $min_points > 0 ? [[
                'key'     => 'jbg_points_total',
                'value'   => $min_points,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ]] : [],
        ]);

        $users = (array) $q->get_results();
        if (empty($users)) {
            return '<div class="jbg-top-users" style="direction:rtl"><div class="jbg-card">کاربری با امتیاز کافی یافت نشد.</div></div>';
        }

        // یک‌بار CSS سبک
        static $once = false;
        $css = '';
        if (!$once) {
            $css = '<style id="jbg-top-users-css">
                .jbg-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:12px 0}
                .jbg-top-users{direction:rtl}
                .jbg-top-users .title{font-weight:800;margin:0 0 10px}
                .jbg-top-users table{width:100%;border-collapse:collapse}
                .jbg-top-users th,.jbg-top-users td{padding:10px;border-bottom:1px solid #f3f4f6;text-align:right}
                .jbg-rank{font-weight:700}
                .jbg-pts-badge{display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:9999px;padding:2px 10px;font-size:12px}
                .jbg-user{display:flex;align-items:center;gap:10px}
                .jbg-user img{width:32px;height:32px;border-radius:50%}
            </style>';
            $once = true;
        }

        ob_start();
        echo $css;
        echo '<div class="jbg-top-users"><div class="jbg-card">';
        echo '<div class="title">کاربران برتر</div>';
        echo '<table><thead><tr><th>#</th><th>کاربر</th><th>امتیاز</th></tr></thead><tbody>';

        $rank = 0;
        foreach ($users as $u) {
            $rank++;
            $uid   = (int) $u->ID;
            $name  = $u->display_name ?: $u->user_nicename;
            $pts   = (int) get_user_meta($uid, 'jbg_points_total', true);
            if ($pts < $min_points) continue;

            $avatar = $show_avatar ? get_avatar($uid, 32) : '';
            $userCol= $show_avatar ? '<div class="jbg-user">'.$avatar.'<span>'.esc_html($name).'</span></div>'
                                   : esc_html($name);
            if ($link_name) {
                $url = get_author_posts_url($uid);
                $userCol = '<a href="'.esc_url($url).'" rel="nofollow">'.$userCol.'</a>';
            }

            echo '<tr>'.
                 '<td class="jbg-rank">'.esc_html((string)$rank).'</td>'.
                 '<td>'.$userCol.'</td>'.
                 '<td><span class="jbg-pts-badge">'.esc_html(self::nf($pts)).'</span></td>'.
                 '</tr>';
        }

        echo '</tbody></table></div></div>';
        return (string) ob_get_clean();
    }
}
