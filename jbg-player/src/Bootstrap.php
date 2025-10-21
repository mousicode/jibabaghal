<?php
namespace JBG\Player;

use JBG\Player\Admin\MetaBox;
use JBG\Player\Frontend\Renderer;
use JBG\Player\Rest\WatchController;

class Bootstrap {
    public static function init(): void {
        // Meta box for video source
        add_action('add_meta_boxes', [MetaBox::class, 'register']);
        add_action('save_post_jbg_ad', [MetaBox::class, 'save'], 10, 2);

        // Front-end renderer (player + assets)
        add_action('wp', [Renderer::class, 'bootstrap']);

        // REST route to record watch-complete
        add_action('rest_api_init', [WatchController::class, 'register_routes']);

        // Mobile-only CSS for single video pages
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_mobile_css']);
    }

    public static function enqueue_mobile_css(): void {
        if (!is_singular('jbg_ad')) return;
        // __FILE__ is src/Bootstrap.php → go up one level to /assets/css/
        wp_enqueue_style(
            'jbg-player-mobile',
            plugins_url('../assets/css/jbg-player-mobile.css', __FILE__),
            [],
            '1.0'
        );
    }

    public static function activate(): void {
        // no db changes
    }

    public static function deactivate(): void { }
}
