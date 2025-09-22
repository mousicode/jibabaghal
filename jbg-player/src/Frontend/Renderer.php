<?php
/**
 * Player Frontend Renderer
 */
namespace JBG\Player\Frontend;

class Renderer
{
    public static function bootstrap(): void
    {
        if (!is_singular('jbg_ad')) {
            return;
        }
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void
    {
        // Plyr
        if (!wp_script_is('plyr', 'registered')) {
            wp_register_script(
                'plyr',
                'https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.min.js',
                [],
                '3',
                true
            );
            wp_register_style(
                'plyr-css',
                'https://cdn.jsdelivr.net/npm/plyr@3/dist/plyr.css',
                [],
                '3'
            );
        }
        wp_enqueue_style('plyr-css');
        wp_enqueue_script('plyr');

        // HLS.js
        if (!wp_script_is('hlsjs', 'registered')) {
            wp_register_script(
                'hlsjs',
                'https://cdn.jsdelivr.net/npm/hls.js@latest',
                [],
                null,
                true
            );
        }
        wp_enqueue_script('hlsjs');

        // اگر ثابت‌های آدرس افزونه موجودند، استفاده کن؛ در غیر این صورت از plugin_dir_url
        if (!defined('JBG_PLAYER_URL')) {
            // تلاش برای حدس آدرس افزونه (نسبت به این فایل)
            $url_guess = trailingslashit(plugins_url('/', dirname(dirname(__DIR__))));
            define('JBG_PLAYER_URL', $url_guess);
        }

        // اسکریپت کنترلی ما (قفل seek + watched_ok + REST ping)
        wp_enqueue_script(
            'jbg-player',
            trailingslashit(JBG_PLAYER_URL) . 'assets/js/jbg-player.js',
            ['plyr', 'hlsjs'],
            '0.1.1',
            true
        );

        $ad_id = get_the_ID() ?: 0;
        wp_localize_script('jbg-player', 'JBG_PLAYER', [
            'watch' => rest_url('jbg/v1/watch-complete'),
            'nonce' => wp_create_nonce('wp_rest'),
            'adId'  => $ad_id,
        ]);
    }
}
