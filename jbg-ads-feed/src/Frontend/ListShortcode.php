<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class ListShortcode {

    public static function register(): void {
        add_shortcode('jbg_ads', [self::class, 'render']);
    }

    private static function compact_num(int $n): string {
        if ($n >= 1000000000) { $num=$n/1000000000; $u=' میلیارد'; }
        elseif ($n >= 1000000){ $num=$n/1000000;    $u=' میلیون'; }
        elseif ($n >= 1000)   { $num=$n/1000;       $u=' هزار'; }
        else return number_format_i18n($n);
        $s = number_format_i18n($num,1);
        $s = preg_replace('/([0-9۰-۹]+)[\.\,٫]0$/u', '$1', $s);
        return $s.$u;
    }
    private static function relative_time(int $post_id): string {
        return trim(human_time_diff(get_the_time('U',$post_id), current_time('timestamp'))).' پیش';
    }
    private static function brand_name(int $post_id): string {
        $names = wp_get_post_terms($post_id, 'jbg_brand', ['fields'=>'names']);
        return (!is_wp_error($names) && !empty($names)) ? (string) $names[0] : '';
    }

    public static function render($atts = []): string {
        $a = shortcode_atts([
            'limit'    => 12,
            'brand'    => '',
            'category' => '',
            'class'    => '',
        ], $atts, 'jbg_ads');

        $args = [
            'post_type'      => 'jbg_ad',
            'posts_per_page' => max(1, (int)$a['limit']),
            'no_found_rows'  => true,
            'meta_query'     => [['key'=>'jbg_cpv','compare'=>'EXISTS']],
        ];

        $tax = [];
        if (!empty($a['brand'])) {
            $tax[] = ['taxonomy'=>'jbg_brand','field'=> is_numeric($a['brand'])?'term_id':'slug',
                      'terms'=> is_numeric($a['brand'])?(int)$a['brand']:$a['brand']];
        }
        if (!empty($a['category'])) {
            $tax[] = ['taxonomy'=>'jbg_cat','field'=> is_numeric($a['category'])?'term_id':'slug',
                      'terms'=> is_numeric($a['category'])?(int)$a['category']:$a['category']];
        }
        if ($tax) $args['tax_query'] = $tax;

        $q = new \WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => $p->ID,
                'title' => get_the_title($p),
                'link'  => get_permalink($p),
                'thumb' => get_the_post_thumbnail_url($p->ID, 'large') ?: '',
                'cpv'   => (int) get_post_meta($p->ID, 'jbg_cpv', true),
                'br'    => (int) get_post_meta($p->ID, 'jbg_budget_remaining', true),
                'boost' => (int) get_post_meta($p->ID, 'jbg_priority_boost', true),
                'date'  => get_post_time('U', true, $p->ID),
            ];
        }
        wp_reset_postdata();

        // مرتب‌سازی نهایی مثل آرشیو
        usort($items, function($a, $b){
            if ($a['cpv'] === $b['cpv']) {
                if ($a['br'] === $b['br']) {
                    if ($a['boost'] === $b['boost']) return ($b['date'] <=> $a['date']);
                    return ($b['boost'] <=> $a['boost']);
                }
                return ($b['br']   <=> $a['br']);
            }
            return ($b['cpv']     <=> $a['cpv']);
        });

        // گیت مرحله‌ای در UI
        $uid = get_current_user_id();
        $is_logged = is_user_logged_in();
        $prev_ok = true;

        $rows = [];
        foreach ($items as $i => $it) {
            $ad_id = (int)$it['ID'];
            $completed = false;
            if ($is_logged) {
                $watched = (bool) get_user_meta($uid, 'jbg_watched_ok_' . $ad_id, true);
                $billed  = (bool) get_user_meta($uid, 'jbg_billed_'     . $ad_id, true);
                $quiz    = (bool) get_user_meta($uid, 'jbg_quiz_passed_' . $ad_id, true);
                $completed = $watched && ($billed || $quiz);
            }
            $allowed = $completed || $prev_ok;
            $rows[] = $it + ['completed'=>$completed, 'allowed'=>$allowed];
            $prev_ok = $completed;
        }

        ob_start();
        $extra_class = $a['class'] ? ' '.sanitize_html_class($a['class']) : '';
        echo '<div class="jbg-list-grid'.$extra_class.'">';
        foreach ($rows as $it) {
            $ad_id = (int)$it['ID'];
            $is_allowed = (bool)$it['allowed'];
            $views  = self::compact_num( (int) get_post_meta($ad_id,'jbg_views_total',true) );
            $when   = self::relative_time($ad_id);
            $brand  = self::brand_name($ad_id);
            $thumb  = $it['thumb'] ? ' style="background-image:url(\''.esc_url($it['thumb']).'\')"' : '';

            echo '<div class="jbg-card '.($is_allowed?'':'is-locked').'">';
            if ($is_allowed) {
                echo '<a class="jbg-card-link" href="'.esc_url($it['link']).'">';
            } else {
                echo '<div class="jbg-card-link -nolink">';
            }

            echo   '<span class="jbg-card-thumb"'.$thumb.'></span>';
            echo   '<span class="jbg-card-body">';
            echo     '<span class="jbg-card-title">'.esc_html($it['title']).'</span>';
            echo     '<span class="jbg-card-sub">';
            if ($brand) echo '<span class="brand">'.esc_html($brand).'</span><span class="dot">•</span>';
            echo       '<span>'.$views.' بازدید</span><span class="dot">•</span><span>'.$when.'</span>';
            echo     '</span>';
            echo     '<span class="jbg-card-cta">مشاهده</span>';
            echo   '</span>';

            if ($is_allowed) {
                echo '</a>';
            } else {
                echo   '<span class="jbg-lock-badge" aria-hidden="true">🔒</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>'; ?>
        <style>
          .jbg-card.is-locked{opacity:.7; position:relative}
          .jbg-card-link.-nolink{cursor:not-allowed}
          .jbg-card.is-locked .jbg-card-cta{filter:grayscale(1); opacity:.8}
          .jbg-card .jbg-lock-badge{position:absolute; left:12px; top:12px; font-size:20px}
        </style>
        <?php
        return (string) ob_get_clean();
    }
}
