<?php
namespace JBG\Billing;

if (!defined('ABSPATH')) exit;

use JBG\Billing\Admin\BrandColumn;
use JBG\Billing\Admin\LogsPage;
use JBG\Billing\Service;
// اختیاری: اگر BrandSpend را گذاشته‌ای برای صفحهٔ بازسازی از آن استفاده می‌کنیم
use JBG\Billing\BrandSpend;

class Bootstrap {

    public static function init(): void {
        // وقتی آزمون پاس شد، بیلینگ را یک‌بار انجام بده
        add_action('jbg_quiz_passed', [Service::class, 'handle_quiz_pass'], 10, 2);

        if (is_admin()) {
            // ستون هزینهٔ برند در صفحهٔ برندها
            add_filter('manage_edit-jbg_brand_columns', [BrandColumn::class, 'columns']);
            add_filter('manage_jbg_brand_custom_column', [BrandColumn::class, 'render'], 10, 3);

            // صفحهٔ لاگ‌ها
            add_action('admin_menu', [LogsPage::class, 'register']);

            // صفحهٔ «بازسازی مجموع هزینهٔ برندها» (در صورت وجود کلاس BrandSpend)
            add_action('admin_menu', function () {
                if (class_exists('\\JBG\\Billing\\BrandSpend')) {
                    add_submenu_page(
                        'edit.php?post_type=jbg_ad',
                        __('Recalculate Brand Spend','jbg-billing'),
                        __('Recalc Brand Spend','jbg-billing'),
                        'manage_options',
                        'jbg-recalc-brands',
                        [BrandSpend::class, 'admin_page']
                    );
                }
            });
        }

        // REST تستی برای مشاهده/همگام‌سازی شمارندهٔ بازدید
        add_action('rest_api_init', function () {
            $file = (defined('JBG_BILLING_DIR') ? JBG_BILLING_DIR : plugin_dir_path(__FILE__) . '../') . 'Rest/StatsController.php';
            if (file_exists($file)) {
                require_once $file;
            }
            if (class_exists('\\JBG\\Billing\\Rest\\StatsController')) {
                \JBG\Billing\Rest\StatsController::register();
            }
        });

        // توجه: اگر افزایش هزینهٔ برند را داخل Service::bill() انجام می‌دهی
        // (که در نسخهٔ فعلی انجام می‌شود)، این هوک را فعال نکن تا دوباره‌شماری نشود.
        // add_action('jbg_billed', [BrandSpend::class, 'increment'], 10, 4);
    }

    public static function activate(): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'jbg_views';
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // نکته: در dbDelta نباید از "IF NOT EXISTS" استفاده کرد
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ad_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            amount INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            ip VARCHAR(45) DEFAULT '' NOT NULL,
            ua VARCHAR(255) DEFAULT '' NOT NULL,
            PRIMARY KEY (id),
            KEY ad_id (ad_id),
            KEY user_id (user_id)
        ) {$charset};";

        dbDelta($sql);
    }

    public static function deactivate(): void {
        // فعلاً نیازی نیست
    }
}
