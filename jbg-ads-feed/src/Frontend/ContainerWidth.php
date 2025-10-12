<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * همسان‌سازی عرض خروجی شورت‌کدهای افزونه و صفحهٔ تکی jbg_ad با هدر/فوتر (1312px)
 * بدون هیچ تغییری در منطق شورت‌کدها/لی‌آوت.
 */
class ContainerWidth
{
    public static function register(): void
    {
        // شورت‌کدها: خروجی [jbg_list]، [jbg_related]، [jbg_points]، [jbg_wallet] را wrap کن
        add_filter('do_shortcode_tag', [self::class, 'wrap_shortcode_output'], 999, 4);

        // صفحهٔ تکی jbg_ad: کل محتوای the_content را wrap کن
        add_filter('the_content', [self::class, 'wrap_single_content'], 999);
        
        // CSS سبک (یک‌بار)
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_inline_css'], 20);
    }

    /** CSS ظرف 1312px را اینلاین می‌کنیم (یک‌بار) */
    public static function enqueue_inline_css(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $css = '
        :root { --jbg-content-width: 1312px; --jbg-content-pad: 16px; }
        .jbg-container-1312{max-width:var(--jbg-content-width);margin:0 auto;padding:0 var(--jbg-content-pad);box-sizing:border-box;width:100%}
        ';
        wp_register_style('jbg-container-width', false, [], '1.0.0');
        wp_add_inline_style('jbg-container-width', trim($css));
        wp_enqueue_style('jbg-container-width');
    }

    /** شورت‌کدها را فقط برای همین 4 شورت‌کد افزونه wrap می‌کنیم */
    public static function wrap_shortcode_output($output, $tag, $attr, $m)
    {
        // فقط روی شورت‌کدهای افزونه اعمال شود
        $targets = ['jbg_list', 'jbg_related', 'jbg_points', 'jbg_wallet'];
        if (!in_array($tag, $targets, true)) {
            return $output;
        }

        // اگر قبلاً wrap شده، کاری نکن
        if (strpos($output, 'class="jbg-container-1312"') !== false) {
            return $output;
        }

        // خروجی خالی را wrap نکن
        if (!trim(strip_tags((string)$output))) {
            return $output;
        }

        return '<div class="jbg-container-1312">'.$output.'</div>';
    }

    /** محتوا در صفحهٔ تکی jbg_ad را wrap می‌کند (بدون دست‌کاری markup پلیر/کوییز) */
    public static function wrap_single_content($content)
    {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (strpos($content, 'class="jbg-container-1312"') !== false) {
            return $content;
        }

        // اگر محتوا واقعاً خالی است، wrap نکن
        if (!trim(strip_tags((string)$content))) {
            return $content;
        }

        return '<div class="jbg-container-1312">'.$content.'</div>';
    }
}
