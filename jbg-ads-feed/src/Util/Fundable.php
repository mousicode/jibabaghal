<?php
namespace JBG\Ads\Util;


class Fundable {
public static function refresh_flag(int $post_id): void {
$cpv = (int) get_post_meta($post_id, 'jbg_cpv', true);
$br = (int) get_post_meta($post_id, 'jbg_budget_remaining', true);
update_post_meta($post_id, 'jbg_is_fundable', ($br >= $cpv && $cpv > 0) ? 1 : 0);
}
}