<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Two-column layout (Left related | Right main content) + Next Video button
 */
class SingleLayout {

    public static function register(): void {
        add_filter('the_content', [self::class, 'wrap'], 99);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $current_id = get_the_ID();
        // محاسبهٔ «ویدیو بعدی» با همان منطق مرتب‌سازی
        $next = self::compute_next_item($current_id);
        $next_id  = $next['ID']  ?? 0;
        $next_url = $next['url'] ?? '';

        // آیا همین ویدیو پاس شده است؟
        $current_ok = is_user_logged_in()
            ? ( (bool) get_user_meta(get_current_user_id(), 'jbg_watched_ok_'.$current_id, true)
            &&  (bool) get_user_meta(get_current_user_id(), 'jbg_billed_'    .$current_id, true) )
            : false;

        $style = '<style id="jbg-single-2col-css">
            .single-jbg_ad .jbg-two-col{direction:ltr; display:grid; grid-template-columns:1fr; gap:24px; align-items:start;}
            @media(min-width:768px){ .single-jbg_ad .jbg-two-col{ grid-template-columns:360px 1fr; } }
            .single-jbg_ad .jbg-col-aside,.single-jbg_ad .jbg-col-main{ direction:rtl; }
            @media(min-width:768px){ .single-jbg_ad .jbg-col-aside{ position:sticky; top:24px; } body.admin-bar .single-jbg_ad .jbg-col-aside{ top:56px; } }

            /* Next button */
            .jbg-next-wrap{margin-top:16px;}
            .jbg-next-btn{display:inline-block; padding:10px 16px; border-radius:10px; background:#2563eb; color:#fff; text-decoration:none; font-weight:700}
            .jbg-next-btn.is-disabled{background:#cbd5e1; pointer-events:none; cursor:not-allowed}
        </style>';

        // دکمهٔ «ویدیو بعدی» (ابتدا اگر پاس نشده باشد غیرفعال است)
        $nextBtn = '';
        if ($next_url) {
            $nextBtn = '<div class="jbg-next-wrap">'
                     . '<a id="jbg-next-btn" class="jbg-next-btn'.($current_ok?'':' is-disabled').'" '
                     . ($current_ok ? 'href="'.esc_url($next_url).'"' : '')
                     . '>ویدیو بعدی</a>'
                     . '</div>';
        }

        // سایدبار
        $related = do_shortcode('[jbg_related limit="10" title="ویدیوهای مرتبط"]');

        // ترکیب
        $html  = '<div class="jbg-two-col">';
        $html .=   '<aside class="jbg-col-aside">'.$related.'</aside>';
        $html .=   '<main class="jbg-col-main">'.$content.$nextBtn.'</main>';
        $html .= '</div>';

        // JS: بعد از مشاهدهٔ پیام «پاسخ صحیح بود»، دکمه فعال و آیتم بعدی در سایدبار unlock
        $script = '';
        if ($next_url && $next_id) {
            $script = '<script>
              (function(){
                var nextUrl = '.json_encode($next_url).';
                var nextId  = '.json_encode((int)$next_id).';
                function unlockUI(){
                  var btn = document.getElementById("jbg-next-btn");
                  if(btn){ btn.classList.remove("is-disabled"); btn.setAttribute("href", nextUrl); }
                  // سایدبار: فعال کردن آیتم بعدی
                  var item = document.querySelector(".jbg-related-item[data-ad-id=\'"+nextId+"\']");
                  if(item){
                    item.classList.remove("is-locked");
                    var nolink = item.querySelector(".jbg-related-link.-nolink");
                    if(nolink){
                      var a = document.createElement("a");
                      a.className = "jbg-related-link";
                      a.href = nextUrl;
                      a.innerHTML = nolink.innerHTML;
                      nolink.parentNode.replaceChild(a, nolink);
                    }
                  }
                }
                // اگر از قبل پاس شده، فوری unlock کن
                if('.($current_ok?'true':'false').'){ unlockUI(); return; }

                // MutationObserver برای پیام موفقیت آزمون (مانند «پاسخ صحیح بود»)
                var obs = new MutationObserver(function(){
                  var txt = document.body.innerText || "";
                  if(/پاسخ\\s*صحیح\\s*بود/.test(txt)){ unlockUI(); obs.disconnect(); }
                });
                obs.observe(document.body, {childList:true, subtree:true, characterData:true});
              })();
            </script>';
        }

        static $once=false;
        if (!$once) { $html = $style . $html . $script; $once = true; }
        else { $html .= $script; }

        return $html;
    }

    /** محاسبهٔ آیتم بعدی با همان ترتیب (CPV ↓، بودجه ↓، Boost ↓، تاریخ ↓) در همان دسته‌ها */
    private static function compute_next_item(int $current_id): array {
        $tax_query = [];
        $terms = wp_get_post_terms($current_id, 'jbg_cat', ['fields'=>'ids']);
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
            'meta_query'     => [['key'=>'jbg_cpv','compare'=>'EXISTS']],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => $tax_query ?: [],
        ]);

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
                    if ($a['boost'] === $b['boost']) return ($b['date'] <=> $a['date']);
                    return ($b['boost'] <=> $a['boost']);
                }
                return ($b['br'] <=> $a['br']);
            }
            return ($b['cpv'] <=> $a['cpv']);
        });

        // پیدا کردن اندیس فعلی و آیتم بعدی
        $idx = -1;
        foreach ($items as $i => $it) {
            if ((int)$it['ID'] === (int)$current_id) { $idx = $i; break; }
        }
        if ($idx < 0) return [];
        $next = $items[$idx+1] ?? null;
        if (!$next) return [];
        return ['ID' => (int)$next['ID'], 'url' => get_permalink((int)$next['ID'])];
    }
}
