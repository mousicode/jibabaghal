public static function bootstrap(): void {
    add_action('jbg_quiz_passed', [self::class, 'on_quiz_passed'], 10, 2);
}

public static function on_quiz_passed(int $user_id, int $ad_id): void {
    $pts = (int) get_post_meta($ad_id, 'jbg_points', true);
    if ($pts > 0) {
        // مجموع
        $total = (int) get_user_meta($user_id, 'jbg_points_total', true);
        update_user_meta($user_id, 'jbg_points_total', $total + $pts);
        // پر-آگهی
        update_user_meta($user_id, 'jbg_points_awarded_' . $ad_id, time());
    }
    // برای بازسازی پیشرفت:
    update_user_meta($user_id, 'jbg_quiz_passed_' . $ad_id, time());
    $list = get_user_meta($user_id, 'jbg_quiz_passed_ids', true);
    if (!is_array($list)) $list = [];
    if (!in_array($ad_id, $list, true)) {
        $list[] = $ad_id;
        update_user_meta($user_id, 'jbg_quiz_passed_ids', $list);
    }
}
