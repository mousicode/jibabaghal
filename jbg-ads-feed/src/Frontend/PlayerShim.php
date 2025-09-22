<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\PlayerShim')):

/**
 * PlayerShim:
 * اگر خروجی سینگل jbg_ad پلیر ندارد، یک پلیر استاندارد را فقط برای «پست اصلیِ همان صفحه» تزریق می‌کند.
 * - سازگار با Elementor (both the_content & elementor/frontend/the_content)
 * - جلوگیری از تزریق داخل هدر/فوتر Elementor با چک کردن نوع/ID پست جاری
 * - گارد تک‌بار برای جلوگیری از تزریق چندباره
 */
class PlayerShim {

    public static function register(): void {
        // با اولویت بالا تا مطمئن باشیم نسخه نهایی محتوا را می‌بینیم
        add_filter('the_content', [self::class, 'inject'], 999);
        add_filter('elementor/frontend/the_content', [self::class, 'inject'], 999);
    }

    public static function inject($content) {
        // فقط روی سینگل آگهی
        if (!is_singular('jbg_ad')) return $content;

        global $post;
        $main_id = (int) get_queried_object_id();

        // ⚠️ مهم: فقط اگر «پست در حال فیلتر» خودش jbg_ad اصلی همین صفحه است، اجازه بده
        if (!$post || $post->post_type !== 'jbg_ad' || (int)$post->ID !== $main_id) {
            return $content; // از تزریق در هدر/فوتر/قالب‌های elementor_library جلوگیری می‌کند
        }

        // گارد تک‌بار: اگر قبلاً برای این پست تزریق شده، دیگر تکرار نکن
        static $done = [];
        if (isset($done[$main_id])) return $content;

        // اگر خود محتوا پلیر دارد، کاری نکن
        if (strpos($content, 'class="jbg-player-wrapper"') !== false ||
            preg_match('~<(video|iframe|amp-video)\b~i', $content) ||
            strpos($content, 'wp-video-shortcode') !== false) {
            $done[$main_id] = true;
            return $content;
        }

        $src = self::resolve_video_src($main_id, $content);
        if (!$src) return $content;

        // ساخت پلیر
        if (stripos($src, '<iframe') !== false || stripos($src, '<video') !== false) {
            $player = $src;
        } elseif (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $src)) {
            $player = wp_video_shortcode(['src' => esc_url_raw($src), 'preload' => 'metadata']);
        } elseif (preg_match('/\.m3u8(\?.*)?$/i', $src)) {
            $player =
              '<video id="jbg-player" controls playsinline preload="metadata"></video>'.
              '<script>(function(){var v=document.getElementById("jbg-player");if(!v)return;var u="'.esc_js($src).'";'.
              'if(window.Hls&&window.Hls.isSupported()){var h=new Hls();h.loadSource(u);h.attachMedia(v);}else{v.src=u;}'.
              '})();</script>';
        } else {
            $embed = wp_oembed_get($src);
            $player = $embed ? $embed : '<iframe src="'.esc_url($src).'" style="width:100%;aspect-ratio:16/9" loading="lazy" allowfullscreen></iframe>';
        }

        $html =
          '<div class="jbg-player">'.
            '<div class="jbg-player-wrapper">'.$player.'</div>'.
          '</div>'."\n".
          '<!-- jbg-player: injected for post '.$main_id.' -->';

        $done[$main_id] = true;
        return $html . "\n" . $content;
    }

    /** منبع ویدیو را از متای درست، ضمیمه یا اولین URL داخل محتوا پیدا می‌کند */
    private static function resolve_video_src(int $post_id, string $content): string {
        // 1) کلید اصلی رایج
        $m = (string) get_post_meta($post_id, 'jbg_video_src', true);
        if (is_string($m) && trim($m) !== '') return trim($m);

        // 2) سایر کلیدهای احتمالی
        foreach (['jbg_player','jbg_video_url','_jbg_video_url','video_url','embed_html'] as $k) {
            $v = get_post_meta($post_id, $k, true);
            if (is_string($v) && trim($v) !== '') return trim($v);
        }

        // 3) اولین ضمیمه ویدیویی متصل به همین پست
        $att = get_children([
            'post_parent'   => $post_id,
            'post_type'     => 'attachment',
            'post_mime_type'=> 'video',
            'numberposts'   => 1,
            'orderby'       => 'menu_order ID',
            'order'         => 'ASC',
        ]);
        if ($att) {
            $first = array_shift($att);
            $url   = wp_get_attachment_url($first->ID);
            if ($url) return $url;
        }

        // 4) اولین URL داخل محتوا (fallback برای oEmbed/MP4)
        if (preg_match('~https?://[^\s"<]+~i', $content, $m)) return $m[0];

        return '';
    }
}

endif;
