<?php
/**
 * Safe 2-column layout for jbg_ad: sidebar (related) on the left, main content on the right
 * – Does NOT alter existing player/quiz markup. Just wraps and places the related list alongside.
 */
namespace JBG\AdsFeed\Frontend;

class SingleLayout
{
    public static function bootstrap(): void
    {
        if (!is_singular('jbg_ad')) {
            return;
        }
        // run after most content filters but before very-late filters
        add_filter('the_content', [self::class, 'wrap_two_cols'], 50);
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
            '0.3.0'
        );
    }

    /**
     * Wrap original content + add related sidebar – without touching the player's HTML.
     */
    public static function wrap_two_cols(string $content): string
    {
        // Build the related block via shortcode (no assumptions about theme markup)
        $related = do_shortcode('[jbg_related limit="10"]');

        // Just wrap – keep the original $content untouched
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
