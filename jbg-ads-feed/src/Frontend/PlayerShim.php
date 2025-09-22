<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * تضمین می‌کند در صفحات jbg_ad «پلیر» نمایش داده شود:
 * 1) اگر خودِ محتوا ویدئو/ایفریم دارد → دست نمی‌زنیم.
 * 2) وگرنه از embed_html یا jbg_video_url استفاده می‌کنیم (oEmbed → wp_video → iframe fallback).
 * 3) اگر هیچ منبعی نبود، یک هشدار ادمین (فقط برای کاربران قادر به ویرایش) نشان می‌دهیم.
 */
class PlayerShim
{
    public static function register(): void
    {
        // بعد از اغلب فیلترهای محتوا اجرا شود تا تداخل نباشد
        \add_filter('the_content', [self::class, 'inject'], 20);
    }

    public static function has_player_markup(string $html): bool
    {
        return (bool) \preg_match('~<(video|iframe|div[^>]+class="[^"]*(plyr|video|embed)[^"]*")[^>]*>~i', $html);
    }

    public static function inject(string $content): string
    {
        if (!\is_singular('jbg_ad')) return $content;

        // اگر قبلاً پلیر/ایفریم وجود دارد، همان را نگه دار
        if (self::has_player_markup($content)) {
            return $content;
        }

        $post_id = \get_the_ID();

        // 1) Embed HTML (اولویت)
        $embed = (string) \get_post_meta($post_id, 'embed_html', true);
        if ($embed) {
            return self::wrap_player($embed) . $content;
        }

        // 2) Direct URL
        $url = (string) \get_post_meta($post_id, 'jbg_video_url', true);
        if ($url) {
            // a) oEmbed (یوتیوب/آپارات…)
            $oembed = \wp_oembed_get($url);
            if ($oembed) {
                return self::wrap_player($oembed) . $content;
            }
            // b) wp_video_shortcode برای فایل mp4/webm…
            $short = \wp_video_shortcode(['src' => $url, 'preload' => 'metadata']);
            if ($short) {
                return self::wrap_player($short) . $content;
            }
            // c) iframe fallback
            $iframe = '<iframe src="'. \esc_url($url) .'" allowfullscreen loading="lazy" style="width:100%;height:100%;border:0"></iframe>';
            return self::wrap_player($iframe) . $content;
        }

        // 3) اگر هیچ منبعی نبود: پیام برای ادمین تا بدونه چه متاهایی رو باید پر کنه
        if (\current_user_can('edit_post', $post_id)) {
            $note = '<div class="jbg-admin-warn" style="background:#fff3cd;border:1px solid #ffeeba;padding:12px;border-radius:8px;margin:8px 0;">
                        <strong>JBG:</strong> No video source found. Fill <code>embed_html</code> or <code>jbg_video_url</code> in the “Video / Embed” metabox.
                     </div>';
            return $note . $content;
        }

        return $content;
    }

    private static function wrap_player(string $inner): string
    {
        // رپر سبک 16:9 (بدون دست‌کاریِ داخلی پلیر)
        $wrap = '<div class="jbg-player-wrap" style="position:relative;width:100%;aspect-ratio:16/9;background:#000;">'
              . '<div class="jbg-player-viewport" style="position:absolute;inset:0;width:100%;height:100%;">'
              . $inner
              . '</div></div>';
        return $wrap;
    }
}
