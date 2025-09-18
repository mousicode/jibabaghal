<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Two-column layout (Left related | Right main content) + robust Next Video button
 */
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
            .jbg-next-note{margin-top:8px; font-size:12px; color:#6b7280}
        </style>';

        // دکمه: در ابتدا بدون href
        $nextBtn  = '<div class="jbg-next-wrap">';
        $nextBtn .=   '<a id="jbg-next-btn" class="jbg-next-btn is-disabled" data-current-id="'.esc_attr($current_id).'" href="javascript:void(0)">ویدیو بعدی</a>';
        $nextBtn .=   '<div id="jbg-next-note" class="jbg-next-note">بعد از تماشای کامل و پاسخ صحیح، این دکمه فعال می‌شود.</div>';
        $nextBtn .= '</div>';

        // سایدبار
        $related = do_shortcode('[jbg_related limit="10" title="ویدیوهای مرتبط"]');

        // ترکیب
        $html  = '<div class="jbg-two-col">';
        $html .=   '<aside class="jbg-col-aside">'.$related.'</aside>';
        $html .=   '<main class="jbg-col-main">'.$content.$nextBtn.'</main>';
        $html .= '</div>';

        // اسکریپت: با REST چک می‌کند و فقط با URL معتبر فعال می‌کند
        $restUrl = esc_url_raw( rest_url('jbg/v1/next') );
        $script = '<script>
        (function(){
          var btn  = document.getElementById("jbg-next-btn");
          var note = document.getElementById("jbg-next-note");
          if(!btn) return;
          var currentId = parseInt(btn.getAttribute("data-current-id"), 10) || 0;
          var restUrl   = '.json_encode($restUrl).';

          function setEnabled(url){
            if(url){
              btn.classList.remove("is-disabled");
              btn.setAttribute("data-next-url", url);
              if(note){ note.textContent = ""; }
            } else {
              btn.classList.add("is-disabled");
              btn.removeAttribute("data-next-url");
            }
          }

          function fetchNextThenEnable(cb){
            fetch(restUrl + "?current=" + currentId, {method:"GET", credentials:"same-origin"})
              .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, d:d}; }); })
              .then(function(res){
                if(res.ok && res.d && res.d.url){
                  setEnabled(res.d.url);
                  if(typeof cb==="function") cb(true, res.d.url);
                } else {
                  // اگر end=true بود یعنی ویدیوی بعدی وجود ندارد → دکمه غیرفعال بماند
                  setEnabled("");
                  if(typeof cb==="function") cb(false, "");
                }
              })
              .catch(function(){ if(typeof cb==="function") cb(false, ""); });
          }

          // چک اولیه: اگر قبلاً پاس‌شده باشد، URL را می‌گیریم
          fetchNextThenEnable();

          // پس از مشاهده‌ی پیام «پاسخ صحیح بود» چندبار REST را می‌پرسیم تا URL آماده شود
          var retries = 6, delay = 700; // تا ~4 ثانیه
          function pollAfterSuccess(){
            fetchNextThenEnable(function(ok){
              if(!ok && retries-- > 0) setTimeout(pollAfterSuccess, delay);
            });
          }
          var obs = new MutationObserver(function(){
            var txt = document.body.innerText || "";
            if(/پاسخ\\s*صحیح\\s*بود/.test(txt)){ pollAfterSuccess(); }
          });
          obs.observe(document.body, {childList:true, subtree:true, characterData:true});

          btn.addEventListener("click", function(e){
            var url = btn.getAttribute("data-next-url") || "";
            if(!url){ e.preventDefault(); return; }
            // ناوبری فقط با URL معتبر
            window.location.assign(url);
          });
        })();
        </script>';

        static $once=false;
        if (!$once) { $html = $style . $html . $script; $once = true; }
        else { $html .= $script; }

        return $html;
    }
}
