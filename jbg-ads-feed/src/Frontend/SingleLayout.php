<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\SingleLayout')):

class SingleLayout {
    public static function register(): void {
        // فقط دکمه را به انتهای محتوا اضافه می‌کنیم تا پلیر/کوییز دست نخورَد
        add_filter('the_content', [self::class, 'append_next_btn'], 50);
    }

    /** ترتیب واحد مطابق آرشیو: cpv↓, budget_remaining↓, priority_boost↓, date↓ (فقط داخل دسته‌های همین ویدیو) */
    private static function ordered_items_for(int $current_id): array {
        $tax_query = [];
        $terms = wp_get_post_terms($current_id, 'jbg_cat', ['fields' => 'ids']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $tax_query[] = [
                'taxonomy' => 'jbg_cat',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $terms),
            ];
        }

        $q = new \WP_Query([
            'post_type'      => 'jbg_ad',
            'posts_per_page' => 500,
            'no_found_rows'  => true,
            'meta_query'     => [['key' => 'jbg_cpv', 'compare' => 'EXISTS']],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => $tax_query ?: null,
        ]);

        $rows = [];
        foreach ($q->posts as $p) {
            $rows[] = [
                'ID'    => (int) $p->ID,
                'cpv'   => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'date'  => get_post_time('U', true, $p->ID),
            ];
        }
        wp_reset_postdata();

        usort($rows, function($a,$b){
            if ($a['cpv'] === $b['cpv']) {
                if ($a['br'] === $b['br']) {
                    if ($a['boost'] === $b['boost']) return ($b['date'] <=> $a['date']);
                    return ($b['boost'] <=> $a['boost']);
                }
                return ($b['br'] <=> $a['br']);
            }
            return ($b['cpv'] <=> $a['cpv']);
        });

        return $rows;
    }

    private static function next_url_for(int $current_id): string {
        $items = self::ordered_items_for($current_id);
        if (!$items) return '';
        $ids = array_map(fn($it)=>(int)$it['ID'], $items);
        $idx = array_search($current_id, $ids, true);
        if ($idx === false || $idx >= count($ids) - 1) return '';
        return get_permalink((int) $ids[$idx + 1]) ?: '';
    }

    public static function append_next_btn($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $current_id = get_the_ID();
        $next_url   = self::next_url_for($current_id);

        $btn  = '<div class="jbg-next-wrap" style="margin-top:16px;text-align:right">';
        if ($next_url) {
            $btn .= '<a id="jbg-next-btn" class="jbg-next-btn" href="'.esc_url($next_url).'" aria-disabled="true" '
                 .  'style="display:inline-block;padding:10px 16px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;opacity:.5;pointer-events:none">'
                 .  'ویدیو بعدی</a>';
            $btn .= '<small id="jbg-next-hint" style="margin-right:8px;font-size:12px;color:#6b7280">'
                 .  'بعد از قبولی آزمون این ویدیو، دکمه فعال می‌شود.</small>';
        } else {
            $btn .= '<small id="jbg-next-hint" style="margin-right:8px;font-size:12px;color:#6b7280">این آخرین ویدیو است.</small>';
        }
        $btn .= '</div>';

        $passed = is_user_logged_in() ? (bool) get_user_meta(get_current_user_id(), 'jbg_quiz_passed_'.$current_id, true) : false;

        $script = '<script>(function(){var b=document.getElementById("jbg-next-btn"),h=document.getElementById("jbg-next-hint");'
                . ($passed ? 'if(b){b.setAttribute("aria-disabled","false");b.style.opacity="1";b.style.pointerEvents="auto";if(h)h.textContent="";}' : '')
                . 'function openBtn(){if(!b)return;b.setAttribute("aria-disabled","false");b.style.opacity="1";b.style.pointerEvents="auto";if(h)h.textContent="";}'
                . 'document.addEventListener("jbg:quiz_passed",function(e){try{var id=e&&e.detail&&e.detail.adId?parseInt(e.detail.adId,10):0;if(!id||id==='.$current_id.')openBtn();}catch(_){openBtn();}});})();</script>';

        return $content . $btn . $script;
    }
}

endif;
