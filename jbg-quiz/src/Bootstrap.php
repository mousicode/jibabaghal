<?php
/**
 * Quiz Bootstrap
 *
 * مسئولیت‌ها:
 * - رجیستر متاباکس‌های کوییز
 * - بارگذاری اسکریپت‌های فرانت کوییز
 * - ثبت روت‌های REST ایمن برای submit کوییز
 */

namespace JBG\Quiz;

use JBG\Quiz\Admin\MetaBox;
use JBG\Quiz\Frontend\Renderer;
use JBG\Quiz\Rest\SubmitController;

class Bootstrap
{
    public static function init(): void
    {
        // Admin: metaboxes for quiz (question, answers, correct)
        add_action('init', [MetaBox::class, 'register']);

        // Frontend: enqueue quiz assets / wiring
        add_action('wp', [Renderer::class, 'bootstrap']);

        // REST: secure quiz submit endpoint (uses Auth::rest_permission())
        add_action('rest_api_init', [SubmitController::class, 'register_routes']);
    }
}

// auto-init when plugin loads
if (function_exists('add_action')) {
    add_action('plugins_loaded', [Bootstrap::class, 'init']);
}
