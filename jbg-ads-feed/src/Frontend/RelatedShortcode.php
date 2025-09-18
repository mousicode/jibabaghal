<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class RelatedShortcode {

    public static function register(): void {
        add_shortcode('jbg_related', [self::class, 'render']);
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
    private static function views_count(int $ad_id): int {
        $v = (int) get_post_meta($ad_id, 'jbg_views_total', true);
        if ($v > 0) return $v;
        global $wpdb; $table = $wpdb->prefix.'jbg_views';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return 0;
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ad_id=%d", $ad_id));
        update_post_meta($ad_id, 'jbg_views_total', $count);
        update_post_meta($ad_id, 'jbg_views_count', $count);
        wp_cache_delete($ad_id, 'post_meta');
        return $count;
    }

    public static function render($atts = []): string {
        $a = shortcode_atts([
            'limit' => 8,
            'title' => 'ویدیوهای مرتبط',
        ], $atts, 'jbg_related');

        $limit = max(1, (int)$a['limit']);
        $current_id = is_singular('jbg_ad') ? get_the_ID() : 0;

        // فیلتر jbg_cat برای مرتبط‌ها
        $tax_query = [];
        if ($current_id) {
            $terms = wp_get_post_terms($current_id, 'jbg_cat', ['fields'=>'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $tax_query[] = [
                    'taxonomy' => 'jbg_cat',
                    'field'    => 'term_id',
                    'terms'    => array_map('intval', $terms),
                ];
            }
        }

        // واکشی گسترده و مرتب‌سازی نهایی
        $args = [
            'post_type'      => 'jbg_ad',
            'posts_per_page' => $limit * 6,
            'no_found_rows'  => true,
            'post__not_in'   => $current_id ? [$current_id] : [],
            'meta_query'     => [['key'=>'jbg_cpv','compare'=>'EXISTS']],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ($tax_query) $args['tax_query'] = $tax_query;

        $q = new \WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => $p->ID,
                'title' => get_the_title($p),
                'link'  => get_permalink($p),
                'thumb' => get_the_post_thumbnail_url($p->ID, 'medium') ?: '',
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
                    if ($a['boost'] === $b['boost']) return ($b['date'] <=> $a['date']);
                    return ($b['boost'] <=> $a['boost']);
                }
                return ($b['br']   <=> $a['br']);
            }
            return ($b['cpv']     <=> $a['cpv']);
        });

        $items = array_slice($items, 0, $limit);

        // گیت مرحله‌ای: اول، همین ویدیو باید تکمیل شده باشد
        $uid       = get_current_user_id();
        $is_logged = is_user_logged_in();
        $current_ok = false;
        if ($current_id && $is_logged) {
            $w = (bool) get_user_meta($uid, 'jbg_watched_ok_' . $current_id, true);
            $b = (bool) get_user_meta($uid, 'jbg_billed_'     . $current_id, true);
            $qz= (bool) get_user_meta($uid, 'jbg_quiz_passed_' . $current_id, true);
            $current_ok = $w && ($b || $qz);
        }
        $prev_ok = $current_ok;

        $rows = [];
        foreach ($items as $i => $it) {
            $ad_id = (int)$it['ID'];
            $completed = $is_logged
                ? ( (bool) get_user_meta($uid, 'jbg_watched_ok_' . $ad_id, true)
                   && ( (bool) get_user_meta($uid, 'jbg_billed_' . $ad_id, true)
                        || (bool) get_user_meta($uid, 'jbg_quiz_passed_'.$ad_id, true) ) )
                : false;

            $allowed = $completed || $prev_ok;
            $rows[]  = $it + ['completed'=>$completed, 'allowed'=>$allowed];
            $prev_ok = $completed;
        }

        ob_start(); ?>
        <div class="jbg-related">
          <div class="jbg-related-title"><?php echo esc_html($a['title']); ?></div>
          <div class="jbg-related-list">
            <?php foreach ($rows as $it):
                $ad_id = (int)$it['ID'];
                $views  = self::views_count($ad_id);
                $viewsF = self::compact_num($views) . ' بازدید';
                $when   = self::relative_time($ad_id);
                $brand  = self::brand_name($ad_id);
                $thumb  = $it['thumb'] ? ' style="background-image:url(\''.esc_url($it['thumb']).'\')"' : '';
                $is_allowed = (bool)$it['allowed'];
            ?>
              <div class="jbg-related-item <?php echo $is_allowed ? '' : 'is-locked'; ?>" data-ad-id="<?php echo esc_attr($ad_id); ?>">
                <?php if ($is_allowed): ?>
                  <a class="jbg-related-link" href="<?php echo esc_url($it['link']); ?>">
                    <span class="jbg-related-thumb"<?php echo $thumb; ?>></span>
                    <span class="jbg-related-meta">
                      <span class="jbg-related-title-text"><?php echo esc_html($it['title']); ?></span>
                      <span class="jbg-related-sub">
                        <?php if ($brand): ?><span class="brand"><?php echo esc_html($brand); ?></span><span class="dot">•</span><?php endif; ?>
                        <span><?php echo esc_html($viewsF); ?></span><span class="dot">•</span><span><?php echo esc_html($when); ?></span>
                      </span>
                    </span>
                  </a>
                <?php else: ?>
                  <div class="jbg-related-link -nolink">
                    <span class="jbg-related-thumb"<?php echo $thumb; ?>></span>
                    <span class="jbg-related-meta">
                      <span class="jbg-related-title-text"><?php echo esc_html($it['title']); ?></span>
                      <span class="jbg-related-sub">
                        <?php if ($brand): ?><span class="brand"><?php echo esc_html($brand); ?></span><span class="dot">•</span><?php endif; ?>
                        <span><?php echo esc_html($viewsF); ?></span><span class="dot">•</span><span><?php echo esc_html($when); ?></span>
                      </span>
                    </span>
                    <span class="jbg-lock-badge" aria-hidden="true">🔒</span>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <style>
          .jbg-related-item.is-locked{opacity:.6; position:relative}
          .jbg-related-item.is-locked .jbg-related-link.-nolink{cursor:not-allowed}
          .jbg-related-item .jbg-lock-badge{position:absolute; left:8px; top:8px; font-size:18px}
          .jbg-related-link{display:flex; gap:10px; text-decoration:none; border-radius:12px; padding:8px; align-items:center; border:1px solid transparent}
          .jbg-related-link:hover{background:#f8fafc; border-color:#e5e7eb}
          .jbg-related-link.-nolink:hover{background:transparent; border-color:transparent}
        </style>
        <?php
        return (string) ob_get_clean();
    }
}
