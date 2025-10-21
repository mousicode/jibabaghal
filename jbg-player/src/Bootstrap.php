<?php
namespace JBG\Player;

use JBG\Player\Admin\MetaBox;
use JBG\Player\Frontend\Renderer;
use JBG\Player\Rest\WatchController;

class Bootstrap {
    public static function init(): void {
        add_action('add_meta_boxes', [MetaBox::class, 'register']);
        add_action('save_post_jbg_ad', [MetaBox::class, 'save'], 10, 2);
        add_action('wp', [Renderer::class, 'bootstrap']);
        add_action('rest_api_init', [WatchController::class, 'register_routes']);

        // ریست ظاهر پلیر در برابر قالب‌ها (بعد از plyr.css)
        add_action('wp_enqueue_scripts', [self::class, 'force_skin'], 99);
    }

    public static function force_skin(): void {
        if (!is_singular('jbg_ad')) return;

        $css = <<<CSS
/* ---- Force default Plyr look on single jbg_ad ---- */
.single-jbg_ad .jbg-player-wrapper .plyr--video .plyr__controls{
  background:transparent !important;
  box-shadow:none !important;
  padding:8px 12px !important;
  gap:8px !important;
  flex-wrap:nowrap !important;
}
.single-jbg_ad .jbg-player-wrapper .plyr--video .plyr__controls::before{display:none !important}
.single-jbg_ad .jbg-player-wrapper .plyr__controls .plyr__control{
  background:none !important;
  border:0 !important;
  box-shadow:none !important;
  border-radius:0 !important;
  padding:0 !important;
  width:auto !important; height:auto !important;
}
.single-jbg_ad .jbg-player-wrapper button,
.single-jbg_ad .jbg-player-wrapper a{
  background:none !important;
  border:0 !important;
  box-shadow:none !important;
}
/* progress takes the middle space */
.single-jbg_ad .jbg-player-wrapper .plyr__progress{
  flex:1 1 auto !important;
  min-width:0 !important;
  height:auto;
  padding:0 6px !important;
}
/* keep volume width sensible */
.single-jbg_ad .jbg-player-wrapper .plyr__volume{max-width:120px}
/* ensure SVG icons are visible */
.single-jbg_ad .jbg-player-wrapper .plyr__control svg{
  display:block !important;
  opacity:1 !important;
}
/* MediaElement fallback reset */
.single-jbg_ad .jbg-player-wrapper .mejs-controls{
  background:transparent !important; box-shadow:none !important;
  height:40px !important; padding:6px 8px !important;
  display:flex; align-items:center; gap:8px;
}
.single-jbg_ad .jbg-player-wrapper .mejs-controls .mejs-button>button{
  background:transparent !important; border:0 !important; box-shadow:none !important; padding:0 !important;
}
.single-jbg_ad .jbg-player-wrapper .mejs-time-rail{flex:1 1 auto !important; min-width:0 !important;}
.single-jbg_ad .jbg-player-wrapper .mejs-time-rail .mejs-time-total{height:3px; margin:8px 6px}
CSS;

        // سعی کن بعد از plyr.css تزریق شود
        if (wp_style_is('plyr', 'enqueued')) {
            wp_add_inline_style('plyr', $css);
        } else {
            // اگر هندل plyr متفاوت است یا لود نشده، یک استایل مستقل بارگذاری کن
            wp_register_style('jbg-player-reset-inline', false);
            wp_enqueue_style('jbg-player-reset-inline');
            wp_add_inline_style('jbg-player-reset-inline', $css);
        }
    }

    public static function activate(): void {}
    public static function deactivate(): void {}
}
