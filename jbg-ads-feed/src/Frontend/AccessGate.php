<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Enforce sequential access for single jbg_ad:
 * - ترتیب: CPV ↓ → budget_remaining ↓ → boost ↓ → date ↓
 * - تنها وقتی اجازه‌ی مشاهده‌ی ad N داده می‌شود که تمام آیتم‌های قبلی (۰..N-1)
 *   هم "تماشا کامل" شده باشند و هم "آزمون پاس/بیلینگ انجام" شده باشد.
 * - مهم: فقط در همان دسته/دسته‌های فعلی (jbg_cat) اعمال می‌شود.
 */
class AccessGate {

    public static function register(): void {
        add_action('template_redirect', [self::class, 'guard'], 10);
    }

    public static function guard(): void {
        if (!is_singular('jbg_ad')) return;

        $current_id = get_queried_object_id();
        if (!$current_id) return;

        // آرایه‌ی آیتم‌ها را طبق همان ترتیبی که در RelatedShortcode داریم بسازیم
        $items = self::sorted_items_for_current($current_id);

        if (empty($items)) return;

        // جایگاه ویدیوی فعلی را پیدا کن
        $idx = -1;
        foreach ($items as $i => $it) {
            if ((int)$it['ID'] === (int)$current_id) { $idx = $i; break; }
        }
        if ($idx < 0) return; // اگر در لیست نبود، گیت اعمال نشود

        // اگر اولین آیتم است، همیشه آزاد است (برای مهمان هم)
        if ($idx === 0) return;

        // اگر کاربر وارد نشده است: فقط اجازه‌ی آیتم اول را بده
        if (!is_user_logged_in()) {
            $first_url = get_permalink($items[0]['ID']);
            if (!empty($first_url) && (int)$items[0]['ID'] !== (int)$current_id) {
                wp_safe_redirect($first_url, 302);
                exit;
            }
            return;
        }

        $uid = get_current_user_id();

      // همه‌ی آیتم‌های قبل از جاری باید completed باشند
$first_locked = null;
for ($i = 0; $i < $idx; $i++) {
    $ad_id  = (int)$items[$i]['ID'];
    $watched = (bool) get_user_meta($uid, 'jbg_watched_ok_' . $ad_id, true);
    $billed  = (bool) get_user_meta($uid, 'jbg_billed_'     . $ad_id, true);
    $quiz    = (bool) get_user_meta($uid, 'jbg_quiz_passed_'.$ad_id, true);
    if (!($watched && ($billed || $quiz))) { $first_locked = $items[$i]; break; }
}


        if ($first_locked) {
            // ریدایرکت به اولین آیتمی که باید تمام/پاس شود
            $url = get_permalink($first_locked['ID']);
            if (!empty($url)) {
                wp_safe_redirect($url, 302);
                exit;
            }
        }
    }

    /** دریافت آیتم‌های دسته‌ی فعلی با ترتیب نهایی */
    private static function sorted_items_for_current(int $current_id): array {
        // دسته‌های فعلی
        $tax_query = [];
        $terms = wp_get_post_terms($current_id, 'jbg_cat', ['fields' => 'ids']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $tax_query[] = [
                'taxonomy' => 'jbg_cat',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $terms),
            ];
        }

        $args = [
            'post_type'      => 'jbg_ad',
            'posts_per_page' => 500, // سقف معقول
            'no_found_rows'  => true,
            'meta_query'     => [
                ['key' => 'jbg_cpv', 'compare' => 'EXISTS'],
            ],
            'orderby' => 'date',
            'order'   => 'DESC',
        ];
        if ($tax_query) $args['tax_query'] = $tax_query;

        $q = new \WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => $p->ID,
                'cpv'   => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'date'  => get_post_time('U', true, $p->ID),
            ];
        }
        wp_reset_postdata();

        usort($items, function($a, $b){
            if ($a['cpv'] === $b['cpv']) {
                if ($a['br'] === $b['br']) {
                    if ($a['boost'] === $b['boost']) {
                        return ($b['date'] <=> $a['date']);
                    }
                    return ($b['boost'] <=> $a['boost']);
                }
                return ($b['br'] <=> $a['br']);
            }
            return ($b['cpv'] <=> $a['cpv']);
        });

        return $items;
    }
}
