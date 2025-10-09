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

        // NEW: گزارش اسپانسر
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/SponsorReportShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\SponsorReportShortcode')) {
                    \JBG\Ads\Frontend\SponsorReportShortcode::register();
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
         * Admin Settings
         * ----------------------------------------------------------------- */
        // تنظیمات «تبدیل امتیاز → مبلغ»
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Admin/PointsDiscountSettings.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Admin\\PointsDiscountSettings')) {
                    \JBG\Ads\Admin\PointsDiscountSettings::register();
                }
            }
        });

        // NEW: انتساب برند به کاربر اسپانسر (در صفحهٔ پروفایل کاربر)
        add_action('admin_init', function () {
            $f = JBG_ADS_DIR . 'src/Admin/SponsorBrandAccess.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Admin\\SponsorBrandAccess')) {
                    \JBG\Ads\Admin\SponsorBrandAccess::register();
                }
            }
        });

        /* -----------------------------------------------------------------
         * WALLET: سرویس، شورت‌کد و REST + اتصال به رخداد بیلینگ
         * ----------------------------------------------------------------- */
        // لود سرویس و شورت‌کد
        add_action('init', function () {
            // سرویس کیف‌پول
            $w = JBG_ADS_DIR . 'src/Wallet/Wallet.php';
            if (file_exists($w)) {
                require_once $w;
                if (class_exists('\\JBG\\Ads\\Wallet\\Wallet')) {
                    // اتصال به رخداد بیلینگ برای کسر بودجه اسپانسر
                    add_action('jbg_billed', ['\\JBG\\Ads\\Wallet\\Wallet', 'deduct_on_billed'], 10, 2);
                }
            }
            // شورت‌کد کیف‌پول
            $sc = JBG_ADS_DIR . 'src/Frontend/WalletShortcode.php';
            if (file_exists($sc)) {
                require_once $sc;
                if (class_exists('\\JBG\\Ads\\Frontend\\WalletShortcode')) {
                    \JBG\Ads\Frontend\WalletShortcode::register();
                }
            }
        });

        // REST کیف‌پول
        add_action('rest_api_init', function () {
            $rc = JBG_ADS_DIR . 'src/Rest/WalletController.php';
            if (file_exists($rc)) {
                require_once $rc;
                if (class_exists('\\JBG\\Ads\\Rest\\WalletController')) {
                    \JBG\Ads\Rest\WalletController::register_routes();
                }
            }
        });

        /* -----------------------------------------------------------------
         * UI Assets: یکسان‌سازی عرض محتوا با هدر/فوتر (1312px)
         * ----------------------------------------------------------------- */
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/UIAssets.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\UIAssets')) {
                    \JBG\Ads\Frontend\UIAssets::register();
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

            // REST «تبدیل امتیاز به کد تخفیف» (مبلغ ثابت)
            $redeem = JBG_ADS_DIR . 'src/Rest/PointsRedeemController.php';
            if (file_exists($redeem)) {
                require_once $redeem;
                if (class_exists('\\JBG\\Ads\\Rest\\PointsRedeemController')) {
                    \JBG\Ads\Rest\PointsRedeemController::register_routes();
                }
            }
        });

        /* -----------------------------------------------------------------
         * چاپ CSS شرطی برای هم‌عرض شدن محتوا با هدر/فوتر
         * ----------------------------------------------------------------- */
        add_action('wp_head', [self::class, 'print_content_width_css'], 99);
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

    /**
     * CSS شرطی: فقط روی صفحات سینگل آگهی یا صفحاتی که شورت‌کدهای ما در محتوا دارند اعمال می‌شود.
     * باعث می‌شود کانتینرهای افزونه و کانتینر المنتور تا 1312px هم‌عرض هدر/فوتر شوند.
     */
    public static function print_content_width_css(): void {
        $print = false;

        if (function_exists('is_singular') && is_singular('jbg_ad')) {
            $print = true;
        } else {
            $post = get_post();
            if ($post) {
                $c = (string) $post->post_content;
                if (
                    stripos($c, '[jbg_list') !== false ||
                    stripos($c, '[jbg_related') !== false ||
                    stripos($c, '[jbg_points') !== false ||
                    stripos($c, '[jbg_wallet') !== false ||
                    stripos($c, '[jbg_sponsor_report') !== false
                ) {
                    $print = true;
                }
            }
        }

        if (!$print) return;
        ?>
        <style id="jbg-content-width">
          :root { --jbg-content-width: 1312px; }

          /* ظرف‌های اصلی پلاگین */
          .jbg-grid,
          .jbg-related-grid,
          .jbg-points-wrap,
          .jbg-wallet,
          .jbg-sponsor-report,
          .jbg-ad-layout {
            max-width: var(--jbg-content-width);
            margin-left: auto;
            margin-right: auto;
            padding-left: 16px;
            padding-right: 16px;
            box-sizing: border-box;
          }

          /* صفحهٔ تکی آگهی: کانتینر المنتور/هلو را هم هم‌عرض کن */
          .single-jbg_ad .elementor-section .elementor-container {
            max-width: var(--jbg-content-width) !important;
          }
        </style>
        <?php
    }
}
