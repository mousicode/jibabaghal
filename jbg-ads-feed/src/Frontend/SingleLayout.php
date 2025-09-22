<?php
/**
 * Safe 2-column layout for jbg_ad
 * Sidebar (related) on the LEFT, Main content (player + the rest) on the RIGHT.
 * If [jbg_related] is empty, fallback to [jbg_list limit="5"].
 */
namespace JBG\AdsFeed\Frontend;

class SingleLayout
{
    public static function register(): void
    {
        if (!is_singular('jbg_ad')) return;
        // خیلی دیر اجرا می‌کنیم تا همه‌ی تزریق‌ها (پلیر/کوییز/…) انجام شده باشد
        add_filter('the_content', [self::class, 'wrap_two_cols'], 2000);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_css']);
    }

    public static function enqueue_css(): void
    {
        if (!defined('JBG_ADS_FEED_URL')) {
            $url_guess = trailingslashit(plugins_url('/', dirname(dirname(__DIR__))));
            define('JBG_ADS_FEED_URL', $url_guess);
        }
        wp_enqueue_style(
            'jbg-ads-single',
            trailingslashit(JBG_ADS_FEED_URL) . 'assets/css/single.css',
            [],
            '0.5.0'
        );
    }

    public static function wrap_two_cols(string $content): string
    {
        if (!is_singular('jbg_ad')) return $content;
        if (strpos($content, 'class="jbg-two-col"') !== false) return $content; // دوباره رپ نکن

        // 1) Related
        $related = do_shortcode('[jbg_related limit="10"]');

        // اگر خروجی مرتبط‌ها خالی بود (یا فقط whitespace)، برگرد به fallback
        $plain = trim(wp_strip_all_tags($related));
        if ($plain === '') {
            $related = do_shortcode('[jbg_list limit="5" title="پیشنهادی"]');
        }

        $out = '
        <div class="jbg-two-col" dir="rtl">
          <aside class="jbg-two-col__sidebar" aria-label="ویدیوهای مرتبط">
            <h3 class="jbg-two-col__title">'. esc_html__('ویدیوهای مرتبط', 'jbg') .'</h3>
            <div class="jbg-two-col__related">'. $related .'</div>
          </aside>
          <div class="jbg-two-col__main">
            '. $content .'
          </div>
        </div>';

        return $out;
    }
}
