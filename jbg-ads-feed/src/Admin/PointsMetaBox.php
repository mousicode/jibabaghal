<?php
namespace JBG\Ads\Admin;
if (!defined('ABSPATH')) exit;

class PointsMetaBox {

    public static function register(): void {
        add_meta_box(
            'jbg_ad_points',
            __('Video Points', 'jbg-ads'),
            [self::class, 'render'],
            'jbg_ad',
            'side',
            'high'
        );
    }

    public static function render(\WP_Post $post): void {
        $pts = (int) get_post_meta($post->ID, 'jbg_points', true);
        wp_nonce_field('jbg_points_save', 'jbg_points_nonce');
        echo '<p><label for="jbg_points_field">' . esc_html__('Points for this video', 'jbg-ads') . '</label></p>';
        echo '<input type="number" min="0" class="widefat" id="jbg_points_field" name="jbg_points_field" value="' . esc_attr($pts) . '" />';
        echo '<p class="description">' . esc_html__('These points will be granted once when the user answers the quiz correctly.', 'jbg-ads') . '</p>';
    }

    public static function save(int $post_id, \WP_Post $post): void {
        if ($post->post_type !== 'jbg_ad') return;
        if (!isset($_POST['jbg_points_nonce']) || !wp_verify_nonce($_POST['jbg_points_nonce'], 'jbg_points_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $val = isset($_POST['jbg_points_field']) ? (int) $_POST['jbg_points_field'] : 0;
        if ($val < 0) $val = 0;
        update_post_meta($post_id, 'jbg_points', $val);
    }
}
