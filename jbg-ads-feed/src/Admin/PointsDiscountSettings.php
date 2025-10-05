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
            'toman_per_unit'  => 100000,   // = ۱۰۰٬۰۰۰ تومان
            'max_toman'       => 5000000,  // سقف مبلغ هر کد
            'min_points'      => 1000,     // حداقل امتیاز برای تبدیل
            'expiry_days'     => 7,        // انقضای کد (روز)
        ];
    }

    /** خواندن تنظیمات با ادغام پیش‌فرض‌ها */
    public static function get(): array {
        $opt = get_option(self::OPTION, []);
        return wp_parse_args(is_array($opt) ? $opt : [], self::defaults());
    }

    /** ثبت صفحهٔ تنظیمات در منوی «آگهی‌ها» */
    public static function menu(): void {
        add_submenu_page(
            'edit.php?post_type=jbg_ad',
            __('تبدیل امتیاز به کد تخفیف', 'jbg-ads'),
            __('تبدیل امتیاز به تخفیف', 'jbg-ads'),
            'manage_options',
            'jbg_points_discount',
            [self::class, 'render_page']
        );
    }

    /** رجیستر ستینگ‌ها و فیلدها */
    public static function settings(): void {
        register_setting(self::OPTION, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => function ($v) {
                $d = self::defaults();
                $out = [
                    'points_per_unit' => max(1,   (int)($v['points_per_unit'] ?? $d['points_per_unit'])),
                    'toman_per_unit'  => max(100, (int)($v['toman_per_unit']  ?? $d['toman_per_unit'])),
                    'max_toman'       => max(0,   (int)($v['max_toman']       ?? $d['max_toman'])),
                    'min_points'      => max(0,   (int)($v['min_points']      ?? $d['min_points'])),
                    'expiry_days'     => max(1,   (int)($v['expiry_days']     ?? $d['expiry_days'])),
                ];
                return $out;
            },
        ]);

        add_settings_section('jbg_pts_disc_sec', '', '__return_false', self::OPTION);

        add_settings_field('points_per_unit', __('هر چند امتیاز', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="1" name="'.esc_attr(self::OPTION).'[points_per_unit]" value="'.esc_attr($o['points_per_unit']).'"> ';
            echo '<span>'.esc_html__('امتیاز', 'jbg-ads').'</span>';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('toman_per_unit', __('معادل چند تومان', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="0" step="1000" name="'.esc_attr(self::OPTION).'[toman_per_unit]" value="'.esc_attr($o['toman_per_unit']).'"> ';
            echo '<span>'.esc_html__('تومان', 'jbg-ads').'</span>';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('max_toman', __('سقف مبلغ هر کد', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="0" step="1000" name="'.esc_attr(self::OPTION).'[max_toman]" value="'.esc_attr($o['max_toman']).'"> ';
            echo '<span>'.esc_html__('تومان', 'jbg-ads').'</span>';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('min_points', __('حداقل امتیاز برای تبدیل', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="0" name="'.esc_attr(self::OPTION).'[min_points]" value="'.esc_attr($o['min_points']).'"> ';
            echo '<span>'.esc_html__('امتیاز', 'jbg-ads').'</span>';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('expiry_days', __('انقضای کد (روز)', 'jbg-ads'), function () {
            $o = self::get();
            echo '<input type="number" min="1" name="'.esc_attr(self::OPTION).'[expiry_days]" value="'.esc_attr($o['expiry_days']).'"> ';
            echo '<span>'.esc_html__('روز', 'jbg-ads').'</span>';
        }, self::OPTION, 'jbg_pts_disc_sec');
    }

    /** رندر صفحهٔ تنظیمات */
    public static function render_page(): void {
        echo '<div class="wrap">';
        echo '<h1>'.esc_html__('تبدیل امتیاز به کد تخفیف', 'jbg-ads').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION);
        do_settings_sections(self::OPTION);
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
