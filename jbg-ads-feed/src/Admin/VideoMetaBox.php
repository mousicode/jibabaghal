<?php
namespace JBG\Ads\Admin;

if (!defined('ABSPATH')) exit;

class VideoMetaBox {
    public static function register(): void {
        \add_meta_box(
            'jbg_ad_video',
            __('Video / Embed', 'jbg-ads'),
            [self::class, 'render'],
            'jbg_ad',
            'normal',
            'high'
        );
        \add_action('save_post_jbg_ad', [self::class, 'save'], 10, 2);
    }

    public static function render(\WP_Post $post): void {
        \wp_nonce_field('jbg_ad_video_meta', 'jbg_ad_video_nonce');
        $url   = \get_post_meta($post->ID, 'jbg_video_url', true);
        $embed = \get_post_meta($post->ID, 'embed_html',   true);
        ?>
        <p><label><strong><?php echo \esc_html__('Direct video URL (mp4/webm/…)', 'jbg-ads'); ?></strong></label><br>
           <input type="url" name="jbg_video_url" value="<?php echo \esc_attr((string)$url); ?>" style="width:100%" placeholder="https://…/video.mp4">
        </p>
        <p><label><strong><?php echo \esc_html__('Embed HTML (iframe – Aparat/YouTube)', 'jbg-ads'); ?></strong></label><br>
           <textarea name="embed_html" rows="4" style="width:100%" placeholder='<iframe src="…"></iframe>'><?php echo \esc_textarea((string)$embed); ?></textarea>
        </p>
        <p style="color:#666"><?php echo \esc_html__('If both are set, Embed HTML has priority.', 'jbg-ads'); ?></p>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['jbg_ad_video_nonce']) || !\wp_verify_nonce($_POST['jbg_ad_video_nonce'], 'jbg_ad_video_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!\current_user_can('edit_post', $post_id)) return;

        $url   = isset($_POST['jbg_video_url']) ? \esc_url_raw(trim((string)$_POST['jbg_video_url'])) : '';
        $embed = isset($_POST['embed_html'])    ? \wp_kses_post((string)$_POST['embed_html'])         : '';

        \update_post_meta($post_id, 'jbg_video_url', $url);
        \update_post_meta($post_id, 'embed_html',    $embed);
    }
}
