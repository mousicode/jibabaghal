/*!
 * JBG Player Controller
 * - کنترل Plyr/HLS و جلوگیری از جلو زدن
 * - نمایش همیشگیِ باکس ویدیو در بدو ورود (حتی اگر قبلاً دیده شده باشد)
 * - پس از اتمام: «پنهان‌سازی نرمِ کل باکس پلیر (Fade Out + جمع‌شدن ارتفاع)»
 *   و «جابجایی/نمایش نرم باکس آزمون (Fade In)» دقیقاً در جای پلیر
 * - تمام تغییرات با حفظ منطق و فانکشن‌های موجود انجام شده‌اند.
 */
(function(){
  if (typeof JBG_PLAYER === 'undefined') return;

  // ابزار اجرای امن روی DOM آماده
  function onReady(fn){
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn);
    else fn();
  }

  onReady(function(){
    var wrap  = document.querySelector('.jbg-player-wrapper');   // ← ظرفِ پلیر (کل باکسی که باید پنهان/نمایش شود)
    var video = document.getElementById('jbg-player');           // ← تگ <video>
    if (!wrap || !video) return;

    /* اطمینان از نمایش پلیر در هر بار ورود (پاکسازی اثرات انیمیشن/پنهان‌سازی قبلی) */
    try{
      wrap.style.display   = '';
      wrap.style.height    = '';
      wrap.style.opacity   = '';
      wrap.style.overflow  = '';
      wrap.style.transition= '';
      wrap.style.marginTop = '';
      wrap.style.marginBottom = '';
    }catch(_){}

    // (سازگاری) انتقال هدر ViewBadge به داخل wrap تا دقیقاً زیر پلیر باشد
    (function(){
      var header = document.querySelector('.jbg-single-header, .jbg-single-shell');
      if (header && header.parentElement!==wrap) wrap.appendChild(header);
    })();

    // استخراج شناسه آگهی برای نشانه‌گذاری سراسری/Storage
    function getAdId(){
      if (window.JBG_PLAYER && JBG_PLAYER.adId) return String(JBG_PLAYER.adId);
      var d = wrap.getAttribute('data-ad-id');
      return d ? String(d) : String(document.body.getAttribute('data-ad-id')||'');
    }

    // پیدا کردن/ساخت دکمه‌های آزمون (برای تم‌هایی که دکمه ندارند)
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

    // کلیک دستی روی دکمه آزمون (fallback) → فقط نمایش باکس آزمون (بدون پنهان‌کردن پلیر)
    document.addEventListener('click', function(e){
      var t=e.target && e.target.closest && e.target.closest('#jbg-quiz-btn,.jbg-quiz-trigger,[data-jbg-quiz-trigger]');
      if (!t) return;
      var q=document.getElementById('jbg-quiz');
      if (q && q.style.display==='none') q.style.display='block';
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

    // جلوگیری از جلو زدن: فقط عقب آزاد است
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

    // نگهبانی اسلایدرهای Plyr (جلوگیری از Seek رو به جلو)
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
          el.value = String((maxAllowed / d) * maxAttr);
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

    // وضعیت تکمیل (همین بار)
    var unlocked = false, UNLOCK_AT = 0.999, statusEl = document.getElementById('jbg-status');

    // علامت‌گذاری سراسری وقتی ویدیو کامل شد (برای سایر ماژول‌ها مثل Quiz/Billing)
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

    /* استایل‌های انیمیشن (Fade In / Fade Out) - فقط یک‌بار تزریق می‌شوند */
    function injectAnimStyles(){
      if (document.getElementById('jbg-anim-styles')) return;
      var css = [
        /* ظاهر شدن نرم */
        '.jbg-enter{opacity:0;transform:translateY(6px)}',
        '.jbg-enter-active{opacity:1;transform:none;transition:opacity .35s ease,transform .35s ease}',
        /* محو شدن نرم + جمع‌شدن ارتفاع */
        '.jbg-fade-out{opacity:1;transition:opacity .35s ease,height .4s ease,margin .4s ease}',
        '.jbg-fade-out.is-leaving{opacity:0}'
      ].join('');
      var st=document.createElement('style'); st.id='jbg-anim-styles'; st.type='text/css'; st.appendChild(document.createTextNode(css));
      document.head.appendChild(st);
    }

    /* انیمیشن: پنهان‌سازی نرم باکس پلیر و نمایش نرم باکس آزمون، در همان جایگاه */
    function collapseAndSwapWithQuiz(){
      injectAnimStyles();

      var quizBox = document.getElementById('jbg-quiz');
      if (quizBox && wrap.parentNode){
        // آزمون را دقیقاً جای wrap بیاور (قبل از آن)
        wrap.parentNode.insertBefore(quizBox, wrap);
      }

      // نمایش نرم آزمون (Fade In)
      if (quizBox){
        quizBox.style.display = 'block';
        quizBox.classList.add('jbg-enter');
        quizBox.getBoundingClientRect();              // ← تریگر رندر
        quizBox.classList.add('jbg-enter-active');
        setTimeout(function(){ quizBox.classList.remove('jbg-enter','jbg-enter-active'); }, 400);
      }

      // محو شدن نرم باکس پلیر (Fade Out + انیمیشن ارتفاع)
      var h = wrap.offsetHeight;
      wrap.style.height = h + 'px';
      wrap.style.overflow = 'hidden';
      wrap.classList.add('jbg-fade-out');
      // تریگر
      wrap.getBoundingClientRect();
      requestAnimationFrame(function(){
        wrap.classList.add('is-leaving');
        wrap.style.height = '0px';
        wrap.style.marginTop = '0';
        wrap.style.marginBottom = '0';
      });
      wrap.addEventListener('transitionend', function te(){
        wrap.removeEventListener('transitionend', te);
        wrap.style.display = 'none';
        // پاکسازی برای بارگذاری‌های بعدی
        wrap.classList.remove('jbg-fade-out','is-leaving');
        ['height','overflow','marginTop','marginBottom'].forEach(function(k){ wrap.style[k] = ''; });
      });
    }

    // Unlock در ۱۰۰٪ (صرفاً همین‌بار، نه بر اساس LocalStorage قدیمی)
    function unlock(){
      if (unlocked) return;
      unlocked = true;

      try{ video.pause(); }catch(_){}
      collapseAndSwapWithQuiz(); // ← جابجایی/پنهان‌سازی نرم
      showQuiz();                // ← اگر دکمه آزمون وجود دارد، قابل‌کلیک بماند
      markUnlocked();            // ← ایونت و فلگ‌ها
      trackDailyView();          // ← ثبت بازدید روزانه
      if (statusEl) statusEl.textContent = '100% watched';
    }

    // حلقه پیشرفت برای تشخیص تکمیل‌شدن
    function tick(){
      var d = video.duration;
      if (isFinite(d) && d>0){
        if (video.currentTime > maxAllowed) maxAllowed = video.currentTime;
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
