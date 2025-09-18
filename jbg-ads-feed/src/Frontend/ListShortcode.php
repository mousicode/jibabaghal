<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\ListShortcode')):

class ListShortcode {
    public static function register(): void {
        add_shortcode('jbg_list', [self::class, 'render']);
    }

    public static function render($atts = []): string {
        $a = shortcode_atts([
            'limit' => 12,
            'cat'   => '',   // term_id یا slug (اختیاری)
            'title' => '',
        ], $atts, 'jbg_list');

        // فیلتر دسته (اختیاری)
        $tax_query = [];
        if (!empty($a['cat'])) {
            $field = is_numeric($a['cat']) ? 'term_id' : 'slug';
            $tax_query[] = ['taxonomy'=>'jbg_cat','field'=>$field,'terms'=>[$a['cat']]];
        }

        // واکشی خام
        $q = new \WP_Query([
            'post_type'      => 'jbg_ad',
            'posts_per_page' => (int)$a['limit'],
            'no_found_rows'  => true,
            'meta_query'     => [['key'=>'jbg_cpv','compare'=>'EXISTS']],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => $tax_query ?: null,
        ]);

        $rows = [];
        foreach ($q->posts as $p) {
            $rows[] = [
                'ID'    => (int)$p->ID,
                'cpv'   => (int)get_post_meta($p->ID,'jbg_cpv',true),
                'br'    => (int)get_post_meta($p->ID,'jbg_budget_remaining',true),
                'boost' => (int)get_post_meta($p->ID,'jbg_priority_boost',true),
                'date'  => get_post_time('U', true, $p->ID),
            ];
        }
        wp_reset_postdata();

        // مرتب‌سازی مرکب (مثل آرشیو)
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

        // قفل‌گذاری زنجیره‌ای: اولی باز؛ هر بعدی منوط به قبولی قبلی
        $uid = get_current_user_id();
        $max_open = 0;
        if ($uid) {
            for ($i=0; $i<count($rows); $i++) {
                $passed = (bool) get_user_meta($uid, 'jbg_quiz_passed_'.$rows[$i]['ID'], true);
                if ($i === 0 || $passed) $max_open = $i + 1; else break;
            }
        } else {
            $max_open = 1; // مهمان فقط اولی
        }

        // خروجی کارت‌ها
        $out  = '<div class="jbg-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">';
        if (!empty($a['title'])) $out .= '<h3 style="grid-column:1/-1;margin:0 0 8px">'.$a['title'].'</h3>';

        foreach ($rows as $idx => $it) {
            $locked = ($idx >= $max_open);
            $perma  = get_permalink($it['ID']);
            $title  = esc_html(get_the_title($it['ID']));
            $age    = human_time_diff($it['date'], current_time('timestamp')) . ' پیش';
            $views  = (int) get_post_meta($it['ID'],'jbg_views_count',true);

            $out .= '<article class="jbg-card'.($locked?' is-locked':'').'" style="border-radius:16px;background:#fff;box-shadow:0 6px 18px rgba(0,0,0,.06);overflow:hidden">';
            $out .= '  <div class="jbg-card__thumb" style="background:#f3f4f6;height:140px"></div>';
            $out .= '  <div class="jbg-card__body" style="padding:12px 14px">';
            $out .= '    <h4 style="margin:0 0 6px;font-weight:700">'.$title.'</h4>';
            $out .= '    <div class="jbg-card__meta" style="font-size:12px;color:#6b7280">بازدید '.(int)$views.' • '.$age.'</div>';
            $out .= '    <div style="margin-top:10px;text-align:left">';
            if ($locked) {
                $out .= '      <a aria-disabled="true" class="btn" style="pointer-events:none;opacity:.6;display:inline-block;background:#2563eb;color:#fff;padding:8px 12px;border-radius:10px;text-decoration:none">قفل</a>';
            } else {
                $out .= '      <a class="btn" href="'.esc_url($perma).'" style="display:inline-block;background:#2563eb;color:#fff;padding:8px 12px;border-radius:10px;text-decoration:none">مشاهده</a>';
            }
            $out .= '    </div>';
            $out .= '  </div>';
            $out .= '</article>';
        }
        $out .= '</div>';

        return $out;
    }
}

endif;
