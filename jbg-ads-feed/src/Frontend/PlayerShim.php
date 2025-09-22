<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\PlayerShim')):

/**
 * PlayerShim
 * پلیر را فقط داخل محتوای «لوپ اصلی» همان پست jbg_ad تزریق می‌کند؛
 * هیچ تزریقی داخل هدر/فوتر Elementor یا سایر کانتکست‌ها انجام نمی‌شود.
 */
class PlayerShim {

    public static function register(): void {
        // فقط the_content (دیگر elementor/frontend/the_content را دستکاری نمی‌کنیم)
        add_filter('the_content', [self::class, 'inject'], 999);
    }

    public static function inject($content) {
        // فقط سینگل jbg_ad
        if (!is_singular('jbg_ad')) return $content;

        // فقط داخل لوپ اصلیِ کوئری اصلی (تا در هدر/فوتر/ویجت‌ها اجرا نشود)
        if (!in_the_loop() || !is_main_query()) return $content;

        global $post;
        $main_id = (int) get_queried_object_id();
        if (!$post || $post->post_type !== 'jbg_ad' || (int)$post->ID !== $main_id) {
            return $content;
        }

        // گارد تک‌بار
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

        // پلیر را ابتدای بدنهٔ پست قرار می‌دهیم (داخل body و قبل از related)
        return $html . "\n" . $content;
    }

    /** استخراج آدرس ویدیو از متا / ضمیمه / URL داخل محتوا */
    private static function resolve_video_src(int $post_id, string $content): string {
        $m = (string) get_post_meta($post_id, 'jbg_video_src', true);
        if (is_string($m) && trim($m) !== '') return trim($m);

        foreach (['jbg_player','jbg_video_url','_jbg_video_url','video_url','embed_html'] as $k) {
            $v = get_post_meta($post_id, $k, true);
            if (is_string($v) && trim($v) !== '') return trim($v);
        }

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

        if (preg_match('~https?://[^\s"<]+~i', $content, $m)) return $m[0];

        return '';
    }
}

endif;
