<?php
namespace JBG\Player\Frontend;

class Renderer {
    public static function bootstrap(): void {
        if (is_singular('jbg_ad')) {
            add_filter('the_content', [self::class, 'inject_player'], 5); // before content
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        }
        // Optional shortcode for manual placement
        add_shortcode('jbg_player', [self::class, 'shortcode']);
    }

    public static function enqueue_assets(): void {
        // Plyr + hls.js from CDN (stable)
        wp_enqueue_style('plyr', 'https://cdn.plyr.io/3.7.8/plyr.css', [], '3.7.8');
        wp_enqueue_script('hlsjs', 'https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js', [], '1.5.8', true);
        wp_enqueue_script('plyr', 'https://cdn.plyr.io/3.7.8/plyr.polyfilled.js', [], '3.7.8', true);

        // Our controller
        wp_enqueue_script('jbg-player', JBG_PLAYER_URL . 'assets/js/jbg-player.js', ['plyr', 'hlsjs'], '0.1.0', true);
        wp_enqueue_style('jbg-player', JBG_PLAYER_URL . 'assets/css/jbg-player.css', ['plyr'], '0.1.0');

        // Pass runtime data
        $current_ad_id = (int) get_queried_object_id();
        wp_localize_script('jbg-player', 'JBG_PLAYER', [
            'threshold' => 0.99, // یا مقدار دلخواه/فعلی شما
            'track' => [
                'url'     => rest_url('jbg/v1/view/track'),
                'nonce'   => wp_create_nonce('wp_rest'),
                'adId'    => $current_ad_id,
                'enabled' => is_user_logged_in() ? 1 : 0, // فقط برای کاربران لاگین‌شده
            ],
        ]);
    }

    public static function build_markup(int $post_id): string {
        $src = (string) get_post_meta($post_id, 'jbg_video_src', true);
        if (!$src) return '<div class="jbg-player-warn">No video source set for this Ad.</div>';

        $escaped_src = esc_url($src);
        $escaped_id  = (int) $post_id; // امن برای درج در HTML
        $btn = '<button id="jbg-quiz-btn" class="jbg-btn" disabled>' . esc_html__('Start Quiz','jbg-player') . '</button>';

        // Player container
        $html = '<div class="jbg-player-wrapper" data-src="' . $escaped_src . '" data-ad-id="' . $escaped_id . '">'
              . ' <video id="jbg-player" playsinline controls preload="metadata"></video>'
              . ' <div class="jbg-status" id="jbg-status"></div>'
              . ' <div class="jbg-actions">' . $btn . '</div>'
              . '</div>';

        return $html;
    }

    public static function inject_player($content) {
        if (!is_singular('jbg_ad')) return $content;
        return self::build_markup(get_the_ID()) . $content;
    }

    public static function shortcode($atts = [], $content = ''): string {
        $atts = shortcode_atts(['id' => get_the_ID()], $atts, 'jbg_player');
        $id = (int) $atts['id'];
        return self::build_markup($id);
    }
}
