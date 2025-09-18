<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\PlayerShim')):

class PlayerShim {

    public static function register(): void {
        // هم محتوای معمولی، هم محتوای رندر شده توسط Elementor
        add_filter('the_content', [self::class, 'inject'], 3);
        add_filter('elementor/frontend/the_content', [self::class, 'inject'], 3);
    }

    public static function inject($content) {
        // فقط روی سینگل jbg_ad کار کن
        if (!is_singular('jbg_ad')) return $content;

        // اگر خود محتوا پلیر/iframe دارد، دست نزن
        if (stripos($content, '<video') !== false ||
            stripos($content, '<iframe') !== false ||
            stripos($content, 'wp-video-shortcode') !== false) {
            return $content;
        }

        $id  = get_queried_object_id();
        $src = self::resolve_video_src($id, $content);

        if (!$src) return $content;

        // اگر HTML خامِ پلیر ذخیره شده باشد
        if (stripos($src, '<iframe') !== false || stripos($src, '<video') !== false) {
            $player = $src;
        } else {
            if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $src)) {
                $player = wp_video_shortcode(['src' => esc_url_raw($src)]);
            } else {
                $player = wp_oembed_get($src);
                if (!$player) {
                    $esc = esc_url($src);
                    $player = '<iframe src="'.$esc.'" style="width:100%;aspect-ratio:16/9" loading="lazy" allowfullscreen></iframe>';
                }
            }
        }

        // یک کامنت برای دیباگ تا در View Source بشه دید
        $marker = "<!-- jbg-player: injected for post {$id} -->";

        return '<div class="jbg-player" style="margin:12px 0">'.$player.'</div>'."\n".$marker."\n".$content;
    }

    /** منبع ویدیو را از متاها، ضمیمه، یا اولین URL داخل محتوا پیدا می‌کند */
    private static function resolve_video_src(int $post_id, string $content): string {
        // 1) متاهای رایج
        foreach ([
            get_post_meta($post_id, 'jbg_player', true),
            get_post_meta($post_id, 'jbg_video_url', true),
            get_post_meta($post_id, '_jbg_video_url', true),
            get_post_meta($post_id, 'video_url', true),
            get_post_meta($post_id, 'embed_html', true),
        ] as $v) {
            if (is_string($v) && trim($v) !== '') return trim($v);
        }

        // 2) ضمیمهٔ ویدیوییِ متصل به پست
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
            $url = wp_get_attachment_url($first->ID);
            if ($url) return $url;
        }

        // 3) اولین URL داخل محتوا (برای یوتیوب/آپارات/MP4 و…)
        if (preg_match('~https?://[^\s"<]+~i', $content, $m)) {
            return $m[0];
        }

        return '';
    }
}

endif;
