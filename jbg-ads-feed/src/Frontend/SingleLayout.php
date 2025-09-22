<?php
/**
 * Single layout for jbg_ad (2-column: sidebar left + main)
 */
namespace JBG\AdsFeed\Frontend;

class SingleLayout
{
    public static function bootstrap(): void
    {
        if (!is_singular('jbg_ad')) {
            return;
        }
        add_filter('the_content', [self::class, 'render'], 999);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_css']);
    }

    public static function enqueue_css(): void
    {
        // اگر قبلاً ثابت مسیر تعریف نشده، حدس بزن
        if (!defined('JBG_ADS_FEED_URL')) {
            $url_guess = trailingslashit(plugins_url('/', dirname(dirname(__DIR__))));
            define('JBG_ADS_FEED_URL', $url_guess);
        }
        wp_enqueue_style(
            'jbg-ads-single',
            trailingslashit(JBG_ADS_FEED_URL) . 'assets/css/single.css',
            [],
            '0.2.0'
        );
    }

    public static function render(string $content): string
    {
        // شناسه آگهی
        $ad_id = get_the_ID();

        // بلوک سایدبار: ویدیوهای مرتبط (می‌تونی پارامتر limit/category دلخواه بدی)
        $sidebar = do_shortcode('[jbg_related limit="10"]');

        // محتوای ستون اصلی: خود محتوا + پلیر + کوییز + دکمه «ویدئوی بعدی»
        // اگر قبلاً Rendererها/HTML دیگری اضافه می‌کنی، همون‌ها درج می‌شن.
        $main = self::wrap_player($content) . self::quiz_hint() . self::next_btn($ad_id);

        // ساختار ۲ ستونه (سایدبار در چپ)
        $html = '
        <div class="jbg-ad-layout" dir="rtl">
            <aside class="jbg-ad-sidebar" aria-label="ویدیوهای مرتبط">
                <h3 class="jbg-ad-sidebar__title">' . esc_html__('ویدیوهای مرتبط', 'jbg') . '</h3>
                ' . $sidebar . '
            </aside>
            <main class="jbg-ad-main">
                ' . $main . '
            </main>
        </div>';

        return $html;
    }

    /**
     * ویدئو را در یک رپر 16:9 می‌پیچد تا نسبت تصویر حفظ شود
     */
    private static function wrap_player(string $content): string
    {
        // اگر قبلاً ویدئو/پلیر داری، همان را در یک پوشش 16:9 جاگذاری می‌کنیم
        // در غیر این صورت همان content را برمی‌گردانیم
        $hasVideo = (bool) preg_match('~<(video|iframe|div[^>]+class="[^"]*plyr[^"]*")[^>]*>~i', $content);
        if (!$hasVideo) {
            return $content;
        }
        return '<div class="jbg-player-wrap">' . $content . '</div>';
    }

    private static function quiz_hint(): string
    {
        // پیام «بعد از تماشای کامل…» که قبلاً داشتی
        return '<div class="jbg-quiz-hint">' .
            esc_html__('بعد از تماشای کامل این ویدئو، دکمه فعال می‌شود.', 'jbg') .
            '</div>';
    }

    private static function next_btn(int $ad_id): string
    {
        // دکمه «ویدئوی بعدی» که با رویداد jbg:quiz_passed آزاد می‌شود
        $next_url = self::calc_next_url($ad_id);
        $disabled = 'disabled aria-disabled="true"';
        return '
        <div class="jbg-next">
            <a class="jbg-next__btn" href="' . esc_url($next_url) . '" ' . $disabled . '>' .
                esc_html__('ویدئو بعدی', 'jbg') .
            '</a>
        </div>
        <script>
        (function(){
          document.addEventListener("jbg:quiz_passed", function(){
            var a = document.querySelector(".jbg-next__btn");
            if(a){ a.removeAttribute("disabled"); a.removeAttribute("aria-disabled"); a.classList.add("is-unlocked"); }
          });
        })();
        </script>';
    }

    private static function calc_next_url(int $ad_id): string
    {
        // همان منطقی که قبلاً برای انتخاب «بعدی» داشتی؛
        // در صورت نبود، می‌توان ساده‌ترین حالت را گذاشت (برگشت به آرشیو)
        $next = get_adjacent_post(true, '', false, 'jbg_cat'); // مثال: بر اساس همین کتگوری سفارشی
        if ($next) {
            return get_permalink($next);
        }
        return get_post_type_archive_link('jbg_ad') ?: home_url('/');
    }
}
