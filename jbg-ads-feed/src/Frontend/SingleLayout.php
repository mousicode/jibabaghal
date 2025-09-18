<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class SingleLayout {
    public static function register(): void {
        add_filter('the_content', [self::class, 'wrap'], 20);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        // سایدبار مرتبط‌ها
        $sidebar = do_shortcode('[jbg_related limit="12" title="ویدیوهای مرتبط"]');

        // دکمه و اسکریپت
        $id  = get_the_ID();
        $btn = '
        <div id="jbg-next-wrap" style="margin:16px 0 24px;direction:rtl;text-align:right">
            <a id="jbg-next-btn" class="button" href="#" aria-disabled="true"
               style="opacity:.5;pointer-events:none;border-radius:10px;padding:10px 14px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600">
               ویدیو بعدی
            </a>
            <small id="jbg-next-hint" style="margin-right:8px;color:#666"></small>
        </div>
        <script>
        (function(){
            const current = '.(int)$id.';
            const REST = "'.esc_js(esc_url_raw( rest_url('jbg/v1/next') ) ).'";
            const btn  = document.getElementById("jbg-next-btn");
            const hint = document.getElementById("jbg-next-hint");

            function setBtn(url){
                if (!url) return;
                btn.href = url;
                btn.style.opacity = "1";
                btn.style.pointerEvents = "auto";
                btn.setAttribute("aria-disabled","false");
                hint.textContent = "";
            }

            function pollNext(tries){
                fetch(REST + "?current=" + current, {credentials:"same-origin"})
                  .then(r => r.text().then(t => { // robust JSON parse
                       let j=null; try{ j = JSON.parse(t); }catch(e){ j=null; }
                       return {status:r.status, data:j};
                  }))
                  .then(res => {
                      if (res.status === 200 && res.data && res.data.ok && !res.data.end && res.data.url){
                          setBtn(res.data.url);
                      } else if (tries > 0) {
                          hint.textContent = "در حال بررسی آزاد شدن…";
                          setTimeout(()=>pollNext(tries-1), 1500);
                      } else {
                          // هنوز قفل است
                          hint.textContent = "برای باز شدن، ویدیو فعلی را کامل ببینید و آزمون را صحیح پاس کنید.";
                      }
                  })
                  .catch(()=> { if (tries>0) setTimeout(()=>pollNext(tries-1), 1500); });
            }

            // تلاش اولیه (ممکن است قبلا آزاد شده باشد)
            pollNext(1);

            // وقتی پیام «پاسخ صحیح بود» در DOM ظاهر شد → شروع polling جدی
            const mo = new MutationObserver(() => {
                const ok = Array.from(document.body.querySelectorAll("*"))
                              .some(n => /پاسخ\\s*صحیح\\s*بود/u.test(n.textContent||""));
                if (ok) { pollNext(10); }
            });
            mo.observe(document.body, {subtree:true, childList:true, characterData:true});

            // کلیک: اگر هنوز آزاد نشده باشد، مانع شو
            btn.addEventListener("click", function(e){
                if (btn.getAttribute("aria-disabled") === "true") {
                    e.preventDefault();
                    pollNext(5);
                }
            });
        })();
        </script>';

        // چیدمان: چپ سایدبار، راست پلیر/محتوا
        $html  = '<div class="jbg-two-col" style="display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start">';
        $html .=   '<aside>'.$sidebar.'</aside>';
        $html .=   '<main>'.$content.$btn.'</main>';
        $html .= '</div>';

        return $html;
    }
}
