$cfg = PointsDiscountSettings::get();

$total = Points::total($uid);
if ($total < $cfg['min_points']) {
    return new \WP_REST_Response(['ok'=>false,'reason'=>'not_enough_points','total'=>$total],400);
}

$units = intdiv($total, $cfg['points_per_unit']);
if ($units <= 0) {
    return new \WP_REST_Response(['ok'=>false,'reason'=>'below_unit'],400);
}

$amount = $units * $cfg['toman_per_unit'];
$amount = min($amount, $cfg['max_toman']);
$points_to_deduct = $units * $cfg['points_per_unit'];
if ($points_to_deduct > $total) $points_to_deduct = $total;

Points::deduct($uid, $points_to_deduct, 'redeem');

$code = self::generate_code();
$expiry = (new \DateTimeImmutable('+'.(int)$cfg['expiry_days'].' days', wp_timezone()))->format('Y-m-d');

$created = false;
if (class_exists('WC_Coupon')) {
    $coupon = new \WC_Coupon();
    $coupon->set_code($code);
    $coupon->set_discount_type('fixed_cart');
    $coupon->set_amount($amount);
    $coupon->set_usage_limit(1);
    $coupon->set_date_expires($expiry);
    $coupon->save();
    $created = true;
}

$list = (array)get_user_meta($uid, 'jbg_coupons', true);
$list[] = [
    'code'=>$code,
    'amount'=>$amount,
    'points'=>$points_to_deduct,
    'expiry'=>$expiry,
    'wc'=>$created?1:0,
    'time'=>time(),
];
update_user_meta($uid, 'jbg_coupons', $list);

return new \WP_REST_Response([
    'ok'=>true,
    'code'=>$code,
    'amount'=>$amount,
    'points'=>$points_to_deduct,
    'expiry'=>$expiry,
    'wc'=>$created?1:0,
    'total'=>Points::total($uid),
],200);
