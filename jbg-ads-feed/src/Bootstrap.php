<?php
namespace JBG\Ads;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Taxonomy\Brand;
use JBG\Ads\Taxonomy\Category;
use JBG\Ads\PostType\Ad;
use JBG\Ads\Admin\MetaBox;
use JBG\Ads\Admin\Columns;

class Bootstrap {
    public static function init(): void {

        /* -----------------------------------------------------------------
         * Taxonomies + CPT
         * ----------------------------------------------------------------- */
        add_action('init', [Brand::class, 'register'], 5);
        add_action('init', [Category::class, 'register'], 6);
        add_action('init', [Ad::class, 'register'], 7);

        /* -----------------------------------------------------------------
         * Metaboxes (اصلی)
         * ----------------------------------------------------------------- */
        add_action('add_meta_boxes', [MetaBox::class, 'register']);
        add_action('save_post_jbg_ad', [MetaBox::class, 'save'], 10, 2);

        /* -----------------------------------------------------------------
         * Metabox امتیاز (در صورت وجود فایل/کلاس)
         * ----------------------------------------------------------------- */
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Admin/PointsMetaBox.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Admin\\PointsMetaBox')) {
                    add_action('add_meta_boxes', ['\\JBG\\Ads\\Admin\\PointsMetaBox', 'register']);
                    add_action('save_post_jbg_ad', ['\\JBG\\Ads\\Admin\\PointsMetaBox', 'save'], 10, 2);
                }
            }
        });

        /* -----------------------------------------------------------------
         * Shortcodes / Frontend
         * ----------------------------------------------------------------- */
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/ListShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\ListShortcode')) {
                    \JBG\Ads\Frontend\ListShortcode::register();
                }
            }
        });

        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/RelatedShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\RelatedShortcode')) {
                    \JBG\Ads\Frontend\RelatedShortcode::register();
                }
            }
        });

        // نمایش امتیاز کاربر (در صورت وجود)
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/PointsShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\PointsShortcode')) {
                    \JBG\Ads\Frontend\PointsShortcode::register();
                }
            }
        });

        // صفحهٔ تکی ویدیو (گارد + چینش بخش‌ها)
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/SingleLayout.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\SingleLayout')) {
                    \JBG\Ads\Frontend\SingleLayout::register();
                }
            }
        });

        // نشان‌گر بازدید
        add_action('init', function () {
            $vb = JBG_ADS_DIR . 'src/Frontend/ViewBadge.php';
            if (file_exists($vb)) {
                require_once $vb;
                if (class_exists('\\JBG\\Ads\\Frontend\\ViewBadge')) {
                    \JBG\Ads\Frontend\ViewBadge::register();
                }
            }
        });

        // UI/Assets لایک — ترجیح با LikeUI (تزریق کنار عنوان)؛ اگر نبود، fallback به LikeAssets
        add_action('init', function () {
            $ui = JBG_ADS_DIR . 'src/Frontend/LikeUI.php';
            if (file_exists($ui)) {
                require_once $ui;
                if (class_exists('\\JBG\\Ads\\Frontend\\LikeUI')) {
                    \JBG\Ads\Frontend\LikeUI::register();
                    return;
                }
            }
            $assets = JBG_ADS_DIR . 'src/Frontend/LikeAssets.php';
            if (file_exists($assets)) {
                require_once $assets;
                if (class_exists('\\JBG\\Ads\\Frontend\\LikeAssets')) {
                    \JBG\Ads\Frontend\LikeAssets::register();
                }
            }
        });

        /* -----------------------------------------------------------------
         * Progress / Gating / Points
         * ----------------------------------------------------------------- */
        add_action('init', function () {
            // Legacy unlock (در صورت وجود)
            $unlock = JBG_ADS_DIR . 'src/Progress/Unlock.php';
            if (file_exists($unlock)) {
                require_once $unlock;
                if (class_exists('\\JBG\\Ads\\Progress\\Unlock')) {
                    \JBG\Ads\Progress\Unlock::bootstrap();
                }
            }

            // Access (گیت نهایی و مدیریت ترتیب)
            $access = JBG_ADS_DIR . 'src/Progress/Access.php';
            if (file_exists($access)) {
                require_once $access;
                if (class_exists('\\JBG\\Ads\\Progress\\Access')) {
                    \JBG\Ads\Progress\Access::bootstrap();
                }
            }

            // Points engine (امتیازدهی)
            $pts = JBG_ADS_DIR . 'src/Progress/Points.php';
            if (file_exists($pts)) {
                require_once $pts;
                if (class_exists('\\JBG\\Ads\\Progress\\Points')) {
                    \JBG\Ads\Progress\Points::bootstrap();
                }
            }
        }, 9); // بعد از ثبت CPT/Tax ولی پیش از بیشتر شورت‌کدها

        /* -----------------------------------------------------------------
         * تنظیمات «تبدیل امتیاز → تخفیف» (اختیاری)
         * ----------------------------------------------------------------- */
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Admin/PointsDiscountSettings.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Admin\\PointsDiscountSettings')) {
                    \JBG\Ads\Admin\PointsDiscountSettings::register();
                }
            }
        });

        /* -----------------------------------------------------------------
         * REST endpoints
         * ----------------------------------------------------------------- */
        add_action('rest_api_init', function () {
            $feed = JBG_ADS_DIR . 'src/Rest/FeedController.php';
            if (file_exists($feed)) {
                require_once $feed;
                if (class_exists('\\JBG\\Ads\\Rest\\FeedController')) {
                    \JBG\Ads\Rest\FeedController::register_routes();
                }
            }

            $view = JBG_ADS_DIR . 'src/Rest/ViewController.php';
            if (file_exists($view)) {
                require_once $view;
                if (class_exists('\\JBG\\Ads\\Rest\\ViewController')) {
                    \JBG\Ads\Rest\ViewController::register_routes();
                }
            }

            $viewTrack = JBG_ADS_DIR . 'src/Rest/ViewTrackController.php';
            if (file_exists($viewTrack)) {
                require_once $viewTrack;
            }
            if (class_exists('\\JBG\\Ads\\Rest\\ViewTrackController')) {
                \JBG\Ads\Rest\ViewTrackController::register_routes();
            }

            // REST لایک‌ها (در صورت وجود فایل)
            $like = JBG_ADS_DIR . 'src/Rest/LikeController.php';
            if (file_exists($like)) {
                require_once $like;
            }
            if (class_exists('\\JBG\\Ads\\Rest\\LikeController')) {
                \JBG\Ads\Rest\LikeController::register_routes();
            }

            // NEW: REST «تبدیل امتیاز به کد تخفیف» (اختیاری)
            $redeem = JBG_ADS_DIR . 'src/Rest/PointsRedeemController.php';
            if (file_exists($redeem)) {
                require_once $redeem;
                if (class_exists('\\JBG\\Ads\\Rest\\PointsRedeemController')) {
                    \JBG\Ads\Rest\PointsRedeemController::register_routes();
                }
            }
        });
    }

    public static function activate(): void {
        Brand::register();
        Category::register();
        Ad::register();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void {
        flush_rewrite_rules(false);
    }
}
