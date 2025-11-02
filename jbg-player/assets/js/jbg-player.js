/*!
 * JBG Player Controller
 * - کنترل Plyr/HLS
 * - جلوگیری از جلو زدن
 * - سیگنال unlock وقتی ویدیو کامل شد
 * - پنهان‌سازی ویدیو و نمایش آزمون پس از اتمام
 */
(function(){
  if (typeof JBG_PLAYER === 'undefined') return;

  // ــ ابزار آماده‌سازی DOM ــ
  function onReady(fn){
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn);
    else fn();
  }

  onReady(function(){
    var wrap  = document.querySelector('.jbg-player-wrapper');   // ← رَپِر پلیر
    var video = document.getElementById('jbg-player');           // ← خود ویدیو
    if (!wrap || !video) return;

    // هدر ViewBadge را داخل wrapper بیاور تا هم‌عرض پلیر باشد (سازگاری با تم‌ها)
    (function(){
      var header = document.querySelector('.jbg-single-header, .jbg-single-shell');
      if (header && header.parentElement!==wrap) wrap.appendChild(header);
    })();

    // شناسه آگهی برای فلگ‌های محلی/رویدادها
    function getAdId(){
      if (window.JBG_PLAYER && JBG_PLAYER.adId) return String(JBG_PLAYER.adId);
      var d = wrap.getAttribute('data-ad-id');
      return d ? String(d) : String(document.body.getAttribute('data-ad-id')||'');
    }

    // پیدا کردن/ساخت دکمه‌های آزمون زیر پلیر
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

    // کلیک روی دکمه آزمون → باکس آزمون را نشان بده (fallback دستی)
    document.addEventListener('click', function(e){
      var t=e.target && e.target.closest && e.target.closest('#jbg-quiz-btn,.jbg-quiz-trigger,[data-jbg-quiz-trigger]');
      if (!t) return;
      var q=document.getElementById('jbg-quiz');
      if (q){
        if (q.style.display==='none') q.style.display='block';
        // q.scrollIntoView({behavior:'smooth',block:'start'}); // ← در صورت نیاز
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
    var maxAllowed = 0; // ← بیشترین زمان واقعی دیده‌شده
    var fixing = false;
    function clampForward(){
      if (fixing) return;
      var tol = 0.25; // ← تلورانس پرش جزئی
      if (video.currentTime > maxAllowed + tol){
        fixing = true;
        video.currentTime = maxAllowed;
        fixing = false;
      }
    }
    video.addEventListener('seeking', clampForward);
    video.addEventListener('seeked',  clampForward);

    // نگهبان اسلایدر Plyr (max=1 یا 100)
    function guardSlider(el){
      if (!el || el.__jbg_guarded) return;
      el.__jbg_guarded = true;
      function handle(e){
        var d = video.duration || 0; if (!d) return;
        var maxAttr = parseFloat(el.max || '100'); if (!isFinite(maxAttr) || maxAttr<=0) maxAttr = 100;
        var val = parseFloat(el.value || '0'); if (!isFinite(val)) val = 0;
        var target = (val / maxAttr) * d; // ← زمان هدف
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
      [].slice.call(wrap.querySelectorAll('.plyr__progress input[type="range"],[data-plyr="seek"]'))
        .forEach(guardSlider);
    }
    setTimeout(bindSliders, 300);
    try{ new MutationObserver(bindSliders).observe(wrap, {childList:true,subtree:true}); }catch(_){}

    // وضعیت unlock در ۱۰۰٪
    var unlocked = false, UNLOCK_AT = 0.999, statusEl = document.getElementById('jbg-status');

    // علامت‌گذاری سراسری «ویدیو کامل دیده شد»
    function markUnlocked(){
      try{ window.JBG_WATCHED_OK = true; }catch(_){}
      try{ document.body.setAttribute('data-jbg-watched','1'); }catch(_){}
      try{ localStorage.setItem('jbg_watched_'+getAdId(),'1'); }catch(_){}
      try{ document.dispatchEvent(new CustomEvent('jbg:watched_ok',{detail:{adId:getAdId(),pct:1}})); }catch(_){}
    }

    // ثبت «بازدید روزانه» پس از کامل‌دیدن (هر ۲۴ ساعت یک‌بار، اگر از سرور فعال شود)
    var dailyTracked = false;
    function trackDailyView(){
      try{
        if (!JBG_PLAYER || !JBG_PLAYER.track || !JBG_PLAYER.track.enabled || dailyTracked) return;
        dailyTracked = true;
        fetch(JBG_PLAYER.track.url, {
          method: 'POST',
          headers: {'Content-Type': 'application/json','X-WP-Nonce': JBG_PLAYER.track.nonce},
          body: JSON.stringify({ ad_id: JBG_PLAYER.track.adId })
        }).catch(function(){ /* silent */ });
      }catch(_){}
    }

    // ــ منطق Unlock ــ
    function unlock(){
      if (unlocked) return;
      unlocked = true;

      /* تغییرات درخواستی: پنهان کردن ویدیو + نمایش آزمون */
      try{
        // مخفی کردن خود ویدیو
        try{ video.pause(); }catch(_){}
        video.style.display = 'none';

        // مخفی کردن اکشن‌های زیر پلیر (مثل دکمه Start Quiz)
        var actions = wrap && wrap.querySelector('.jbg-actions');
        if (actions) actions.style.display = 'none';

        // نمایش باکس آزمون
        var quizBox = document.getElementById('jbg-quiz');
        if (quizBox){
          quizBox.style.display = 'block';
          try{ quizBox.scrollIntoView({behavior:'smooth', block:'start'}); }catch(_){}
        }
      }catch(_){}

      showQuiz();        // ← اگر دکمه آزمون داریم، آن را فعال نگه می‌داریم
      markUnlocked();    // ← سیگنال برای سایر بخش‌ها (آزمون/بیلینگ/امتیاز)
      trackDailyView();  // ← ثبت بازدید روزانه
      if (statusEl) statusEl.textContent = '100% watched';
    }

    // تیکِ زمان برای محاسبۀ بیشینه و تشخیص تکمیل‌شدن
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
