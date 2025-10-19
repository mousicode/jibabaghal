<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class CarouselShortcode {

    public static function register(): void {
        add_shortcode('jbg_ads_carousel', [self::class, 'render']);
        // استایل/اسکریپت فقط وقتی این شورت‌کد در صفحه هست تزریق می‌شوند
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_assets']);
    }

    private static $present = false;

    public static function maybe_enqueue_assets(): void {
        if (!self::$present) return;
        // بدون وابستگی به دارایی‌های دیگر. نام‌های یکتا برای جلوگیری از تداخل.
        wp_register_style('jbg-ads-carousel', '', [], '1.0.0');
        $css = <<<CSS
.jbg-ads-carousel{margin:8px 0}
.jbg-ads-carousel .jbg-ac-head{display:flex;align-items:center;justify-content:space-between;margin:0 8px 10px}
.jbg-ads-carousel .jbg-ac-title{font-weight:600;font-size:1.1rem}
.jbg-ads-carousel .jbg-ac-track{display:flex;gap:12px;overflow:auto;scroll-snap-type:x mandatory;padding:4px 8px}
.jbg-ads-carousel .jbg-ac-track::-webkit-scrollbar{height:8px}
.jbg-ads-carousel .jbg-ac-item{flex:0 0 auto;width:240px;scroll-snap-align:start;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff}
.jbg-ads-carousel .jbg-ac-thumb{display:block;position:relative;width:100%;padding-top:56.25%;background:#f3f4f6}
.jbg-ads-carousel .jbg-ac-thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.jbg-ads-carousel .jbg-ac-body{padding:8px 10px}
.jbg-ads-carousel .jbg-ac-title2{margin:0 0 6px;font-size:.95rem;line-height:1.35}
.jbg-ads-carousel .jbg-ac-meta{font-size:.8rem;color:#6b7280;display:flex;gap:8px;flex-wrap:wrap}
.jbg-ads-carousel .jbg-ac-ctrl{display:flex;gap:6px}
.jbg-ads-carousel .jbg-ac-btn{border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer}
@media (max-width:480px){ .jbg-ads-carousel .jbg-ac-item{width:78vw} }
CSS;
        wp_add_inline_style('jbg-ads-carousel', $css);
        wp_enqueue_style('jbg-ads-carousel');

        wp_register_script('jbg-ads-carousel', '', [], '1.0.0', true);
        $js = <<<JS
(function(){
  function q(sel, root){return (root||document).querySelector(sel)}
  function qa(sel, root){return Array.prototype.slice.call((root||document).querySelectorAll(sel))}
  function slide(track, dir){
    var item = track.querySelector('.jbg-ac-item'); if(!item) return;
    var w = item.getBoundingClientRect().width + 12; // gap
    track.scrollBy({left: dir * w, behavior:'smooth'});
  }
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.jbg-ac-btn'); if(!btn) return;
    var wrap = btn.closest('.jbg-ads-carousel'); if(!wrap) return;
    var track = q('.jbg-ac-track', wrap);
    slide(track, btn.dataset.dir === 'next' ? 1 : -1);
  }, true);

  qa('.jbg-ads-carousel[data-autoplay="1"]').forEach(function(wrap){
    var interval = parseInt(wrap.dataset.interval||'3500',10);
    var track = q('.jbg-ac-track', wrap);
    var t = setInterval(function(){ slide(track, 1); }, interval);
    // مکث هنگام تعامل کاربر
    ['mouseenter','touchstart','focusin'].forEach(function(ev){
      wrap.addEventListener(ev, function(){ clearInterval(t); }, {passive:true, once:true});
    });
  });
})();
JS;
        wp_add_inline_script('jbg-ads-carousel', $js);
        wp_enqueue_script('jbg-ads-carousel');
    }

    private static function brand_name(int $post_id): string {
        $terms = wp_get_post_terms($post_id, 'jbg_brand', ['fields'=>'names']);
        if (is_wp_error($terms) || empty($terms)) return '';
        return (string)$terms[0];
    }

    private static function relative_time(int $post_id): string {
        $t = get_post_time('U', true, $post_id);
        $d = time() - (int)$t;
        if ($d < 60) return 'لحظاتی پیش';
        if ($d < 3600) return floor($d/60).' دقیقه پیش';
        if ($d < 86400) return floor($d/3600).' ساعت پیش';
        if ($d < 86400*30) return floor($d/86400).' روز پیش';
        return get_the_date('', $post_id);
    }

    public static function render($atts = []): string {
        self::$present = true;

        $a = shortcode_atts([
            'limit'   => 10,
            'autoplay'=> '1',
            'interval'=> '3500',
            'arrows'  => '1',
            'title'   => '',
        ], $atts, 'jbg_ads_carousel');

        $q = new \WP_Query([
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'posts_per_page'      => max(1, (int)$a['limit']),
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'suppress_filters'    => true,
            'orderby'             => ['date'=>'DESC','ID'=>'DESC'],
            'lang'                => 'all',
        ]);

        if (!$q->have_posts()) return '';

        ob_start();
        $autoplay = (int) !in_array((string)$a['autoplay'], ['0','false'], true);
        $arrows   = (int) !in_array((string)$a['arrows'],   ['0','false'], true);
        $interval = max(1200, (int)$a['interval']);

        echo '<section class="jbg-ads-carousel" data-autoplay="'.$autoplay.'" data-interval="'.$interval.'">';
        echo '<div class="jbg-ac-head">';
        if ($a['title']!=='') echo '<div class="jbg-ac-title">'.esc_html($a['title']).'</div>';
        if ($arrows) {
            echo '<div class="jbg-ac-ctrl">';
            echo '<button type="button" class="jbg-ac-btn" data-dir="prev" aria-label="قبلی">‹</button>';
            echo '<button type="button" class="jbg-ac-btn" data-dir="next" aria-label="بعدی">›</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="jbg-ac-track" role="list">';
        while ($q->have_posts()) { $q->the_post();
            $id    = get_the_ID();
            $link  = get_permalink($id);
            $title = get_the_title($id);
            $thumb = get_the_post_thumbnail_url($id, 'medium') ?: '';
            $brand = self::brand_name($id);
            $when  = self::relative_time($id);

            echo '<article class="jbg-ac-item" role="listitem">';
            echo '  <a class="jbg-ac-thumb" href="'.esc_url($link).'" aria-label="'.esc_attr($title).'">';
            if ($thumb) echo '<img src="'.esc_url($thumb).'" alt="">';
            echo '  </a>';
            echo '  <div class="jbg-ac-body">';
            echo '    <h3 class="jbg-ac-title2"><a href="'.esc_url($link).'">'.esc_html($title).'</a></h3>';
            echo '    <div class="jbg-ac-meta">';
            if ($brand) echo '      <span>'.esc_html($brand).'</span>';
            echo '      <span>'.$when.'</span>';
            echo '    </div>';
            echo '  </div>';
            echo '</article>';
        }
        echo '</div></section>';

        wp_reset_postdata();
        return (string) ob_get_clean();
    }
}
