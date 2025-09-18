<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\AccessGate')):

class AccessGate {
    public static function register(): void {
        add_action('template_redirect', [self::class, 'guard'], 9);
    }

    public static function guard(): void {
        if (!is_singular('jbg_ad')) return;
        // … منطق فعلی‌ات همینی که هست بماند …
    }
}
endif;
