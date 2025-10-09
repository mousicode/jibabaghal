<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class UIAssets
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 20);
    }

    public static function enqueue(): void
    {
        // یک هندل مجازی برای اینلاین‌کردن CSS
        wp_register_style('jbg-ui', false, [], '1.0.0');
        $css = self::css();
        wp_add_inline_style('jbg-ui', $css);
        wp_enqueue_style('jbg-ui');
    }

    private static function css(): string
    {
        return <<<CSS
/* === JBG UI – unified content width ======================================= */
:root {
  --jbg-content-width: 1312px;   /* عرض استاندارد شما (هدر/فوتر) */
  --jbg-content-pad: 16px;
}

/* کانتینر عمومی برای همه خروجی‌های افزونه */
.jbg-container,
.jbg-wallet,
.jbg-points-wrap,
.jbg-sponsor-report,
.jbg-react-inline,
.jbg-related-wrap,
.jbg-list-wrap,
.jbg-single-wrap {
  max-width: var(--jbg-content-width);
  margin-left: auto;
  margin-right: auto;
  padding-left: var(--jbg-content-pad);
  padding-right: var(--jbg-content-pad);
  box-sizing: border-box;
}

/* صفحهٔ تکی آگهی (post type: jbg_ad) — هم‌عرض‌سازی محتوای اصلی با هدر/فوتر */
.single-jbg_ad .entry-content > *:not(.alignfull):not(.alignwide) {
  max-width: var(--jbg-content-width);
  margin-left: auto;
  margin-right: auto;
  padding-left: var(--jbg-content-pad);
  padding-right: var(--jbg-content-pad);
  box-sizing: border-box;
}

/* اگر قالب (Hello/Elementor) کانتینر محدودتری داشته باشد، این کلاس‌ها بازهم غالب می‌شوند */
body .elementor-section.jbg-wide,
body .jbg-wide {
  --container-max-width: var(--jbg-content-width);
  max-width: var(--jbg-content-width) !important;
  margin-left: auto !important;
  margin-right: auto !important;
  padding-left: var(--jbg-content-pad);
  padding-right: var(--jbg-content-pad);
}

/* جلوگیری از دوبل‌شدن پدینگ در موبایل‌ها */
@media (max-width: 640px) {
  :root { --jbg-content-pad: 12px; }
}
CSS;
    }
}
