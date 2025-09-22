<?php
namespace JBG\Quiz\Admin;


class MetaBox {
public static function register(): void {
add_meta_box('jbg_quiz_box', __('Quiz (4 choices)','jbg-quiz'), [self::class,'render'], 'jbg_ad', 'normal', 'high');
}


public static function render($post): void {
$q = (string) get_post_meta($post->ID, 'jbg_quiz_q', true);
$a1 = (string) get_post_meta($post->ID, 'jbg_quiz_a1', true);
$a2 = (string) get_post_meta($post->ID, 'jbg_quiz_a2', true);
$a3 = (string) get_post_meta($post->ID, 'jbg_quiz_a3', true);
$a4 = (string) get_post_meta($post->ID, 'jbg_quiz_a4', true);
$ans = (int) get_post_meta($post->ID, 'jbg_quiz_ans', true); // 1..4
wp_nonce_field('jbg_quiz_meta', 'jbg_quiz_meta_nonce');
echo '<p><label>'.__('Question','jbg-quiz').'</label><br><textarea name="jbg_quiz_q" rows="3" style="width:100%">'.esc_textarea($q).'</textarea></p>';
echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">';
echo '<p><label>A1</label><br><input type="text" name="jbg_quiz_a1" value="'.esc_attr($a1).'" style="width:100%"></p>';
echo '<p><label>A2</label><br><input type="text" name="jbg_quiz_a2" value="'.esc_attr($a2).'" style="width:100%"></p>';
echo '<p><label>A3</label><br><input type="text" name="jbg_quiz_a3" value="'.esc_attr($a3).'" style="width:100%"></p>';
echo '<p><label>A4</label><br><input type="text" name="jbg_quiz_a4" value="'.esc_attr($a4).'" style="width:100%"></p>';
echo '</div>';
echo '<p><label>'.__('Correct answer (1-4)','jbg-quiz').'</label><br><input type="number" name="jbg_quiz_ans" min="1" max="4" value="'.esc_attr($ans ? $ans : 1).'" style="width:100px"></p>';
echo '<p style="opacity:.7">'.__('Quiz unlocks after video is watched (≈95%).','jbg-quiz').'</p>';
}


public static function save($post_id, $post): void {
if (!isset($_POST['jbg_quiz_meta_nonce']) || !wp_verify_nonce($_POST['jbg_quiz_meta_nonce'], 'jbg_quiz_meta')) return;
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
if (!current_user_can('edit_post', $post_id)) return;


$q = isset($_POST['jbg_quiz_q']) ? wp_kses_post($_POST['jbg_quiz_q']) : '';
$a1 = isset($_POST['jbg_quiz_a1']) ? sanitize_text_field($_POST['jbg_quiz_a1']) : '';
$a2 = isset($_POST['jbg_quiz_a2']) ? sanitize_text_field($_POST['jbg_quiz_a2']) : '';
$a3 = isset($_POST['jbg_quiz_a3']) ? sanitize_text_field($_POST['jbg_quiz_a3']) : '';
$a4 = isset($_POST['jbg_quiz_a4']) ? sanitize_text_field($_POST['jbg_quiz_a4']) : '';
$ans = isset($_POST['jbg_quiz_ans']) ? max(1, min(4, (int) $_POST['jbg_quiz_ans'])) : 1;


update_post_meta($post_id, 'jbg_quiz_q', $q);
update_post_meta($post_id, 'jbg_quiz_a1', $a1);
update_post_meta($post_id, 'jbg_quiz_a2', $a2);
update_post_meta($post_id, 'jbg_quiz_a3', $a3);
update_post_meta($post_id, 'jbg_quiz_a4', $a4);
update_post_meta($post_id, 'jbg_quiz_ans',$ans);
}
}