<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class SingleLayout {
    public static function register(): void {
        add_filter('the_content', [self::class, 'wrap'], 99);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $current_id = get_the_ID();
        $style = '<style id="jbg-single-2col-css">
            .single-jbg_ad .jbg-two-col{direction:ltr; display:grid; grid-template-columns:1fr; gap:24px; align-items:start;}
            @media(min-width:768px){ .single-jbg_ad .jbg-two-col{ grid-template-columns:360px 1fr; } }
            .single-jbg_ad .jbg-col-aside,.single-jbg_ad .jbg-col-main{ direction:rtl; }
            @media(min-width:768px){ .single-jbg_ad .jbg-col-aside{ position:sticky; top:24px; } body.admin-bar .single-jbg_ad .jbg-col-aside{ top:56px; } }
            .jbg-next-wrap{margin-top:16px;}
            .jbg-next-btn{display:inline-block; padding:10px 16px; border-radius:10px; background:#2563eb; color:#fff; text-decoration:none; font-weight:700}
            .jbg-next-btn.is-disabled{background:#cbd5e1; pointer-events:none; cursor:not-allowed}
        </style>';

        // دکمه بدون href – با کلیک، REST را صدا می‌زنیم
        $nextBtn  = '<div class="jbg-next-wrap">';
        $nextBtn .=   '<a id="jbg-next-btn" class="jbg-next-btn is-disabled" data-current-id="'.esc_attr($current_id).'" href="javascript:void(0)">ویدیو بعدی</a>';
        $nextBtn .= '</div>';

        // سایدبار
        $related = do_shortcode('[jbg_related limit="10" title="ویدیوهای مرتبط"]');

        // ترکیب
        $html  = '<div class="jbg-two-col">';
        $html .=   '<aside class="jbg-col-aside">'.$related.'</aside>';
        $html .=   '<main class="jbg-col-main">'.$content.$nextBtn.'</main>';
        $html .= '</div>';

        // اسکریپت: فعال‌سازی دکمه پس از پاس آزمون + کلیک -> REST check
        $restUrl = esc_url_raw( rest_url('jbg/v1/next') );
        $script = '<script>
        (function(){
          var btn = document.getElementById("jbg-next-btn");
          if(!btn) return;
          var currentId = parseInt(btn.getAttribute("data-current-id"), 10) || 0;
          var restUrl = '.json_encode($restUrl).';

          function enableBtn(){ btn.classList.remove("is-disabled"); }

          // اگر همین حالا پاس شده، دکمه رو فعال کن
          // (با یک HEAD check ساده به REST – اگر 200 داد، فعال می‌کنیم)
          fetch(restUrl + "?current=" + currentId, {method:"GET", credentials:"same-origin"})
            .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, d:d}; }); })
            .then(function(res){ if(res.ok && res.d && (res.d.url || res.d.id===0)){ enableBtn(); } })
            .catch(function(){});

          // گوش به پیام «پاسخ صحیح بود»
          var obs = new MutationObserver(function(){
            var txt = document.body.innerText || "";
            if(/پاسخ\\s*صحیح\\s*بود/.test(txt)){ enableBtn(); }
          });
          obs.observe(document.body, {childList:true, subtree:true, characterData:true});

          btn.addEventListener("click", function(e){
            if(btn.classList.contains("is-disabled")) { e.preventDefault(); return; }
            btn.classList.add("is-disabled");
            fetch(restUrl + "?current=" + currentId, {method:"GET", credentials:"same-origin"})
              .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, d:d}; }); })
              .then(function(res){
                if(res.ok && res.d && res.d.url){
                  window.location.assign(res.d.url);
                } else {
                  // هنوز پاس نشده یا ویدیوی بعدی وجود ندارد
                  btn.classList.remove("is-disabled");
                }
              })
              .catch(function(){ btn.classList.remove("is-disabled"); });
          });
        })();
        </script>';

        static $once=false;
        if (!$once) { $html = $style . $html . $script; $once = true; }
        else { $html .= $script; }

        return $html;
    }
}
