<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class CarouselShortcode {

    private static $present = false;

    public static function register(): void {
        add_shortcode('jbg_ads_carousel', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_assets']);
    }

    public static function maybe_enqueue_assets(): void {
        if (!self::$present) return;

        // فقط لایهٔ کاروسل؛ استایل کارت‌ها از شورت‌کد موجود می‌آید
        wp_register_style('jbg-ads-carousel', '', [], '1.0.1');
        $css = <<<CSS
.jbg-ads-carousel{position:relative;margin:8px 0}
.jbg-ads-carousel .jbg-ac-head{display:flex;align-items:center;justify-content:space-between;margin:0 8px 10px}
.jbg-ads-carousel .jbg-ac-title{font-weight:600;font-size:1.1rem}
.jbg-ads-carousel .jbg-ac-track{display:flex;gap:12px;overflow:auto;scroll-snap-type:x mandatory;padding:4px 8px}
.jbg-ads-carousel .jbg-ac-track::-webkit-scrollbar{height:8px}
.jbg-ads-carousel .jbg-ac-item{flex:0 0 var(--ac-w,260px);scroll-snap-align:start}
.jbg-ads-carousel .jbg-ac-item > *{display:block}
.jbg-ads-carousel .jbg-ac-ctrl{display:flex;gap:6px}
.jbg-ads-carousel .jbg-ac-btn{border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:6px 10px;cursor:pointer}
@media (max-width:480px){ .jbg-ads-carousel .jbg-ac-item{flex-basis:78vw} }
CSS;
        wp_add_inline_style('jbg-ads-carousel', $css);
        wp_enqueue_style('jbg-ads-carousel');

        wp_register_script('jbg-ads-carousel', '', [], '1.0.1', true);
        $js = <<<JS
(function(){
  function q(sel, root){return (root||document).querySelector(sel)}
  function qa(sel, root){return Array.prototype.slice.call((root||document).querySelectorAll(sel))}
  function slide(track, dir){
    var item = track.querySelector('.jbg-ac-item'); if(!item) return;
    var w = item.getBoundingClientRect().width + 12;
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
    ['mouseenter','touchstart','focusin'].forEach(function(ev){
      wrap.addEventListener(ev, function(){ clearInterval(t); }, {passive:true, once:true});
    });
  });
})();
JS;
        wp_add_inline_script('jbg-ads-carousel', $js);
        wp_enqueue_script('jbg-ads-carousel');
    }

    // سعی برای استفاده از همان رندر کارت شورت‌کد لیست
    private static function render_card_html(int $post_id): string {
        // 1) اگر Renderer اختصاصی دارید
        if (class_exists('\\JBG\\Ads\\Frontend\\Renderer') && method_exists('\\JBG\\Ads\\Frontend\\Renderer', 'card')) {
            return (string) \JBG\Ads\Frontend\Renderer::card($post_id);
        }
        // 2) اگر ListShortcode متد رندر آیتم دارد
        if (class_exists('\\JBG\\Ads\\Frontend\\ListShortcode') && method_exists('\\JBG\\Ads\\Frontend\\ListShortcode', 'render_card')) {
            return (string) \JBG\Ads\Frontend\ListShortcode::render_card($post_id);
        }
        // 3) اگر فیلتر سفارشی دارید
        $via_filter = apply_filters('jbg/ads/render_card_html', '', $post_id);
        if (is_string($via_filter) && $via_filter !== '') return $via_filter;

        // 4) FALLBACK: مارکاپ نزدیک به کارت‌های موجود
        $link  = get_permalink($post_id);
        $title = get_the_title($post_id);
        $thumb = get_the_post_thumbnail_url($post_id, 'medium') ?: '';
        ob_start();
        ?>
        <article class="jbg-ad-card">
            <a class="jbg-ad-card__thumb" href="<?php echo esc_url($link); ?>">
                <?php if ($thumb): ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="">
                <?php endif; ?>
            </a>
            <div class="jbg-ad-card__body">
                <h3 class="jbg-ad-card__title"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($title); ?></a></h3>
            </div>
        </article>
        <?php
        return (string) ob_get_clean();
    }

    public static function render($atts = []): string {
        self::$present = true;

        $a = shortcode_atts([
            'limit'   => 10,
            'autoplay'=> '1',
            'interval'=> '3500',
            'arrows'  => '1',
            'title'   => '',
            'width'   => '', // اختیاری: مثل 280px
        ], $atts, 'jbg_ads_carousel');

        // تلاش برای بارگیری رندرر لیست در صورت نیاز
        $maybe = JBG_ADS_DIR . 'src/Frontend/ListShortcode.php';
        if (file_exists($maybe)) require_once $maybe;
        $maybe2 = JBG_ADS_DIR . 'src/Frontend/Renderer.php';
        if (file_exists($maybe2)) require_once $maybe2;

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

        $autoplay = (int)!in_array((string)$a['autoplay'], ['0','false'], true);
        $arrows   = (int)!in_array((string)$a['arrows'],   ['0','false'], true);
        $interval = max(1200, (int)$a['interval']);
        $styleW   = $a['width'] !== '' ? ' style="--ac-w:'.esc_attr($a['width']).';"' : '';

        ob_start();
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
            $id = get_the_ID();
            echo '<div class="jbg-ac-item" role="listitem"'.$styleW.'>';
            echo self::render_card_html($id);
            echo '</div>';
        }
        echo '</div></section>';

        wp_reset_postdata();
        return (string) ob_get_clean();
    }
}
