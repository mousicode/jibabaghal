<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class SingleLayout {
    public static function register(): void {
        add_filter('the_content', [self::class, 'wrap'], 20);
    }

    /** ترتیب واحد: cpv↓, budget_remaining↓, priority_boost↓, date↓ فقط داخل دسته(های) همین ویدیو */
    private static function ordered_items_for(int $current_id): array {
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
            'tax_query'      => $tax_query ?: null,
            'fields'         => 'all',
        ]);

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => (int)$p->ID,
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

        return $items;
    }

    /** پیدا کردن لینکِ آیتم بعدی طبق همین ترتیب */
    private static function next_url_for(int $current_id): string {
        $items = self::ordered_items_for($current_id);
        if (!$items) return '';
        $ids = array_map(fn($it)=> (int)$it['ID'], $items);
        $idx = array_search($current_id, $ids, true);
        if ($idx === false || $idx >= count($ids)-1) return '';
        $next_id = (int)$ids[$idx+1];
        return get_permalink($next_id) ?: '';
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $current_id = get_the_ID();
        $next_url   = self::next_url_for($current_id);   // ← لینک ویدیو بعدی را همین‌جا می‌گذاریم
        $sidebar    = do_shortcode('[jbg_related limit="12" title="ویدیوهای مرتبط"]');

        // دکمه پیش‌فرض غیرفعال است؛ فقط ایونت «قبولی آزمون» قفل را برمی‌دارد
        $style = '<style>
          .jbg-two-col{display:grid;grid-template-columns:1fr;gap:24px}
          @media(min-width:768px){.jbg-two-col{grid-template-columns:360px 1fr}}
          .jbg-next-wrap{margin-top:16px;text-align:right}
          .jbg-next-btn{display:inline-block;padding:10px 16px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;opacity:.5;pointer-events:none}
          .jbg-next-btn[aria-disabled="false"]{opacity:1;pointer-events:auto}
          .jbg-next-hint{margin-right:8px;font-size:12px;color:#6b7280}
        </style>';

        $btn  = '<div class="jbg-next-wrap">';
        if ($next_url) {
            $btn .= '<a id="jbg-next-btn" class="jbg-next-btn" aria-disabled="true" data-current-id="'.esc_attr($current_id).'" href="'.esc_url($next_url).'">ویدیو بعدی</a>';
            $btn .= '<small id="jbg-next-hint" class="jbg-next-hint">برای رفتن به مرحله بعد، آزمون این ویدیو را درست پاسخ بده.</small>';
        } else {
            $btn .= '<a id="jbg-next-btn" class="jbg-next-btn" aria-disabled="true" style="display:none" href="#">ویدیو بعدی</a>';
            $btn .= '<small id="jbg-next-hint" class="jbg-next-hint">این آخرین ویدیو است.</small>';
        }
        $btn .= '</div>';

        // فعال‌سازی فقط با ایونت «قبولی آزمون»
        $script = '<script>
        (function(){
          var btn  = document.getElementById("jbg-next-btn");
          var hint = document.getElementById("jbg-next-hint");
          if(!btn) return;

          // اگر قبلاً این کاربر آزمون همین ویدیو را پاس کرده، دکمه را از همان ابتدا باز کن
          try{
            var phpUser = '.(is_user_logged_in()? 'true':'false').';
            var phpPassed = phpUser ? '.( get_user_meta(get_current_user_id(),'jbg_quiz_passed_'.get_the_ID(), true) ? 'true':'false' ).' : false;
            if(phpPassed){ btn.setAttribute("aria-disabled","false"); if(hint) hint.textContent=""; }
          }catch(e){}

          function unlock(){
            btn.setAttribute("aria-disabled","false");
            if(hint) hint.textContent="";
          }

          // ایونت سراسری که باید بعد از تایید پاسخ صحیح فایر شود:
          document.addEventListener("jbg:quiz_passed", function(ev){
            try{
              var id = (ev && ev.detail && ev.detail.adId) ? parseInt(ev.detail.adId, 10) : 0;
              if(!id || id === '.intval($current_id).') unlock();
            }catch(_){ unlock(); }
          });
        })();
        </script>';

        $html  = '<div class="jbg-two-col"><aside>'.$sidebar.'</aside><main>'.$content.$btn.'</main></div>';

        return $style.$html.$script;
    }
}
