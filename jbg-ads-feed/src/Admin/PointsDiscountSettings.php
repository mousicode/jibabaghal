<?php
namespace JBG\Ads\Admin;
if (!defined('ABSPATH')) exit;

class PointsDiscountSettings {

    const OPTION = 'jbg_points_discount';

    public static function register(): void {
        add_action('admin_init', [self::class, 'settings']);
        add_action('admin_menu', function () {
            add_submenu_page(
                'edit.php?post_type=jbg_ad',
                __('تبدیل امتیاز به تخفیف', 'jbg-ads'),
                __('تبدیل امتیاز به تخفیف', 'jbg-ads'),
                'manage_options',
                'jbg_points_discount',
                [self::class, 'render_page']
            );
        });
    }

    public static function defaults(): array {
        return [
            'points_per_unit'   => 1000, // هر ۱۰۰۰ امتیاز
            'percent_per_unit'  => 10,   // = ۱۰٪
            'max_percent'       => 50,   // سقف هر کد
            'min_points'        => 1000, // حداقل برای اولین تبدیل
            'expiry_days'       => 7,    // انقضای کد (روز)
        ];
    }

    public static function get(): array {
        $opt = get_option(self::OPTION, []);
        return wp_parse_args(is_array($opt) ? $opt : [], self::defaults());
    }

    public static function settings(): void {
        register_setting(self::OPTION, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => function ($v) {
                $d = self::defaults();
                $out = [
                    'points_per_unit'  => max(1, (int)($v['points_per_unit'] ?? $d['points_per_unit'])),
                    'percent_per_unit' => max(1, (int)($v['percent_per_unit'] ?? $d['percent_per_unit'])),
                    'max_percent'      => max(1, (int)($v['max_percent'] ?? $d['max_percent'])),
                    'min_points'       => max(0, (int)($v['min_points'] ?? $d['min_points'])),
                    'expiry_days'      => max(1, (int)($v['expiry_days'] ?? $d['expiry_days'])),
                ];
                if ($out['percent_per_unit'] > 100) $out['percent_per_unit'] = 100;
                if ($out['max_percent'] > 100) $out['max_percent'] = 100;
                return $out;
            },
        ]);

        add_settings_section('jbg_pts_disc_sec', '', '__return_false', self::OPTION);

        add_settings_field('points_per_unit', __('هر چند امتیاز', 'jbg-ads'), function () {
            $o = self::get(); echo '<input type="number" min="1" name="'.self::OPTION.'[points_per_unit]" value="'.esc_attr($o['points_per_unit']).'">';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('percent_per_unit', __('برابر با چند درصد', 'jbg-ads'), function () {
            $o = self::get(); echo '<input type="number" min="1" max="100" name="'.self::OPTION.'[percent_per_unit]" value="'.esc_attr($o['percent_per_unit']).'"> %';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('max_percent', __('سقف درصد هر کد', 'jbg-ads'), function () {
            $o = self::get(); echo '<input type="number" min="1" max="100" name="'.self::OPTION.'[max_percent]" value="'.esc_attr($o['max_percent']).'"> %';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('min_points', __('حداقل امتیاز برای تبدیل', 'jbg-ads'), function () {
            $o = self::get(); echo '<input type="number" min="0" name="'.self::OPTION.'[min_points]" value="'.esc_attr($o['min_points']).'">';
        }, self::OPTION, 'jbg_pts_disc_sec');

        add_settings_field('expiry_days', __('انقضای کد (روز)', 'jbg-ads'), function () {
            $o = self::get(); echo '<input type="number" min="1" name="'.self::OPTION.'[expiry_days]" value="'.esc_attr($o['expiry_days']).'">';
        }, self::OPTION, 'jbg_pts_disc_sec');
    }

    public static function render_page(): void {
        echo '<div class="wrap"><h1>'.esc_html__('تبدیل امتیاز به تخفیف', 'jbg-ads').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION);
        do_settings_sections(self::OPTION);
        submit_button();
        echo '</form></div>';
    }
}
