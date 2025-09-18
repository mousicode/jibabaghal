<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\RelatedShortcode')):

class RelatedShortcode {
    public static function register(): void {
        add_shortcode('jbg_related', [self::class, 'render']);
    }

    public static function render($atts = []): string {
        $a = shortcode_atts([
            'limit' => 12,
            'title' => 'ویدیوهای مرتبط',
        ], $atts, 'jbg_related');

        $current_id = is_singular('jbg_ad') ? get_queried_object_id() : 0;

        // فقط دسته‌های همین ویدیو
        $tax_query = [];
        if ($current_id) {
            $terms = wp_get_post_terms($current_id, 'jbg_cat', ['fields'=>'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $tax_query[] = ['taxonomy'=>'jbg_cat','field'=>'term_id','terms'=>array_map('intval',$terms)];
            }
        }

        $q = new \WP_Query([
            'post_type'      => 'jbg_ad',
            'posts_per_page' => (int) $a['limit'],
            'no_found_rows'  => true,
            'meta_query'     => [['key'=>'jbg_cpv','compare'=>'EXISTS']],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => $tax_query ?: null,
        ]);

        $rows = [];
        foreach ($q->posts as $p) {
            $rows[] = [
                'ID'    => (int) $p->ID,
                'cpv'   => (int) get_post_meta($p->ID,'jbg_cpv',true),
                'br'    => (int) get_post_meta($p->ID,'jbg_budget_remaining',true),
                'boost' => (int) get_post_meta($p->ID,'jbg_priority_boost',true),
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

        // قفل‌گذاری زنجیره‌ای (اولی باز؛ هر بعدی منوط به پاس قبلی)
        $uid = get_current_user_id();
        $max_open = 0;
        if ($uid) {
            for ($i = 0; $i < count($rows); $i++) {
                $passed = (bool) get_user_meta($uid, 'jbg_quiz_passed_'.$rows[$i]['ID'], true);
                if ($i === 0 || $passed) $max_open = $i + 1; else break;
            }
        } else {
            $max_open = 1;
        }

        // خروجی کارت عمودی/سایدبار
        $out  = '<div class="jbg-related" style="border-radius:14px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:12px">';
        $out .= '<h3 style="margin:0 0 8px 0">'.$a['title'].'</h3>';

        foreach ($rows as $idx => $it) {
            $locked = ($idx >= $max_open);
            $perma  = get_permalink($it['ID']);
            $title  = esc_html(get_the_title($it['ID']));
            $age    = human_time_diff($it['date'], current_time('timestamp')) . ' پیش';

            $out .= '<div class="jbg-related-item'.($locked?' is-locked':'').'" style="display:flex;gap:10px;align-items:center;padding:8px;border-radius:10px;background:#f9fafb;margin:8px 0">';
            $out .= '  <div class="thumb" style="width:72px;height:54px;background:#e5e7eb;border-radius:8px;flex:none"></div>';
            $out .= '  <div class="meta" style="flex:1 1 auto">';
            $out .= '    <div style="font-weight:700">'.$title.'</div>';
            $out .= '    <div style="font-size:12px;color:#6b7280">بازدید '.(int)get_post_meta($it['ID'],'jbg_views_count',true).' • '.$age.'</div>';
            $out .= '  </div>';
            if ($locked) {
                $out .= '  <a aria-disabled="true" class="btn" style="pointer-events:none;opacity:.6;display:inline-block;background:#2563eb;color:#fff;padding:6px 10px;border-radius:10px;text-decoration:none">قفل</a>';
            } else {
                $out .= '  <a class="btn" href="'.esc_url($perma).'" style="display:inline-block;background:#2563eb;color:#fff;padding:6px 10px;border-radius:10px;text-decoration:none">مشاهده</a>';
            }
            $out .= '</div>';
        }
        $out .= '</div>';

        return $out;
    }
}

endif;
