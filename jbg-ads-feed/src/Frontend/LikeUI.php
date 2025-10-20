<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class LikeUI {
    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('the_content', [self::class, 'inject_after_player'], 12);
    }

    public static function enqueue(): void {
        if (!is_singular('jbg_ad')) return;

        // CSS سبک دلخواه شما
        wp_enqueue_style('jbg-like', plugins_url('../../assets/css/jbg-like.css', __FILE__), [], '1.0');

        // JS
        wp_enqueue_script('jbg-like', plugins_url('../../assets/js/jbg-like.js', __FILE__), [], '1.1', true);

        $ad_id = (int) get_queried_object_id();
        wp_localize_script('jbg-like', 'JBG_LIKE', [
            'adId'  => $ad_id,
            'liked' => (is_user_logged_in() ? (bool) get_user_meta(get_current_user_id(), 'jbg_liked_'.$ad_id, true) : 0),
            'count' => (int) get_post_meta($ad_id, 'jbg_like_count', true),
            'logged'=> is_user_logged_in() ? 1 : 0,
            'nonce' => wp_create_nonce('wp_rest'),
            'rest'  => [
                'toggle' => rest_url('jbg/v1/like/toggle'),
                'status' => rest_url('jbg/v1/like/status'),
            ],
        ]);
    }

    /** عنوان + دکمه‌ها زیر ویدیو باقی بماند */
    public static function inject_after_player($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        // اگر قبلاً درج شده تکرار نکن
        if (strpos($content, 'jbg-like-ui') !== false) return $content;

        $title = get_the_title();
        $meta  = self::meta_line();

        $html  = '<div class="jbg-single-head">';
        $html .=   '<h1 class="entry-title">'.$title.'</h1>';
        $html .=   '<div class="jbg-like-ui">'
                .     '<button type="button" class="jbg-like-btn" data-jbg-like="up" aria-label="like">👍</button>'
                .     '<button type="button" class="jbg-like-btn" data-jbg-like="down" aria-label="dislike">👎</button>'
                .     '<span class="jbg-like-count" data-jbg-like-count>'.(int)get_post_meta(get_the_ID(),'jbg_like_count',true).'</span>'
                .   '</div>';
        $html .=   $meta;
        $html .= '</div>';

        // قرار دادن «بعد از» پلیر
        $content = preg_replace('~(</div>\s*</div>\s*</div>\s*</div>\s*)~i', '$1'.$html, $content, 1) ?: ($html.$content);
        return $content;
    }

    private static function meta_line(): string {
        $brand = '';
        $terms = wp_get_post_terms(get_the_ID(), 'jbg_brand', ['fields'=>'names']);
        if (!is_wp_error($terms) && !empty($terms)) $brand = esc_html($terms[0]);
        $views = (int) get_post_meta(get_the_ID(), 'jbg_views_count', true);
        $when  = get_the_time('U') ? human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ' . __('ago') : '';
        return '<div class="jbg-meta-line">'.
               ($brand ? '<span class="brand">'.$brand.'</span><span class="dot">•</span>' : '').
               '<span>'.number_format_i18n($views).' بازدید</span>'.
               ($when ? '<span class="dot">•</span><span>'.$when.'</span>' : '').
               '</div>';
    }
}
