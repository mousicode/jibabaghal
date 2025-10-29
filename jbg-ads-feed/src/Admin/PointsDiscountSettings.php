<?php
namespace JBG\Ads\Admin;

if (!defined('ABSPATH')) exit;

class PointsDiscountSettings {

    const OPTION = 'jbg_points_discount';

    public static function register(): void {
        add_action('admin_init', [self::class, 'settings']);
        add_action('admin_menu', [self::class, 'menu']);
    }

    /** مقادیر پیش‌فرض */
    public static function defaults(): array {
        return [
            'points_per_unit' => 1000,     // هر ۱۰۰۰ امتیاز
            'toman_per_unit'  => 100000,   // معادل ۱۰۰٬۰۰۰ تومان
            'min_points'      => 1000,     // حداقل امتیاز برای تبدیل
            'expiry_days'     => 7,        // انقضای کد (روز)
        ];
    }

    /** خواندن تنظیمات با ادغام پیش‌فرض‌ها */
    public static function get(): array {
        $opt = get_option(self::OPTION, []);
        return wp_parse_args(is_array($opt) ? $opt : [], self::defaults());
    }

    /** افزودن صفحهٔ تنظیمات در منوی «آگهی‌ها» */
    public static function menu(): void {
        add_submenu_page(
            'edit.php?post_type=jbg_ad',
            __('تنظیمات امتیاز و تبدیل به تخفیف', 'jbg-ads'),
            __('تبدیل امتیاز به تخفیف', 'jbg-ads'),
            'manage_options',
            'jbg_points_discount',
            [self::class, 'render_page']
        );
    }

    /** ثبت گزینه‌ها و فیلدهای تنظیمات */
    public static function settings(): void {
        register_setting(self::OPTION, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => function ($v) {
                $d = self::defaults();
                return [
                    'points_per_unit' => max(1, (int)($v['points_per_unit'] ?? $d['points_per_unit'])),
                    // حذف محدودیت 100 و هرگونه پله‌بندی؛ فقط حداقل صفر
                    'toman_per_unit'  => max(0, (int)($v['toman_per_unit']  ?? $d['toman_per_unit'])),
                    'min_points'      => max(0, (int)($v['min_points']      ?? $d['min_points'])),
                    'expiry_days'     => max(1, (int)($v['expiry_days']     ?? $d['expiry_days'])),
                ];
            },
        ]);

        add_settings_section('jbg_pts_disc_sec', '', '__return_false', self::OPTION);

        add_settings_field('points_per_unit', __('هر چند امتیاز', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="1" name="'.esc_attr(self::OPTION).'[points_per_unit]" value="'.esc_attr($o['points_per_unit']).'"> امتیاز';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('toman_per_unit', __('معادل چند تومان', 'jbg-ads'), function () {
            $o = self::get();
            // بدون محدودیت اجباری مرورگر
            echo '<input type="number" min="0" step="1" name="'.esc_attr(self::OPTION).'[toman_per_unit]" value="'.esc_attr($o['toman_per_unit']).'"> تومان';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('min_points', __('حداقل امتیاز برای تبدیل', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="0" name="'.esc_attr(self::OPTION).'[min_points]" value="'.esc_attr($o['min_points']).'"> امتیاز';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('expiry_days', __('انقضای کد (روز)', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="1" name="'.esc_attr(self::OPTION).'[expiry_days]" value="'.esc_attr($o['expiry_days']).'"> روز';
        }, self::OPTION, 'jbg_pts_disc_sec');
    }

    /** رندر صفحهٔ تنظیمات */
    public static function render_page(): void {
        echo '<div class="wrap">';
        echo '<h1>'.esc_html__('تنظیمات تبدیل امتیاز به مبلغ تخفیف', 'jbg-ads').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION);
        do_settings_sections(self::OPTION);
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
