<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\AccessGate')):

/**
 * AccessGate دیگر هیچ مانعی برای نمایش ویدیو ایجاد نمی‌کند.
 * اگر در نسخه‌های قبلی template_redirect داشتیم، اینجا عمداً حذف شده تا پلیر همیشه لود شود.
 */
class AccessGate {
    public static function register(): void {
        // عمداً خالی؛ نه template_redirect و نه فیلتر دیگری
    }
}

endif;
