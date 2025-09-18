<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\PlayerShim')):
class PlayerShim {
    public static function register(): void {
        add_filter('the_content', [self::class, 'inject_player_if_missing'], 5);
    }
    public static function inject_player_if_missing($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        // اگر پلیر/iframe هست، دست نزن
        if (stripos($content, '<video') !== false ||
            stripos($content, '<iframe') !== false ||
            stripos($content, 'wp-video-shortcode') !== false) return $content;

        $id = get_the_ID();

        // اول متاهای رایج
        $src = '';
        foreach ([
            get_post_meta($id,'jbg_player',true),
            get_post_meta($id,'jbg_video_url',true),
            get_post_meta($id,'_jbg_video_url',true),
            get_post_meta($id,'video_url',true),
            get_post_meta($id,'embed_html',true),
        ] as $v) { if (is_string($v) && trim($v) !== '') { $src = trim($v); break; } }

        // اگر خالی بود: اولین ضمیمهٔ ویدیویی
        if ($src === '') {
            $att = get_children([
                'post_parent'=>$id,'post_type'=>'attachment',
                'post_mime_type'=>'video','numberposts'=>1,
                'orderby'=>'menu_order ID','order'=>'ASC',
            ]);
            if ($att) {
                $first = array_shift($att);
                $u = wp_get_attachment_url($first->ID);
                if ($u) $src = $u;
            }
        }

        if ($src === '') return $content;

        // اگر HTML خام باشد
        if (stripos($src,'<iframe') !== false || stripos($src,'<video') !== false) {
            $player = $src;
        } else {
            if (preg_match('/\\.(mp4|webm|ogg)(\\?.*)?$/i', $src)) {
                $player = wp_video_shortcode(['src'=>esc_url_raw($src)]);
            } else {
                $player = wp_oembed_get($src);
                if (!$player) {
                    $esc = esc_url($src);
                    $player = '<iframe src="'.$esc.'" style="width:100%;aspect-ratio:16/9" loading="lazy" allowfullscreen></iframe>';
                }
            }
        }

        return '<div class="jbg-player" style="margin:12px 0">'.$player.'</div>'."\n".$content;
    }
}
endif;
