<?php
/**
 * Safe 2-column layout for jbg_ad:
 * Sidebar (related) on the LEFT, Main content (player + rest) on the RIGHT.
 * Does NOT alter existing player/quiz markup. Runs very late to avoid conflicts.
 */
namespace JBG\AdsFeed\Frontend;

class SingleLayout
{
    public static function bootstrap(): void
    {
        if (!is_singular('jbg_ad')) {
            return;
        }
        // خیلی دیر اجرا بشه تا هر افزونه/قالبی که محتوا/پلیر رو تزریق می‌کنه، کارش را کرده باشد.
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
            '0.4.0'
        );
    }

    public static function wrap_two_cols(string $content): string
    {
        // فقط در سینگل jbg_ad
        if (!is_singular('jbg_ad')) {
            return $content;
        }

        // اگر قبلاً رپ شده بود، دوباره رپ نکن
        if (strpos($content, 'class="jbg-two-col"') !== false) {
            return $content;
        }

        // Related list via shortcode (دست‌کاری داخل محتوا نمی‌کنیم)
        $related = do_shortcode('[jbg_related limit="10"]');

        // رپر سبک; محتوای اصلی دست‌نخورده میاد داخل ستون Main
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
