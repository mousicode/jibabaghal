<?php
/**
 * Quiz Bootstrap
 */
namespace JBG\Quiz;

use JBG\Quiz\Admin\MetaBox;
use JBG\Quiz\Frontend\Renderer;
use JBG\Quiz\Rest\SubmitController;

class Bootstrap
{
    public static function init(): void
    {
        // Admin meta box
        add_action('init', [MetaBox::class, 'register']);

        // Front assets for quiz
        add_action('wp', [Renderer::class, 'bootstrap']);

        // Secure REST endpoint for quiz submit
        add_action('rest_api_init', [SubmitController::class, 'register_routes']);
    }
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', [Bootstrap::class, 'init']);
}
