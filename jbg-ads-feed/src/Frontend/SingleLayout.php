<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

if (!class_exists(__NAMESPACE__ . '\\SingleLayout')):

class SingleLayout {
    public static function register(): void {
        // در خروجی محتوای پست سینگل (بدون دستکاری خودِ محتوا) یک رپر دو ستونه اضافه می‌کنیم
        add_filter('the_content', [self::class, 'wrap'], 30);
    }

    /** ترتیب واحد (مثل آرشیو): cpv↓, budget_remaining↓, priority_boost↓, date↓ — فقط در دسته(های) همین ویدیو */
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
        ]);

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'ID'    => (int)$p->ID,
                'cpv'   => (int)get_post_meta($p->ID,'jbg_cpv',true),
                'br'    => (int)get_post_meta($p->ID,'jbg_budget_remaining',true),
                'boost' => (int)get_post_meta($p->ID,'jbg_priority_boost',true),
                'date'  => get_post_time('U', true, $p->ID),
            ];
        }
        wp_reset_postdata();

        usort($items, function($a,$b){
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

    /** لینک ویدیو بعدی طبق همان ترتیب */
    private static function next_url_for(int $current_id): string {
        $items = self::ordered_items_for($current_id);
        if (!$items) return '';
        $ids = array_map(fn($it)=>(int)$it['ID'], $items);
        $idx = array_search($current_id, $ids, true);
        if ($idx === false || $idx >= count($ids)-1) return '';
        return get_permalink((int)$ids[$idx+1]) ?: '';
    }

    public static function wrap($content) {
        // فقط برای سینگل jbg_ad و حلقه اصلی
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $current_id = get_the_ID();
        $next_url   = self::next_url_for($current_id);

        // سایدبار (لیست مرتبط) — همانی که قبلاً خوب کار می‌کرد
        $sidebar = do_shortcode('[jbg_related limit="12" title="ویدیوهای مرتبط"]');

        // استایل خیلی سبک برای گرید؛ به پلیر/کنترل‌ها دست نمی‌زند
        $style = '<style>
          .jbg-two-col{display:grid;grid-template-columns:1fr;gap:24px}
          @media(min-width:992px){.jbg-two-col{grid-template-columns:360px 1fr}}
          .jbg-two-col > aside{order:1}
          .jbg-two-col > main{order:2}
          .jbg-next-wrap{margin-top:16px;text-align:right}
          .jbg-next-btn{display:inline-block;padding:10px 16px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;opacity:.5;pointer-events:none}
          .jbg-next-btn[aria-disabled="false"]{opacity:1;pointer-events:auto}
          .jbg-next-hint{margin-right:8px;font-size:12px;color:#6b7280}
        </style>';

        // دکمه «ویدیو بعدی» — لینک از الان درست است، ولی قفل است تا آزمون پاس شود
        $btn  = '<div class="jbg-next-wrap">';
        if ($next_url) {
            $btn .= '<a id="jbg-next-btn" class="jbg-next-btn" href="'.esc_url($next_url).'" aria-disabled="true">ویدیو بعدی</a>';
            $btn .= '<small id="jbg-next-hint" class="jbg-next-hint">بعد از قبولی آزمون این ویدیو، دکمه فعال می‌شود.</small>';
        } else {
            $btn .= '<small id="jbg-next-hint" class="jbg-next-hint">این آخرین ویدیو است.</small>';
        }
        $btn .= '</div>';

        // اگر قبلاً این کاربر آزمون همین ویدیو را پاس کرده باشد، دکمه از ابتدا باز است
        $passed = is_user_logged_in() ? (bool)get_user_meta(get_current_user_id(),'jbg_quiz_passed_'.$current_id,true) : false;

        $script = '<script>(function(){
          var btn=document.getElementById("jbg-next-btn"); var hint=document.getElementById("jbg-next-hint");
          if(!btn) return;
          '.($passed ? 'btn.setAttribute("aria-disabled","false"); if(hint) hint.textContent="";' : '').'
          function unlock(){btn.setAttribute("aria-disabled","false"); if(hint) hint.textContent="";}
          // ایونت را اسکریپت آزمون بعد از پاسخ صحیح فایر می‌کند:
          document.addEventListener("jbg:quiz_passed", function(e){
            try{ var id=e&&e.detail&&e.detail.adId?parseInt(e.detail.adId,10):0; if(!id || id==='.$current_id.') unlock(); }
            catch(_){ unlock(); }
          });
        })();</script>';

        // چیدمان دو ستونه: سایدبار چپ، محتوای اصلی (پلیر + Start Quiz + هرچیز دیگر) راست
        $html = '<div class="jbg-two-col"><aside>'.$sidebar.'</aside><main>'.$content.$btn.'</main></div>';

        return $style . $html . $script;
    }
}

endif;
