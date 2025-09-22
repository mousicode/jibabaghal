<?php
namespace JBG\Ads;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Taxonomy\Brand;
use JBG\Ads\Taxonomy\Category;
use JBG\Ads\PostType\Ad;
use JBG\Ads\Admin\MetaBox;
use JBG\Ads\Admin\Columns;
use JBG\Ads\Admin\VideoMetaBox; // ← متاباکس ویدئو/ایمبد

class Bootstrap {
    public static function init(): void {

        // ───────── Taxonomies + CPT ─────────
        \add_action('init', [Brand::class, 'register'], 5);
        \add_action('init', [Category::class, 'register'], 6);
        \add_action('init', [Ad::class, 'register'], 7);

        // ───────── Metaboxes ─────────
        \add_action('add_meta_boxes', [MetaBox::class, 'register']);
        \add_action('save_post_jbg_ad', [MetaBox::class, 'save'], 10, 2);

        // منبع ویدئو (ضروری برای PlayerShim)
        \add_action('add_meta_boxes', [VideoMetaBox::class, 'register']);
        \add_action('save_post_jbg_ad', [VideoMetaBox::class, 'save'], 10, 2);

        // ───────── Admin columns ─────────
        if (\is_admin()) {
            \add_filter('manage_jbg_ad_posts_columns', [Columns::class, 'columns']);
            \add_action('manage_jbg_ad_posts_custom_column', [Columns::class, 'render'], 10, 2);
            \add_filter('manage_edit-jbg_ad_sortable_columns', [Columns::class, 'sortable']);
            \add_action('pre_get_posts', [Columns::class, 'handle_sorting']);
        }

        // ───────── Frontend components (شورتکدها/نما) ─────────
        \add_action('init', function () {
            $map = [
                'src/Frontend/PlayerShim.php'        => '\\JBG\\Ads\\Frontend\\PlayerShim',
                'src/Frontend/ListShortcode.php'     => '\\JBG\\Ads\\Frontend\\ListShortcode',
                'src/Frontend/RelatedShortcode.php'  => '\\JBG\\Ads\\Frontend\\RelatedShortcode',
                'src/Frontend/ViewBadge.php'         => '\\JBG\\Ads\\Frontend\\ViewBadge',
                'src/Frontend/SingleLayout.php'      => '\\JBG\\Ads\\Frontend\\SingleLayout',
                'src/Frontend/AccessGate.php'        => '\\JBG\\Ads\\Frontend\\AccessGate',
            ];
            foreach ($map as $rel => $fqcn) {
                $file = JBG_ADS_DIR . $rel;
                if (\is_file($file)) { require_once $file; }
                if (\class_exists($fqcn) && \method_exists($fqcn, 'register')) {
                    $fqcn::register();
                }
            }
        }, 10);

        // ───────── REST (2-مرحله‌ای) ─────────
        \add_action('init', function () {
            foreach ([
                'src/Rest/FeedController.php',
                'src/Rest/ViewController.php',
                'src/Rest/ViewTrackController.php',
                'src/Rest/NextController.php',
            ] as $rel) {
                $f = JBG_ADS_DIR . $rel;
                if (\is_file($f)) { require_once $f; }
            }
        }, 6);

        \add_action('rest_api_init', function () {
            foreach ([
                '\\JBG\\Ads\\Rest\\FeedController',
                '\\JBG\\Ads\\Rest\\ViewController',
                '\\JBG\\Ads\\Rest\\ViewTrackController',
                '\\JBG\\Ads\\Rest\\NextController',
            ] as $fqcn) {
                if (\class_exists($fqcn) && \method_exists($fqcn, 'register_routes')) {
                    $fqcn::register_routes();
                }
            }
        }, 10);

        // ───────── Quiz Pass Flag ─────────
        \add_action('jbg_quiz_passed', function ($user_id, $ad_id) {
            $user_id = (int) $user_id; $ad_id = (int) $ad_id;
            if ($user_id > 0 && $ad_id > 0 && \get_post_type($ad_id) === 'jbg_ad') {
                \update_user_meta($user_id, 'jbg_quiz_passed_' . $ad_id, \current_time('mysql'));
            }
        }, 5, 2);
    }

    public static function activate(): void {
        Brand::register(); Category::register(); Ad::register();
        \flush_rewrite_rules(false);
    }

    public static function deactivate(): void { \flush_rewrite_rules(false); }
}
