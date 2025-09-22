<?php
/**
 * Admin MetaBox for jbg_ad quiz fields
 */
namespace JBG\Quiz\Admin;

class MetaBox
{
    public static function register(): void
    {
        // فقط در ادمین متاباکس را رجیستر کن و به موقع (روی add_meta_boxes)
        if (!\is_admin()) {
            return;
        }

        \add_action('add_meta_boxes', [self::class, 'add_box']);
        \add_action('save_post_jbg_ad', [self::class, 'save'], 10, 2);
    }

    public static function add_box(): void
    {
        // اطمینان از در دسترس بودن توابع ادمین (در بعضی هاست‌ها لازم می‌شود)
        if (!\function_exists('add_meta_box') && \file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
        }

        \add_meta_box(
            'jbg_quiz_box',
            __('JBG Quiz', 'jbg'),
            [self::class, 'render'],
            'jbg_ad',
            'normal',
            'high'
        );
    }

    public static function render(\WP_Post $post): void
    {
        \wp_nonce_field('jbg_quiz_meta', 'jbg_quiz_nonce');

        $q   = \get_post_meta($post->ID, 'jbg_quiz_q', true);
        $a1  = \get_post_meta($post->ID, 'jbg_quiz_a1', true);
        $a2  = \get_post_meta($post->ID, 'jbg_quiz_a2', true);
        $a3  = \get_post_meta($post->ID, 'jbg_quiz_a3', true);
        $a4  = \get_post_meta($post->ID, 'jbg_quiz_a4', true);
        $ans = (int) \get_post_meta($post->ID, 'jbg_quiz_ans', true);
        ?>
        <style>
            .jbg-field {margin:8px 0;}
            .jbg-field label{display:block;font-weight:600;margin-bottom:4px;}
            .jbg-field input[type="text"], .jbg-field textarea{width:100%;}
            .jbg-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        </style>

        <div class="jbg-field">
            <label for="jbg_quiz_q"><?php echo \esc_html__('Question', 'jbg'); ?></label>
            <textarea id="jbg_quiz_q" name="jbg_quiz_q" rows="3"><?php echo \esc_textarea((string)$q); ?></textarea>
        </div>

        <div class="jbg-grid">
            <div class="jbg-field">
                <label for="jbg_quiz_a1"><?php echo \esc_html__('Answer 1', 'jbg'); ?></label>
                <input id="jbg_quiz_a1" type="text" name="jbg_quiz_a1" value="<?php echo \esc_attr((string)$a1); ?>">
            </div>
            <div class="jbg-field">
                <label for="jbg_quiz_a2"><?php echo \esc_html__('Answer 2', 'jbg'); ?></label>
                <input id="jbg_quiz_a2" type="text" name="jbg_quiz_a2" value="<?php echo \esc_attr((string)$a2); ?>">
            </div>
            <div class="jbg-field">
                <label for="jbg_quiz_a3"><?php echo \esc_html__('Answer 3', 'jbg'); ?></label>
                <input id="jbg_quiz_a3" type="text" name="jbg_quiz_a3" value="<?php echo \esc_attr((string)$a3); ?>">
            </div>
            <div class="jbg-field">
                <label for="jbg_quiz_a4"><?php echo \esc_html__('Answer 4', 'jbg'); ?></label>
                <input id="jbg_quiz_a4" type="text" name="jbg_quiz_a4" value="<?php echo \esc_attr((string)$a4); ?>">
            </div>
        </div>

        <div class="jbg-field">
            <label for="jbg_quiz_ans"><?php echo \esc_html__('Correct answer (1-4)', 'jbg'); ?></label>
            <input id="jbg_quiz_ans" type="number" min="1" max="4" step="1" name="jbg_quiz_ans" value="<?php echo \esc_attr((string)($ans ?: 1)); ?>">
        </div>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        if (!isset($_POST['jbg_quiz_nonce']) || !\wp_verify_nonce($_POST['jbg_quiz_nonce'], 'jbg_quiz_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ($post->post_type !== 'jbg_ad') {
            return;
        }
        if (!\current_user_can('edit_post', $post_id)) {
            return;
        }

        $q   = isset($_POST['jbg_quiz_q'])   ? \wp_kses_post($_POST['jbg_quiz_q']) : '';
        $a1  = isset($_POST['jbg_quiz_a1'])  ? \sanitize_text_field($_POST['jbg_quiz_a1']) : '';
        $a2  = isset($_POST['jbg_quiz_a2'])  ? \sanitize_text_field($_POST['jbg_quiz_a2']) : '';
        $a3  = isset($_POST['jbg_quiz_a3'])  ? \sanitize_text_field($_POST['jbg_quiz_a3']) : '';
        $a4  = isset($_POST['jbg_quiz_a4'])  ? \sanitize_text_field($_POST['jbg_quiz_a4']) : '';
        $ans = isset($_POST['jbg_quiz_ans']) ? (int) $_POST['jbg_quiz_ans'] : 1;

        $ans = \max(1, \min(4, $ans));

        \update_post_meta($post_id, 'jbg_quiz_q',   $q);
        \update_post_meta($post_id, 'jbg_quiz_a1',  $a1);
        \update_post_meta($post_id, 'jbg_quiz_a2',  $a2);
        \update_post_meta($post_id, 'jbg_quiz_a3',  $a3);
        \update_post_meta($post_id, 'jbg_quiz_a4',  $a4);
        \update_post_meta($post_id, 'jbg_quiz_ans', $ans);
    }
}
