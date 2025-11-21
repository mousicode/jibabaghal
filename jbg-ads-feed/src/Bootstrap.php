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

        // Grid of latest ads
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/GridShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\GridShortcode')) {
                    \JBG\Ads\Frontend\GridShortcode::register();
                }
            }
        });

        // نمایش امتیاز کاربر
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/PointsShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\PointsShortcode')) {
                    \JBG\Ads\Frontend\PointsShortcode::register();
                }
            }
        });

        // کاربران برتر
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/TopUsersShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\TopUsersShortcode')) {
                    \JBG\Ads\Frontend\TopUsersShortcode::register();
                }
            }
        });

        // گزارش اسپانسر
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/SponsorReportShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\SponsorReportShortcode')) {
                    \JBG\Ads\Frontend\SponsorReportShortcode::register();
                }
            }
        });

        // شورت‌کد استعلام کد تخفیف (فقط UI؛ دسترسی را خود شورت‌کد کنترل می‌کند)
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/CouponCheckShortcode.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\CouponCheckShortcode')) {
                    \JBG\Ads\Frontend\CouponCheckShortcode::register();
                }
            }
        });

        // صفحهٔ تکی ویدیو
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Frontend/SingleLayout.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Frontend\\SingleLayout')) {
                    \JBG\Ads\Frontend\SingleLayout::register();
                }
            }
        });

        // سرخط و متای زیر پلیر
        add_action('init', function () {
            $vb = JBG_ADS_DIR . 'src/Frontend/ViewBadge.php';
            if (file_exists($vb)) {
                require_once $vb;
                if (class_exists('\\JBG\\Ads\\Frontend\\ViewBadge')) {
                    \JBG\Ads\Frontend\ViewBadge::register();
                }
            }
        });

        // enqueue استایل هدر ویدیو
        add_action('wp_enqueue_scripts', function () {
            if (is_singular('jbg_ad')) {
                wp_enqueue_style(
                    'jbg-video-header',
                    plugins_url('../assets/css/jbg-video-header.css', __FILE__),
                    [],
                    '1.0'
                );
            }
        });

        /* =================================================================
         * ثبت‌نام اختصاصی «اسپانسر/برند» (بدون تغییر در ثبت‌نام معمولی Digits)
         * - شورت‌کد: [jbg_sponsor_register]
         * - مسیر کلاس: src/Onboarding/SponsorRegister.php
         * ================================================================= */
        add_action('init', function () {
            $f = JBG_ADS_DIR . 'src/Onboarding/SponsorRegister.php';
            if (file_exists($f)) {
                require_once $f;
                if (class_exists('\\JBG\\Ads\\Onboarding\\SponsorRegister')) {
                    \JBG\Ads\Onboarding\SponsorRegister::register(); // ← رجیستر شورت‌کد و نقش
                }
            }
        });

        /* -----------------------------------------------------------------
         * Progress / Gating / Points
         * ----------------------------------------------------------------- */
        add_action('init', function () {
            $unlock = JBG_ADS_DIR . 'src/Progress/Unlock.php';
            if (file_exists($unlock)) {
                require_once $unlock;
                if (class_exists('\\JBG\\Ads\\Progress\\Unlock')) {
                    \JBG\Ads\Progress\Unlock::bootstrap();
                }
            }

            $access = JBG_ADS_DIR . 'src/Progress/Access.php';
            if (file_exists($access)) {
                require_once $access;
                if (class_exists('\\JBG\\Ads\\Progress\\Access')) {
                    \JBG\Ads\Progress\Access::bootstrap();
                }
            }

            $pts = JBG_ADS_DIR . 'src/Progress/Points.php';
            if (file_exists($pts)) {
                require_once $pts;
                if (class_exists('\\JBG\\Ads\\Progress\\Points')) {
                    \JBG\Ads\Progress\Points::bootstrap();
                }
            }
        }, 9);

        /* -----------------------------------------------------------------
         * Admin Settings
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

        // انتساب برند به کاربر اسپانسر
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
         * WALLET: سرویس، شورت‌کد و REST
         * ----------------------------------------------------------------- */
        add_action('init', function () {
            $w = JBG_ADS_DIR . 'src/Wallet/Wallet.php';
            if (file_exists($w)) {
                require_once $w;
                if (class_exists('\\JBG\\Ads\\Wallet\\Wallet')) {
                    add_action('jbg_billed', ['\\JBG\\Ads\\Wallet\\Wallet', 'deduct_on_billed'], 10, 2);
                }
            }
            $sc = JBG_ADS_DIR . 'src/Frontend/WalletShortcode.php';
            if (file_exists($sc)) {
                require_once $sc;
                if (class_exists('\\JBG\\Ads\\Frontend\\WalletShortcode')) {
                    \JBG\Ads\Frontend\WalletShortcode::register();
                }
            }
        });

        /* -----------------------------------------------------------------
         * REST API
         * ----------------------------------------------------------------- */
        add_action('rest_api_init', function () {
            // Wallet API
            $rc = JBG_ADS_DIR . 'src/Rest/WalletController.php';
            if (file_exists($rc)) {
                require_once $rc;
                if (class_exists('\\JBG\\Ads\\Rest\\WalletController')) {
                    \JBG\Ads\Rest\WalletController::register_routes();
                }
            }

            // Points redeem API
            $pr = JBG_ADS_DIR . 'src/Rest/PointsRedeemController.php';
            if (file_exists($pr)) {
                require_once $pr;
                if (class_exists('\\JBG\\Ads\\Rest\\PointsRedeemController')) {
                    \JBG\Ads\Rest\PointsRedeemController::register_routes();
                }
            }

            // NEW: Coupon check API برای استعلام کد تخفیف
            $cc = JBG_ADS_DIR . 'src/Rest/CouponCheckController.php';
            if (file_exists($cc)) {
                require_once $cc;
                if (class_exists('\\JBG\\Ads\\Rest\\CouponCheckController')) {
                    \JBG\Ads\Rest\CouponCheckController::register_routes();
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
