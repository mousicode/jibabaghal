<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * همسان‌سازی عرض محتوا با هدر/فوتر (1312px)
 * اجرای CSS با اولویت بالا و !important تا بر استایل قالب غالب شود.
 */
class UIAssets {

    public static function register(): void {
        // اولویت بالا تا بعد از استایل‌های قالب لود شود
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 999);
    }

    public static function enqueue(): void {
        $css = <<<CSS
:root { --jbg-content-width: 1312px; --jbg-content-pad: 16px; }

/* ظرف‌های افزونه */
.jbg-grid,
.jbg-related-grid,
.jbg-points-wrap,
.jbg-wallet,
.jbg-sponsor-report,
.jbg-ad-layout {
  max-width: var(--jbg-content-width) !important;
  margin-left: auto !important;
  margin-right: auto !important;
  padding-left: var(--jbg-content-pad) !important;
  padding-right: var(--jbg-content-pad) !important;
  box-sizing: border-box !important;
  width: 100% !important;
}

/* صفحهٔ تکی ویدیو با المنتور/Hello */
.single-jbg_ad .elementor-section .elementor-container {
  max-width: var(--jbg-content-width) !important;
  margin-left: auto !important;
  margin-right: auto !important;
  padding-left: var(--jbg-content-pad) !important;
  padding-right: var(--jbg-content-pad) !important;
  box-sizing: border-box !important;
}

/* اگر شورت‌کدها داخل ویجت المنتور باشند، این ظرف نیز هم‌عرض می‌شود */
.elementor-widget-container .jbg-grid,
.elementor-widget-container .jbg-related-grid,
.elementor-widget-container .jbg-points-wrap,
.elementor-widget-container .jbg-wallet,
.elementor-widget-container .jbg-sponsor-report {
  max-width: var(--jbg-content-width) !important;
}

/* جلوگیری از باریک شدن در موبایل‌ها */
@media (max-width: 640px) {
  :root { --jbg-content-pad: 12px; }
}
CSS;

        // هندل مجازی برای اینلاین‌کردن CSS
        wp_register_style('jbg-ui-fix-width', false, [], '1.0.0');
        wp_enqueue_style('jbg-ui-fix-width');
        wp_add_inline_style('jbg-ui-fix-width', $css);
    }
}
