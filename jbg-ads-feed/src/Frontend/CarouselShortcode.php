<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class CarouselShortcode {
    private static $present = false;

    public static function register(): void {
        add_shortcode('jbg_ads_carousel', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
    }

    public static function assets(): void {
        if (!self::$present) return;

        // فقط لایهٔ کاروسل؛ کارت‌ها از [jbg_ads] می‌آیند
        wp_register_style('jbg-ads-carousel', '', [], '1.2.0');
        $css = <<<CSS
.jbg-ads-carousel{position:relative;margin:8px 0}
.jbg-ads-carousel .jbg-ac-head{display:flex;align-items:center;justify-content:space-between;margin:0 8px 10px}
.jbg-ads-carousel .jbg-ac-title{font-weight:600;font-size:1.1rem}
/* گرید افقی: همیشه n ستون در نما */
.jbg-ads-carousel .jbg-ac-track{
  display:grid;
  grid-auto-flow:column;
  gap:12px;
  overflow-x:auto;
  scroll-snap-type:x mandatory;
  padding:4px 8px;
  grid-auto-columns: calc((100% - (var(--ac-cols,4) - 1)*12px)/var(--ac-cols,4));
}
/* حذف اثر کانتینر شورت‌کد [jbg_ads] */
.jbg-ads-carousel .jbg-ac-track > *{display:contents}
/* هر کارت یک ستون و نقطهٔ اسنپ */
.jbg-ads-carousel .jbg-ac-track .jbg-ad-card{scroll-snap-align:start}
.jbg-ads-carousel .jbg-ac-ctrl{display:flex;gap:6px}
.jbg-ads-carousel .jbg-ac-btn{border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer}
@media (max-width:640px){
  .jbg-ads-carousel .jbg-ac-track{grid-auto-columns:80vw}
}
CSS;
        wp_add_inline_style('jbg-ads-carousel', $css);
        wp_enqueue_style('jbg-ads-carousel');

        wp_register_script('jbg-ads-carousel', '', [], '1.2.0', true);
        $js = <<<JS
(function(){
  function q(s,r){return (r||document).querySelector(s)}
  function qa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))}
  function slide(track, dir){
    var item = track.querySelector('.jbg-ad-card');
    if(!item) return;
    var w = item.getBoundingClientRect().width + 12; // عرض یک ستون + فاصله
    track.scrollBy({left: dir * w, behavior:'smooth'});
  }
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.jbg-ac-btn'); if(!btn) return;
    var wrap = btn.closest('.jbg-ads-carousel'); if(!wrap) return;
    slide(q('.jbg-ac-track', wrap), btn.dataset.dir === 'next' ? 1 : -1);
  }, true);

  qa('.jbg-ads-carousel[data-autoplay="1"]').forEach(function(wrap){
    var track = q('.jbg-ac-track', wrap);
    var interval = parseInt(wrap.dataset.interval||'3500',10);
    var t = setInterval(function(){ slide(track, 1); }, interval);
    ['mouseenter','touchstart','focusin'].forEach(function(ev){
      wrap.addEventListener(ev, function(){ clearInterval(t); }, {passive:true, once:true});
    });
  });
})();
JS;
        wp_add_inline_script('jbg-ads-carousel', $js);
        wp_enqueue_script('jbg-ads-carousel');
    }

    public static function render($atts = []): string {
        self::$present = true;

        $a = shortcode_atts([
            'limit'   => 10,
            'cols'    => '4',
            'autoplay'=> '1',
            'interval'=> '3500',
            'arrows'  => '1',
            'title'   => '',
        ], $atts, 'jbg_ads_carousel');

        $limit    = max(1, (int)$a['limit']);
        $cols     = max(1, (int)$a['cols']);
        $autoplay = (int)!in_array((string)$a['autoplay'], ['0','false'], true);
        $arrows   = (int)!in_array((string)$a['arrows'],   ['0','false'], true);
        $interval = max(1200, (int)$a['interval']);

        // رندر کارت‌ها با شورت‌کد اصلی
        $cards_html = do_shortcode('[jbg_ads limit="'.$limit.'"]');

        ob_start();
        echo '<section class="jbg-ads-carousel" data-autoplay="'.$autoplay.'" data-interval="'.$interval.'">';
        echo   '<div class="jbg-ac-head">';
        if ($a['title']!=='') echo '<div class="jbg-ac-title">'.esc_html($a['title']).'</div>';
        if ($arrows) {
            echo   '<div class="jbg-ac-ctrl">';
            echo     '<button type="button" class="jbg-ac-btn" data-dir="prev" aria-label="قبلی">‹</button>';
            echo     '<button type="button" class="jbg-ac-btn" data-dir="next" aria-label="بعدی">›</button>';
            echo   '</div>';
        }
        echo   '</div>';
        echo   '<div class="jbg-ac-track" role="list" style="--ac-cols:'.$cols.'">';
        echo     $cards_html; // همان مارکاپ کارت‌های [jbg_ads]
        echo   '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }
}
