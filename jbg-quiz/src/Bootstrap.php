<?php
/**
 * Quiz Bootstrap
 */
namespace JBG\Quiz;

use JBG\Quiz\Admin\MetaBox;
use JBG\Quiz\Frontend\Renderer;
use JBG\Quiz\Rest\SubmitController; // استفاده از کنترلر اعتبارسنجی‌شده

class Bootstrap
{
    public static function init(): void
    {
        // ادیتور/متاباکس
        add_action('init', [MetaBox::class, 'register']);
        // فرانت
        add_action('wp', [Renderer::class, 'bootstrap']);
        // REST (نسخه امن با permission و validation)
        add_action('rest_api_init', [SubmitController::class, 'register_routes']);
    }
}

// اجرای خودکار در لود افزونه
if (function_exists('add_action')) {
    add_action('plugins_loaded', [Bootstrap::class, 'init']);
}
