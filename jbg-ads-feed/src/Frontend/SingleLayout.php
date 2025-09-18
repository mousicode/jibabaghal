<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class SingleLayout {
    public static function register(): void {
        add_filter('the_content', [self::class, 'wrap'], 20);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $current_id = get_the_ID();
        $sidebar = do_shortcode('[jbg_related limit="12" title="ویدیوهای مرتبط"]');

        $style = '<style>
          .jbg-two-col{display:grid;grid-template-columns:1fr;gap:24px}
          @media(min-width:768px){.jbg-two-col{grid-template-columns:360px 1fr}}
          .jbg-next-wrap{margin-top:16px;text-align:right}
          .jbg-next-btn{display:inline-block;padding:10px 16px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700;opacity:.5;pointer-events:none}
          .jbg-next-btn[aria-disabled="false"]{opacity:1;pointer-events:auto}
          .jbg-next-hint{margin-right:8px;font-size:12px;color:#6b7280}
        </style>';

        $btn  = '<div class="jbg-next-wrap">';
        $btn .=   '<a id="jbg-next-btn" class="jbg-next-btn" aria-disabled="true" data-current-id="'.esc_attr($current_id).'" href="#">ویدیو بعدی</a>';
        $btn .=   '<small id="jbg-next-hint" class="jbg-next-hint"></small>';
        $btn .= '</div>';

        $rest = esc_url_raw( rest_url('jbg/v1/next') );
        $script = '<script>(function(){
          var btn  = document.getElementById("jbg-next-btn");
          var hint = document.getElementById("jbg-next-hint");
          if(!btn) return;
          var current = parseInt(btn.getAttribute("data-current-id"),10)||0;
          var REST = "'.$rest.'";

          function enable(url){
            if(!url) return;
            btn.href = url;
            btn.setAttribute("aria-disabled","false");
            if(hint) hint.textContent="";
          }
          function fetchNext(cb){
            fetch(REST+"?current="+current,{credentials:"same-origin"})
              .then(r=>r.json().then(d=>({ok:r.ok,d})))
              .then(res=>{
                if(res.ok && res.d && res.d.url){ enable(res.d.url); if(cb)cb(true); }
                else { if(cb)cb(false); }
              }).catch(()=>{ if(cb)cb(false); });
          }

          // بعد از قبولی آزمون: ایونت سراسری
          document.addEventListener("jbg:quiz_passed", function(e){ fetchNext(); });

          // بکاپ: چند بار هم خودمان چک می‌کنیم
          var tries=5; function poll(){ if(tries--<=0) return; hint.textContent="در حال بررسی آزاد شدن..."; fetchNext(function(ok){ if(!ok) setTimeout(poll,1200); }); }
          poll();

          btn.addEventListener("click", function(e){
            if(btn.getAttribute("aria-disabled")==="true"){ e.preventDefault(); fetchNext(); }
          });
        })();</script>';

        $html  = '<div class="jbg-two-col"><aside>'.$sidebar.'</aside><main>'.$content.$btn.'</main></div>';

        return $style.$html.$script;
    }
}
