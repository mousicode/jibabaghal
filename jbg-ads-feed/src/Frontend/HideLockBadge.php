<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * کلاس سبک برای پنهان‌سازی نشان «قفل» در تمام صفحات
 * شامل کارت ویدیوها، صفحه‌ی تکی ویدیو، و هر باکس دیگری.
 */
class HideLockBadge {
    public static function register(): void {
        add_action('wp_head', [self::class, 'css'], 5);
    }

    public static function css(): void {
        echo '<style id="jbg-hide-lock">
        /* پنهان‌سازی سراسری badge قفل */
        .jbg-badge.lock {
          display: none !important;
          visibility: hidden !important;
          opacity: 0 !important;
        }
        </style>';
    }
}
