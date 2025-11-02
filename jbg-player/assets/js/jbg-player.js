/*!
 * JBG Player Controller
 * - کنترل Plyr/HLS و جلوگیری از جلو زدن
 * - پس از اتمام: «پنهان‌سازی نرمِ کل باکس پلیر» و «جابجایی/نمایش نرم باکس آزمون»
 */
(function(){
  if (typeof JBG_PLAYER === 'undefined') return;

  // آماده‌سازی DOM
  function onReady(fn){
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn);
    else fn();
  }

  onReady(function(){
    var wrap  = document.querySelector('.jbg-player-wrapper');   // ← کل باکس پلیر (همان چیزی که باید پنهان شود)
    var video = document.getElementById('jbg-player');           // ← تگ <video>
    if (!wrap || !video) return;

    // (سازگاری) هدر ViewBadge را داخل wrap منتقل می‌کنیم تا زیر پلیر رندر شود
    (function(){
      var header = document.querySelector('.jbg-single-header, .jbg-single-shell');
      if (header && header.parentElement!==wrap) wrap.appendChild(header);
    })();

    // شناسه آگهی جهت فلگ‌های لوکال/رویدادها
    function getAdId(){
      if (window.JBG_PLAYER && JBG_PLAYER.adId) return String(JBG_PLAYER.adId);
      var d = wrap.getAttribute('data-ad-id');
      return d ? String(d) : String(document.body.getAttribute('data-ad-id')||'');
    }

    // دکمه‌های آزمون (ممکن است در برخی قالب‌ها وجود نداشته باشد)
    function quizBtns(){
      var a = [];
      var b = document.getElementById('jbg-quiz-btn'); if(b) a.push(b);
      a = a.concat([].slice.call(document.querySelectorAll('.jbg-quiz-trigger,[data-jbg-quiz-trigger]')));
      return a;
    }
    function ensureQuizBtn(){
      if (quizBtns().length) return;
      var box=document.createElement('div'); box.className='jbg-actions';
      var btn=document.createElement('button');
      btn.id='jbg-quiz-btn'; btn.type='button'; btn.className='jbg-btn jbg-quiz-trigger'; btn.textContent='Start Quiz';
      box.appendChild(btn); wrap.appendChild(box);
    }
    function hideQuiz(){ quizBtns().forEach(function(el){ el.removeAttribute('data-jbg-visible'); el.style.display='none'; el.disabled=true; }); }
    function showQuiz(){ quizBtns().forEach(function(el){ el.setAttribute('data-jbg-visible','1'); el.style.display='inline-block'; el.disabled=false; }); }
    ensureQuizBtn(); hideQuiz();

    // کلیک دستی روی دکمه آزمون → نمایش باکس آزمون (fallback)
    document.addEventListener('click', function(e){
      var t=e.target && e.target.closest && e.target.closest('#jbg-quiz-btn,.jbg-quiz-trigger,[data-jbg-quiz-trigger]');
      if (!t) return;
      var q=document.getElementById('jbg-quiz');
      if (q){
        if (q.style.display==='none') q.style.display='block';
        // q.scrollIntoView({behavior:'smooth',block:'start'});
      }
    });

    // بارگذاری منبع و Plyr/HLS
    var src = wrap.getAttribute('data-src') || video.currentSrc || video.src || '';
    try{
      if (window.Hls && window.Hls.isSupported() && /\.m3u8(\?|$)/i.test(src)){
        var hls=new Hls({enableWorker:true,lowLatencyMode:true}); hls.loadSource(src); hls.attachMedia(video);
      } else if (src && !video.src){ video.src=src; }
    }catch(_){}
    try{
      new Plyr(video,{
        controls:['play','progress','current-time','mute','volume','pip','airplay','fullscreen'],
        keyboard:{focused:true,global:false}, tooltips:{controls:true,seek:false}, seekTime:0
      });
    }catch(_){}

    // جلوگیری از جلو زدن (عقب آزاد)
    var maxAllowed = 0, fixing = false;
    function clampForward(){
      if (fixing) return;
      var tol = 0.25;
      if (video.currentTime > maxAllowed + tol){
        fixing = true; video.currentTime = maxAllowed; fixing = false;
      }
    }
    video.addEventListener('seeking', clampForward);
    video.addEventListener('seeked',  clampForward);

    // نگهبانی اسلایدرهای Plyr
    function guardSlider(el){
      if (!el || el.__jbg_guarded) return;
      el.__jbg_guarded = true;
      function handle(e){
        var d = video.duration || 0; if (!d) return;
        var maxAttr = parseFloat(el.max || '100'); if (!isFinite(maxAttr) || maxAttr<=0) maxAttr = 100;
        var val = parseFloat(el.value || '0'); if (!isFinite(val)) val = 0;
        var target = (val / maxAttr) * d;
        if (target > maxAllowed + 0.25){
          e.preventDefault(); e.stopImmediatePropagation && e.stopImmediatePropagation();
          var back = (maxAllowed / d) * maxAttr;
          el.value = String(back);
          try{ video.currentTime = maxAllowed; }catch(_){}
        }
      }
      el.addEventListener('input',  handle, true);
      el.addEventListener('change', handle, true);
      el.addEventListener('pointerup', handle, true);
      el.addEventListener('touchend',  handle, true);
    }
    function bindSliders(){
      [].slice.call(wrap.querySelectorAll('.plyr__progress input[type="range"],[data-plyr="seek"]')).forEach(guardSlider);
    }
    setTimeout(bindSliders, 300);
    try{ new MutationObserver(bindSliders).observe(wrap, {childList:true,subtree:true}); }catch(_){}

    // وضعیت تکمیل
    var unlocked = false, UNLOCK_AT = 0.999, statusEl = document.getElementById('jbg-status');

    // فلگ سراسری «ویدیو کامل دیده شد»
    function markUnlocked(){
      try{ window.JBG_WATCHED_OK = true; }catch(_){}
      try{ document.body.setAttribute('data-jbg-watched','1'); }catch(_){}
      try{ localStorage.setItem('jbg_watched_'+getAdId(),'1'); }catch(_){}
      try{ document.dispatchEvent(new CustomEvent('jbg:watched_ok',{detail:{adId:getAdId(),pct:1}})); }catch(_){}
    }

    // ثبت بازدید روزانه (اختیاری سمت سرور)
    var dailyTracked = false;
    function trackDailyView(){
      try{
        if (!JBG_PLAYER || !JBG_PLAYER.track || !JBG_PLAYER.track.enabled || dailyTracked) return;
        dailyTracked = true;
        fetch(JBG_PLAYER.track.url, {
          method: 'POST',
          headers: {'Content-Type': 'application/json','X-WP-Nonce': JBG_PLAYER.track.nonce},
          body: JSON.stringify({ ad_id: JBG_PLAYER.track.adId })
        }).catch(function(){});
      }catch(_){}
    }

    // ← انیمیشن نرم: جمع‌شدن wrap و ظاهرشدن آزمون
    function collapseAndSwapWithQuiz(){
      var quizBox = document.getElementById('jbg-quiz');

      // اگر آزمون موجود است، آن را دقیقاً جای wrap بیاور
      if (quizBox && wrap.parentNode){
        wrap.parentNode.insertBefore(quizBox, wrap);
      }

      // ۱) نمایش نرم آزمون
      if (quizBox){
        quizBox.style.display = 'block';
        // افزودن کلاس‌های انیمیشن ظاهر شدن
        try{
          injectAnimStyles();
          quizBox.classList.add('jbg-enter');
          // تریگر رندر
          quizBox.getBoundingClientRect();
          quizBox.classList.add('jbg-enter-active');
          setTimeout(function(){
            quizBox.classList.remove('jbg-enter','jbg-enter-active');
          }, 400);
        }catch(_){}
      }

      // ۲) جمع‌شدن نرمِ wrap (قد و شفافیت)
      try{
        injectAnimStyles();
        var h = wrap.offsetHeight;
        wrap.style.height = h + 'px';
        wrap.style.opacity = '1';
        wrap.style.overflow = 'hidden';
        wrap.style.transition = 'height .4s ease, opacity .25s ease, margin .4s ease';
        // تریگر رندر
        wrap.getBoundingClientRect();
        requestAnimationFrame(function(){
          wrap.style.height = '0px';
          wrap.style.opacity = '0';
          wrap.style.marginTop = '0';
          wrap.style.marginBottom = '0';
        });
        wrap.addEventListener('transitionend', function te(){
          wrap.removeEventListener('transitionend', te);
          wrap.style.display = 'none';
          wrap.style.height = '';
          wrap.style.opacity = '';
          wrap.style.overflow = '';
          wrap.style.transition = '';
          wrap.style.marginTop = '';
          wrap.style.marginBottom = '';
        });
      }catch(_){}
    }

    // استایل‌های انیمیشن (یک‌بار تزریق شود)
    function injectAnimStyles(){
      if (document.getElementById('jbg-anim-styles')) return;
      var css = [
        '.jbg-enter{opacity:0;transform:translateY(8px)}',
        '.jbg-enter-active{opacity:1;transform:none;transition:opacity .35s ease,transform .35s ease}'
      ].join('');
      var st=document.createElement('style'); st.id='jbg-anim-styles'; st.type='text/css'; st.appendChild(document.createTextNode(css));
      document.head.appendChild(st);
    }

    // Unlock در ۱۰۰٪
    function unlock(){
      if (unlocked) return;
      unlocked = true;

      // توقف ویدیو برای قطع صدا
      try{ video.pause(); }catch(_){}

      // جابجایی/نمایش آزمون و پنهان‌سازی نرم باکس پلیر
      collapseAndSwapWithQuiz();

      // فعال ماندن دکمه‌های آزمون (در صورت وجود)
      showQuiz();

      // فلگ‌ها و ثبت
      markUnlocked();
      trackDailyView();
      if (statusEl) statusEl.textContent = '100% watched';
    }

    // حلقه پیشرفت
    function tick(){
      var d = video.duration;
      if (isFinite(d) && d>0){
        if (video.currentTime > maxAllowed) maxAllowed = video.currentTime; // عقب آزاد
        if (maxAllowed/d >= UNLOCK_AT || (d - maxAllowed) <= 0.2) unlock();
        if (statusEl && !unlocked) statusEl.textContent = Math.round((maxAllowed/d)*100) + '% watched';
      }
    }
    video.addEventListener('timeupdate', tick);
    video.addEventListener('ended', unlock);
    video.addEventListener('loadedmetadata', function(){ if (video.currentTime > maxAllowed) maxAllowed = video.currentTime; });

    var iv = setInterval(function(){ tick(); if (unlocked) clearInterval(iv); }, 300);
  });
})();
