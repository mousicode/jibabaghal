public static function inject($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $id     = get_the_ID();
        $views  = self::views_count((int)$id);
        $brandN = wp_get_post_terms($id, 'jbg_brand', ['fields'=>'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $viewsF = self::compact_views($views) . ' بازدید';
        $when   = self::relative_time($id);
        $like   = do_shortcode('[posts_like_dislike id=' . $id . ']');

$style = '<style id="jbg-single-header-css">
/* مخفی‌سازی هدرهای پیش‌فرض */
.single-jbg_ad header.wd-single-post-header,
.single-jbg_ad h1.wd-entities-title,
.single-jbg_ad .entry-title,
.single-jbg_ad h1.entry-title,
.single-jbg_ad .post-title,
.single-jbg_ad .elementor-heading-title{display:none!important;}
.single-jbg_ad .jbg-status,.single-jbg_ad .jbg-watched,.single-jbg_ad .watched{display:none!important;}

/* کارت هدر (تنظیمات موبایل: Column) */
.jbg-player-wrapper .jbg-single-header{
  direction:rtl;
  width:100%;
  margin-top:10px;
  padding:14px 16px;
  background:#fff;
  border:1px solid #e5e7eb;
  border-radius:12px;
  box-shadow:0 1px 2px rgba(0,0,0,.04);
  box-sizing:border-box;

  display:flex;
  flex-direction:column;     /* پیش‌فرض: Column برای موبایل */
  align-items:stretch;       /* محتوا کامل کشیده شود */
  gap:10px;
}

/* عنوان */
.jbg-single-header .hdr-title{margin:0}
.jbg-single-header .hdr-title h1{
  margin:0;
  font-size:20px;
  line-height:1.6;
  font-weight:800;
  color:#0f172a;
  word-break:break-word;
  white-space:normal!important;
  overflow:visible!important;
  text-overflow:clip!important;
}

/* ردیف متا: بازدید • زمان */
.jbg-single-header .hdr-meta{
  display:flex;
  align-items:center;
  gap:8px;
  font-size:13px;
  color:#6b7280;
  margin:0;
}
.jbg-single-header .hdr-meta .dot{opacity:.6}

/* اکشن‌ها به‌صورت چیپ در یک ردیف */
.jbg-single-header .hdr-actions{
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
  margin:0;
}
.jbg-single-header .hdr-actions > *{
  display:inline-flex;
  align-items:center;
  gap:6px;
  height:32px;
  padding:0 12px;
  background:#f8fafc;
  color:#111827;
  border:1px solid #e5e7eb;
  border-radius:999px;
  font-size:13px;
  font-weight:600;
  line-height:1;
}
.jbg-single-header .brand{background:#eef2ff; border-color:#e5e7eb}

/* ریسپانسیو (تنظیمات دسکتاپ: Row) */
@media (min-width:768px){
  .jbg-player-wrapper .jbg-single-header{
    /* تبدیل به Row برای دسکتاپ */
    flex-direction:row;
    align-items:center; /* همه‌چیز در وسط عمودی ردیف قرار گیرد */
    justify-content:space-between; /* پخش شدن آیتم‌ها در طول ردیف */
    padding:16px 18px;
    gap:16px; /* فاصله بین آیتم‌های اصلی را افزایش می‌دهیم */
  }
  
  /* تنظیمات عنوان برای دسکتاپ */
  .jbg-single-header .hdr-title{
    flex-grow:1; /* اجازه می‌دهیم عنوان حداکثر فضای خالی را بگیرد */
    order:1;     /* عنوان اولین آیتم است */
  }
  .jbg-single-header .hdr-title h1{font-size:22px}

  /* تنظیمات متا و اکشن‌ها برای دسکتاپ */
  .jbg-single-header .hdr-meta{
    flex-shrink:0; /* از جمع شدن جلوگیری می‌کنیم */
    order:2;     /* متا بعد از عنوان قرار می‌گیرد */
  }
  .jbg-single-header .hdr-actions{
    flex-shrink:0; /* از جمع شدن جلوگیری می‌کنیم */
    order:3;     /* اکشن‌ها در انتها قرار می‌گیرند */
  }

}
</style>';



        $title  = '<div class="hdr-title"><h1 class="title">'.esc_html(get_the_title($id)).'</h1></div>';
        $meta   = '<div class="hdr-meta"><span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span></div>';
        $acts   = '<div class="hdr-actions"><span class="ext-like">'.$like.'</span>'.($brand ? '<span class="brand">'.esc_html($brand).'</span>' : '').'</div>';
        $header = '<div class="jbg-single-header">'.$title.$meta.$acts.'</div>';

        $script = '<script id="jbg-single-header-move">(function(){
          function move(){try{
            var w=document.querySelector(".jbg-player-wrapper");
            var h=document.querySelector(".jbg-single-header");
            if(w&&h&&!w.contains(h)) w.appendChild(h);
          }catch(e){}}
          if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",move);}else{move();}
        })();</script>';

        static $once=false;
        if(!$once){ $content = $style . $content; $once=true; }
        return $header . $script . $content;
    }