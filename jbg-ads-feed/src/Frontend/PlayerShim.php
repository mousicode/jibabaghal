<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\PlayerShim')):

/**
 * PlayerShim:
 * اگر خروجی سینگل jbg_ad پلیر ندارد، همیشه یک پلیر می‌سازد و ابتدای محتوا تزریق می‌کند.
 * - با المنتور سازگار (روی فیلتر elementor هم سوار می‌شود)
 * - اول از jbg_video_src می‌خواند؛ سپس سایر متاها / ضمیمه / اولین URL داخل محتوا
 * - از اسکین فعلی استفاده می‌کند (Plyr/HLS) بدون تغییر در seekbar/کوییز/دکمه‌ها
 */
class PlayerShim {

    public static function register(): void {
        // محتوای معمولی و المنتور؛ با اولویت بالا تا در نهایت حتماً تزریق شود
        add_filter('the_content', [self::class, 'inject'], 999);
        add_filter('elementor/frontend/the_content', [self::class, 'inject'], 999);
    }

    public static function inject($content) {
        // فقط روی سینگل jbg_ad
        if (!is_singular('jbg_ad')) return $content;

        // اگر از قبل پلیر داخل محتوا هست، دست نزن
        if (strpos($content, 'class="jbg-player-wrapper"') !== false ||
            preg_match('~<(video|iframe|amp-video)\b~i', $content) ||
            strpos($content, 'wp-video-shortcode') !== false) {
            return $content;
        }

        $post_id = get_queried_object_id() ?: get_the_ID();
        $src     = self::resolve_video_src($post_id, $content);
        if (!$src) return $content;

        // اگر HTML خام (iframe/video) ذخیره شده باشد
        if (stripos($src, '<iframe') !== false || stripos($src, '<video') !== false) {
            $player = $src;
        } else if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $src)) {
            // فایل مستقیم
            $player = wp_video_shortcode(['src' => esc_url_raw($src), 'preload' => 'metadata']);
        } else if (preg_match('/\.m3u8(\?.*)?$/i', $src)) {
            // HLS: hls.js یا native (Safari)
            $player =
              '<video id="jbg-player" controls playsinline preload="metadata"></video>'.
              '<script>(function(){var v=document.getElementById("jbg-player");if(!v)return;var u="'.esc_js($src).'";'.
              'if(window.Hls&&window.Hls.isSupported()){var h=new Hls();h.loadSource(u);h.attachMedia(v);}else{v.src=u;}'.
              '})();</script>';
        } else {
            // oEmbed (آپارات/یوتیوب/...)
            $embed = wp_oembed_get($src);
            $player = $embed ? $embed : '<iframe src="'.esc_url($src).'" style="width:100%;aspect-ratio:16/9" loading="lazy" allowfullscreen></iframe>';
        }

        $html =
          '<div class="jbg-player">'.
            '<div class="jbg-player-wrapper">'.$player.'</div>'.
          '</div>'."\n".
          '<!-- jbg-player: injected for post '.$post_id.' -->';

        return $html . "\n" . $content;
    }

    /** منبع ویدیو را از متای درست، ضمیمه یا اولین URL داخل محتوا پیدا می‌کند */
    private static function resolve_video_src(int $post_id, string $content): string {
        // 1) کلید اصلی (در اکثر نصب‌ها همین است)
        $m = (string) get_post_meta($post_id, 'jbg_video_src', true);
        if (is_string($m) && trim($m) !== '') return trim($m);

        // 2) سایر کلیدهای متداول
        $keys = ['jbg_player','jbg_video_url','_jbg_video_url','video_url','embed_html'];
        foreach ($keys as $k) {
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

        // 4) اولین URL داخل محتوا (برای oEmbed/MP4)
        if (preg_match('~https?://[^\s"<]+~i', $content, $m)) return $m[0];

        return '';
    }
}

endif;
