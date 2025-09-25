<?php
namespace JBG\Ads;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Taxonomy\Brand;
use JBG\Ads\Taxonomy\Category;
use JBG\Ads\PostType\Ad;
use JBG\Ads\Admin\MetaBox;
use JBG\Ads\Admin\Columns;

// NEW
use JBG\Ads\Admin\PointsMetaBox;
use JBG\Ads\Frontend\PointsShortcode;
use JBG\Ads\Progress\Points;

class Bootstrap {
    public static function init(): void {

        // Taxonomies + CPT
        add_action('init', [Brand::class, 'register'], 5);
        add_action('init', [Category::class, 'register'], 6);
        add_action('init', [Ad::class, 'register'], 7);

        // Metaboxes
        add_action('add_meta_boxes', [MetaBox::class, 'register']);
        add_action('save_post_jbg_ad', [MetaBox::class, 'save'], 10, 2);

        // NEW: Points meta box (independent, no conflict)
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Admin/PointsMetaBox.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Admin\\PointsMetaBox')) {
                    \JBG\Ads\Admin\PointsMetaBox::register();
                }
            }
        });

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

        // Shortcode: related
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Frontend/RelatedShortcode.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Frontend\\RelatedShortcode')) {
                    \JBG\Ads\Frontend\RelatedShortcode::register();
                }
            }
        });

        // NEW: Shortcode: points
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Frontend/PointsShortcode.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Frontend\\PointsShortcode')) {
                    \JBG\Ads\Frontend\PointsShortcode::register();
                }
            }
        });

        // Single two-column layout wrapper
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Frontend/SingleLayout.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Frontend\\SingleLayout')) {
                    \JBG\Ads\Frontend\SingleLayout::register();
                }
            }
        });

        // Single header badge
        add_action('init', function () {
            $vb = JBG_ADS_DIR . 'src/Frontend/ViewBadge.php';
            if (file_exists($vb)) {
                require_once $vb;
                if (class_exists('\\JBG\\Ads\\Frontend\\ViewBadge')) {
                    \JBG\Ads\Frontend\ViewBadge::register();
                }
            }
        });

        // NEW: Points engine
        add_action('init', function () {
            $file = JBG_ADS_DIR . 'src/Progress/Points.php';
            if (file_exists($file)) {
                require_once $file;
                if (class_exists('\\JBG\\Ads\\Progress\\Points')) {
                    \JBG\Ads\Progress\Points::bootstrap();
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
