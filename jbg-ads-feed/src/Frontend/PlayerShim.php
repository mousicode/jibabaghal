<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\PlayerShim')):

/**
 * PlayerShim: اگر در the_content هیچ پلیر/iframe نبود، از متا/URL ویدیو
 * یک پلیر می‌سازد و قبل از محتوا تزریق می‌کند تا ویدیو «همیشه» نمایش داده شود.
 * هیچ شرط دسترسی/قفل‌گذاری در اینجا وجود ندارد.
 */
class PlayerShim {

    public static function register(): void {
        // عمداً خیلی زود اجرا نمی‌کنیم؛ اما قبل از SingleLayout (که 99 است) محتوا آماده می‌شود.
        add_filter('the_content', [self::class, 'inject_player_if_missing'], 5);
    }

    public static function inject_player_if_missing($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        // اگر الان هم پلیر/iframe داخل محتوا هست، کاری نکنیم
        $hasPlayer =
            (stripos($content, '<video') !== false) ||
            (stripos($content, '<iframe') !== false) ||
            (stripos($content, 'wp-video-shortcode') !== false);
        if ($hasPlayer) return $content;

        $id = get_the_ID();

        // کاندیدهای رایج برای ذخیرهٔ سورس ویدیو/Html
        $cands = [
            get_post_meta($id, 'jbg_player', true),
            get_post_meta($id, 'jbg_video_url', true),
            get_post_meta($id, '_jbg_video_url', true),
            get_post_meta($id, 'video_url', true),
            get_post_meta($id, 'embed_html', true),
        ];

        $src = '';
        foreach ($cands as $v) {
            if (is_string($v) && trim($v) !== '') { $src = trim($v); break; }
        }
        if ($src === '') return $content; // چیزی برای ساخت پلیر نداریم

        // اگر html خامِ iframe/video ذخیره شده باشد
        if (stripos($src, '<iframe') !== false || stripos($src, '<video') !== false) {
            $player = $src;
        } else {
            // اگر فایل محلی/مستقیم است، از شورتکد ویدیو استفاده کن
            if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $src)) {
                $player = wp_video_shortcode(['src' => esc_url_raw($src)]);
            } else {
                // لینک‌های یوتیوب/آپارات/و… → oEmbed
                $player = wp_oembed_get($src);
                if (!$player) {
                    // در صورت شکست oEmbed، یک iframe ساده بساز
                    $esc = esc_url($src);
                    $player = '<iframe src="'.$esc.'" style="width:100%;aspect-ratio:16/9" loading="lazy" allowfullscreen></iframe>';
                }
            }
        }

        // استایل خیلی مینیمال برای اینکه ارتفاع صفر نشود
        $wrap = '<div class="jbg-player" style="margin:12px 0">'.$player.'</div>';

        // پلیر را ابتدای محتوا قرار بده
        return $wrap . "\n" . $content;
    }
}

endif;
