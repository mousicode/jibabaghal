<?php
namespace JBG\Player\Admin;


class MetaBox {
public static function register(): void {
add_meta_box('jbg_ad_video_src', __('Video Source','jbg-player'), [self::class,'render'], 'jbg_ad', 'normal', 'high');
}


public static function render($post): void {
$src = (string) get_post_meta($post->ID, 'jbg_video_src', true);
wp_nonce_field('jbg_ad_video_src', 'jbg_ad_video_src_nonce');
echo '<p>'.__('Provide MP4 or HLS (m3u8) URL. HLS is recommended.','jbg-player').'</p>';
echo '<input type="url" name="jbg_video_src" style="width:100%" value="'.esc_attr($src).'" placeholder="https://.../video.m3u8 or .mp4">';
}


public static function save($post_id, $post): void {
if (!isset($_POST['jbg_ad_video_src_nonce']) || !wp_verify_nonce($_POST['jbg_ad_video_src_nonce'], 'jbg_ad_video_src')) return;
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
if (!current_user_can('edit_post', $post_id)) return;
$src = isset($_POST['jbg_video_src']) ? esc_url_raw($_POST['jbg_video_src']) : '';
update_post_meta($post_id, 'jbg_video_src', $src);
}
}