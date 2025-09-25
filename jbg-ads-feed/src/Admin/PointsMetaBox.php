<?php
namespace JBG\Ads\Admin;
if (!defined('ABSPATH')) exit;

class PointsMetaBox {

    // فقط تعریف خود متاباکس؛ این متد را با هوک add_meta_boxes صدا می‌زنیم
    public static function register(): void {
        \add_meta_box(
            'jbg_ad_points',
            __('امتیاز ویدیو', 'jbg-ads'),
            [self::class, 'render'],
            'jbg_ad',
            'side',
            'default'
        );
    }

    public static function render(\WP_Post $post): void {
        $points = (int) \get_post_meta($post->ID, 'jbg_points', true);
        \wp_nonce_field('jbg_ad_points_nonce', 'jbg_ad_points_nonce');
        ?>
        <p style="margin:0 0 8px">
            <?php echo esc_html__('این امتیاز بعد از پاس‌شدن آزمون این ویدیو به کاربر تعلق می‌گیرد.', 'jbg-ads'); ?>
        </p>
        <label for="jbg_points"><?php echo esc_html__('امتیاز', 'jbg-ads'); ?></label>
        <input type="number" min="0" step="1" id="jbg_points" name="jbg_points"
               value="<?php echo esc_attr($points); ?>" style="width:100%">
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['jbg_ad_points_nonce']) ||
            !\wp_verify_nonce($_POST['jbg_ad_points_nonce'], 'jbg_ad_points_nonce')) return;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'jbg_ad') return;
        if (!\current_user_can('edit_post', $post_id)) return;

        $points = isset($_POST['jbg_points']) ? (int) $_POST['jbg_points'] : 0;
        if ($points < 0) $points = 0;

        \update_post_meta($post_id, 'jbg_points', $points);
    }
}
