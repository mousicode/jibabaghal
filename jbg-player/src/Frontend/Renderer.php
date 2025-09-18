<?php
namespace JBG\Player\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\Renderer')):

class Renderer {
    public static function bootstrap(): void {
        if (is_singular('jbg_ad')) {
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
            // مارک‌آپ پلیرت از قبل در قالب/شورت‌کد هست؛ دست نمی‌زنیم
        }
    }

    public static function enqueue_assets(): void {
        // Plyr + hls.js
        wp_enqueue_style('plyr', 'https://cdn.plyr.io/3.7.8/plyr.css', [], '3.7.8');
        wp_enqueue_script('hlsjs', 'https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js', [], '1.5.8', true);
        wp_enqueue_script('plyr', 'https://cdn.plyr.io/3.7.8/plyr.polyfilled.js', [], '3.7.8', true);

        // کنترل‌های پیش‌فرض Plyr شامل progress/seekbar هستند
        wp_add_inline_script('plyr', 'try{window.PLYR_DEFAULTS={}}catch(e){}', 'before');
    }
}
endif;
