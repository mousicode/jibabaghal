<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\PlayerShim')):

/**
 * تزریق پلیر فقط وقتی که داخل محتوای پست هیچ ویدئو/iframe/پلیر از قبل وجود نداشته باشد.
 * اولویت 20 تا بعد از پردازش المنتور/فیلترهای معمول اجرا شود و دقیقاً داخل body/ابتدای content بیاید.
 */
class PlayerShim
{
    public static function register(): void
    {
        // اولویت را بالاتر می‌گذاریم تا خروجی نهاییِ content را بگیریم
        add_filter('the_content', [self::class, 'inject_player_if_missing'], 20);
    }

    public static function inject_player_if_missing($content)
    {
        // فقط روی single آگهی و کوئری اصلی
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // اگر همین حالا پلیر/ویدئو/iframe یا jbg-player/شورتکد وجود دارد: دست نزن
        $haystack = strtolower($content);
        $alreadyHasPlayer =
            strpos($haystack, '<video') !== false ||
            strpos($haystack, '<iframe') !== false ||
            strpos($haystack, 'wp-video-shortcode') !== false ||
            strpos($haystack, 'jbg-player') !== false ||
            // اگر کاربر خودش شورتکدی مثل [video] یا [jbg_player] گذاشته باشد
            strpos($haystack, '[video') !== false ||
            strpos($haystack, '[jbg_player') !== false;

        if ($alreadyHasPlayer) {
            return $content;
        }

        $id = get_the_ID();
        if (!$id) return $content;

        // منبع ویدئو را از متاها یا اولین ضمیمه پیدا کن
        $src = '';
        foreach ([
            get_post_meta($id, 'jbg_player', true),
            get_post_meta($id, 'jbg_video_url', true),
            get_post_meta($id, '_jbg_video_url', true),
            get_post_meta($id, 'video_url', true),
            get_post_meta($id, 'embed_html', true),
        ] as $v) {
            if (is_string($v) && trim($v) !== '') { $src = trim($v); break; }
        }

        if ($src === '') {
            $att = get_children([
                'post_parent'    => $id,
                'post_type'      => 'attachment',
                'post_mime_type' => 'video',
                'numberposts'    => 1,
                'orderby'        => 'menu_order ID',
                'order'          => 'ASC',
            ]);
            if ($att) {
                $first = array_shift($att);
                $u = wp_get_attachment_url($first->ID);
                if ($u) $src = $u;
            }
        }

        // اگر چیزی پیدا نشد، محتوا را دست نزن
        if ($src === '') return $content;

        // ساخت HTML پلیر
        if (stripos($src, '<iframe') !== false || stripos($src, '<video') !== false) {
            // خودِ HTML آماده است
            $playerHtml = $src;
        } else {
            if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $src)) {
                $playerHtml = wp_video_shortcode(['src' => esc_url_raw($src)]);
            } else {
                // oEmbed (مثل آپارات/یوتیوب و ...)
                $embed = wp_oembed_get($src);
                if ($embed) {
                    $playerHtml = $embed;
                } else {
                    // fallback: iframe ساده
                    $esc = esc_url($src);
                    $playerHtml = '<iframe src="'.$esc.'" style="width:100%;aspect-ratio:16/9" loading="lazy" allowfullscreen></iframe>';
                }
            }
        }

        // یک‌بارِ دیگر مطمئن شویم که دو بار تزریق نکنیم (در صورت اجرای مجدد فیلتر توسط پلاگینی دیگر)
        if (strpos($content, 'class="jbg-player"') !== false) {
            return $content;
        }

        // پلیر را در ابتدای content قرار می‌دهیم (داخل body و قبل از هدر/کوییز/سایدبار)
        $wrapper = '<div class="jbg-player" style="margin:12px 0;">'.$playerHtml.'</div>'."\n";
        return $wrapper . $content;
    }
}

endif;
