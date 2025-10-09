<?php
namespace JBG\Ads\Admin;

if (!defined('ABSPATH')) exit;

class SponsorBrandAccess
{
    public static function register(): void
    {
        // فقط ادمین‌ها فرم را ببینند/ذخیره کنند
        add_action('show_user_profile', [self::class, 'field']);
        add_action('edit_user_profile',  [self::class, 'field']);
        add_action('personal_options_update', [self::class, 'save']);
        add_action('edit_user_profile_update', [self::class, 'save']);
    }

    public static function field($user): void
    {
        if (!current_user_can('manage_options')) return;

        $terms = get_terms([
            'taxonomy'   => 'jbg_brand',
            'hide_empty' => false,
        ]);
        if (is_wp_error($terms)) $terms = [];

        $assigned = get_user_meta($user->ID, 'jbg_sponsor_brand_ids', true);
        if (!is_array($assigned)) $assigned = [];

        ?>
        <h2 style="margin-top:30px">دسترسی اسپانسر به برندها</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="jbg_sponsor_brand_ids">برندهای مجاز برای این کاربر</label></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">برندهای مجاز</legend>
                        <?php if (empty($terms)): ?>
                            <p>هیچ برندی (taxonomy: jbg_brand) تعریف نشده است.</p>
                        <?php else: ?>
                            <?php foreach ($terms as $t): ?>
                                <label style="display:block;margin:6px 0;">
                                    <input type="checkbox"
                                           name="jbg_sponsor_brand_ids[]"
                                           value="<?php echo (int)$t->term_id; ?>"
                                           <?php checked(in_array((int)$t->term_id, $assigned, true)); ?>>
                                    <?php echo esc_html($t->name); ?>
                                    <span style="color:#6b7280">#<?php echo (int)$t->term_id; ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <p class="description">برای کاربران با رول <code>jbg_sponsor</code> تعیین کنید به داده‌های کدام برند دسترسی دارند.</p>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save(int $user_id): void
    {
        if (!current_user_can('manage_options')) return;

        $ids = isset($_POST['jbg_sponsor_brand_ids']) ? (array) $_POST['jbg_sponsor_brand_ids'] : [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        update_user_meta($user_id, 'jbg_sponsor_brand_ids', $ids);
    }
}
