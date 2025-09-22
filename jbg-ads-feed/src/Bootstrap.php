<?php
namespace JBG\Ads;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Taxonomy\Brand;
use JBG\Ads\Taxonomy\Category;            // ← NEW
use JBG\Ads\PostType\Ad;
use JBG\Ads\Admin\MetaBox;
use JBG\Ads\Admin\Columns;
// برای سیستم قطره‌ای (progress)
use JBG\Ads\Progress\Unlock;              // ← NEW (bootstrap)
use JBG\Ads\Progress\Access;              // ← NEW (type-hint/use در کلاس‌های دیگر)

class Bootstrap {
    public static function init(): void {

        // Taxonomies + CPT
        add_action('init', [Brand::class, 'register'], 5);
        add_action('init', [Category::class, 'register'], 6);   // ← NEW
        add_action('init', [Ad::class, 'register'], 7);

        // Metaboxes
        add_action('add_meta_boxes', [MetaBox::class, 'register']);
        add_action('save_post_jbg_ad', [MetaBox::class, 'save'], 10, 2);

        // Admin columns
        if (is_admin()) {
            add_filter('manage_jbg_ad_posts_columns', [Columns::class, 'columns']);
            add_action('manage_jbg_ad_posts_custom_column', [Columns::class, 'render'], 10, 2);
            add_filter('manage_edit-jbg_ad_sortable_columns', [Columns::class, 'sortable']);
            add_action('pre_get_posts', [Columns::class, 'handle_sorting']);
        }

        // Shortcode: list
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Frontend/ListShortcode.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Frontend\\ListShortcode')) {
                    \JBG\Ads\Frontend\ListShortcode::register();
                }
            }
        });

        // Shortcode: related (by category)
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Frontend/RelatedShortcode.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Frontend\\RelatedShortcode')) {
                    \JBG\Ads\Frontend\RelatedShortcode::register();
                }
            }
        });

        // Single layout (stack: Player → Quiz → Related)
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Frontend/SingleLayout.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Frontend\\SingleLayout')) {
                    \JBG\Ads\Frontend\SingleLayout::register();
                }
            }
        });

        // View badge (header)
        add_action('init', function () {
            $vb = JBG_ADS_DIR . 'src/Frontend/ViewBadge.php';
            if (file_exists($vb)) {
                require_once $vb;
                if (class_exists('\\JBG\\Ads\\Frontend\\ViewBadge')) {
                    \JBG\Ads\Frontend\ViewBadge::register();
                }
            }
        });

        // --- NEW: Progress/Gating (قطره‌ای)
        add_action('init', function () {
            // Access: ابزارهای کمکی فقط use می‌شوند؛ بوت لازم ندارد
            $access = JBG_ADS_DIR . 'src/Progress/Access.php';
            if (file_exists($access)) require_once $access;

            // Unlock: شنوندهٔ رویداد بیلینگ برای باز کردن مرحله بعد
            $unlock = JBG_ADS_DIR . 'src/Progress/Unlock.php';
            if (file_exists($unlock)) {
                require_once $unlock;
                if (class_exists('\\JBG\\Ads\\Progress\\Unlock')) {
                    \JBG\Ads\Progress\Unlock::bootstrap();
                }
            }
        });

        // REST endpoints
        add_action('rest_api_init', function () {
            $feed = JBG_ADS_DIR . 'src/Rest/FeedController.php';
            if (file_exists($feed)) {
                require_once $feed;
                if (class_exists('\\JBG\\Ads\\Rest\\FeedController')) {
                    \JBG\Ads\Rest\FeedController::register_routes();
                }
            }

            // اگر ViewController را حذف کرده‌ای، این بلوک به‌صورت امن نادیده گرفته می‌شود
            $view = JBG_ADS_DIR . 'src/Rest/ViewController.php';
            if (file_exists($view)) {
                require_once $view;
                if (class_exists('\\JBG\\Ads\\Rest\\ViewController')) {
                    \JBG\Ads\Rest\ViewController::register_routes();
                }
            }

            // ViewTrackController (ثبت تعامل روزانه — بدون افزایش شمارنده)
            $viewTrack = JBG_ADS_DIR . 'src/Rest/ViewTrackController.php';
            if (file_exists($viewTrack)) {
                require_once $viewTrack;
            }
            if (class_exists('\\JBG\\Ads\\Rest\\ViewTrackController')) {
                \JBG\Ads\Rest\ViewTrackController::register_routes();
            }
        });
    }

    public static function activate(): void {
        Brand::register();
        Category::register();   // ← NEW
        Ad::register();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void {
        flush_rewrite_rules(false);
    }
}
