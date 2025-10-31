<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class ViewBadge {
    public static function register(): void {
        add_filter('the_content', [self::class, 'inject'], 7);
    }

    public static function inject($content) {
        // اگر SingleLayout ادغام‌شده فعال است، هیچ تزریقی انجام نده
        if (!empty($GLOBALS['JBG_DISABLE_VIEWBADGE'])) {
            return $content;
        }
        return $content; // سازگاری قدیمی؛ عمداً خروجی اضافه نمی‌کنیم
    }
}
