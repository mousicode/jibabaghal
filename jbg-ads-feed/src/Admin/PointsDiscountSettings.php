public static function defaults(): array {
    return [
        'points_per_unit'  => 1000, // هر ۱۰۰۰ امتیاز
        'toman_per_unit'   => 100000, // برابر با ۱۰۰ هزار تومان
        'max_toman'        => 5000000, // سقف هر کد (اختیاری)
        'min_points'       => 1000,
        'expiry_days'      => 7,
    ];
}

public static function settings(): void {
    register_setting(self::OPTION, self::OPTION, [
        'type'              => 'array',
        'sanitize_callback' => function ($v) {
            $d = self::defaults();
            return [
                'points_per_unit' => max(1, (int)($v['points_per_unit'] ?? $d['points_per_unit'])),
                'toman_per_unit'  => max(1000, (int)($v['toman_per_unit'] ?? $d['toman_per_unit'])),
                'max_toman'       => max(0, (int)($v['max_toman'] ?? $d['max_toman'])),
                'min_points'      => max(0, (int)($v['min_points'] ?? $d['min_points'])),
                'expiry_days'     => max(1, (int)($v['expiry_days'] ?? $d['expiry_days'])),
            ];
        },
    ]);

    add_settings_section('jbg_pts_disc_sec', '', '__return_false', self::OPTION);

    add_settings_field('points_per_unit', 'هر چند امتیاز', function () {
        $o = self::get();
        echo '<input type="number" name="'.self::OPTION.'[points_per_unit]" value="'.esc_attr($o['points_per_unit']).'"> امتیاز';
    }, self::OPTION, 'jbg_pts_disc_sec');

    add_settings_field('toman_per_unit', 'معادل چند تومان', function () {
        $o = self::get();
        echo '<input type="number" name="'.self::OPTION.'[toman_per_unit]" value="'.esc_attr($o['toman_per_unit']).'"> تومان';
    }, self::OPTION, 'jbg_pts_disc_sec');

    add_settings_field('max_toman', 'سقف مبلغ هر کد', function () {
        $o = self::get();
        echo '<input type="number" name="'.self::OPTION.'[max_toman]" value="'.esc_attr($o['max_toman']).'"> تومان';
    }, self::OPTION, 'jbg_pts_disc_sec');

    add_settings_field('min_points', 'حداقل امتیاز برای تبدیل', function () {
        $o = self::get();
        echo '<input type="number" name="'.self::OPTION.'[min_points]" value="'.esc_attr($o['min_points']).'"> امتیاز';
    }, self::OPTION, 'jbg_pts_disc_sec');

    add_settings_field('expiry_days', 'انقضای کد (روز)', function () {
        $o = self::get();
        echo '<input type="number" name="'.self::OPTION.'[expiry_days]" value="'.esc_attr($o['expiry_days']).'"> روز';
    }, self::OPTION, 'jbg_pts_disc_sec');
}
