(function(){
  if (typeof JBG_PLAYER === 'undefined') return;

  function onReady(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }

  onReady(function(){
    var wrap  = document.querySelector('.jbg-player-wrapper');
    var video = document.getElementById('jbg-player');
    if (!wrap || !video) return;

    // هدر را داخل wrapper بیاور تا هم‌عرض پلیر باشد
    (function(){
      var header = document.querySelector('.jbg-single-header, .jbg-single-shell');
      if (header && header.parentElement!==wrap) wrap.appendChild(header);
    })();

    // ابزار
    function adId(){
      if (window.JBG_PLAYER && JBG_PLAYER.adId) return String(JBG_PLAYER.adId);
      var d = wrap.getAttribute('data-ad-id');
      return d ? String(d) : String(document.body.getAttribute('data-ad-id')||'');
    }

    // دکمه آزمون
    function quizBtns(){
      var a=[]; var b=document.getElementById('jbg-quiz-btn'); if(b)a.push(b);
      a=a.concat([].slice.call(document.querySelectorAll('.jbg-quiz-trigger,[data-jbg-quiz-trigger]')));
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

    document.addEventListener('click', function(e){
      var t=e.target && e.target.closest && e.target.closest('#jbg-quiz-btn,.jbg-quiz-trigger,[data-jbg-quiz-trigger]');
      if (!t) return;
      var q=document.getElementById('jbg-quiz');
      if (q){
        if (q.style.display==='none') q.style.display='block';
        // حذف اسکرول خودکار به باکس آزمون:
        // q.scrollIntoView({behavior:'smooth',block:'start'});
      }
    });

    // منبع و Plyr
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

    // فقط عقب آزاد؛ جلو ممنوع
    var maxAllowed = 0; // بیشترین زمان واقعاً دیده‌شده
    var fixing = false;

    function clampForward(){
      if (fixing) return;
      var tol = 0.25;
      if (video.currentTime > maxAllowed + tol){
        fixing = true;
        video.currentTime = maxAllowed;
        fixing = false;
      }
    }
    video.addEventListener('seeking', clampForward);
    video.addEventListener('seeked',  clampForward);

    // نگهبان اسلایدر Plyr (max=1 یا 100 هر دو پوشش داده می‌شود)
    function guardSlider(el){
      if (!el || el.__jbg_guarded) return;
      el.__jbg_guarded = true;
      function handle(e){
        var d = video.duration || 0; if (!d) return;
        var maxAttr = parseFloat(el.max || '100'); if (!isFinite(maxAttr) || maxAttr<=0) maxAttr = 100;
        var val = parseFloat(el.value || '0'); if (!isFinite(val)) val = 0;
        var target = (val / maxAttr) * d; // زمان هدف
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

    // Unlock در ۱۰۰٪
    var unlocked = false, UNLOCK_AT = 0.999, statusEl = document.getElementById('jbg-status');

    function markUnlocked(){
      try{ window.JBG_WATCHED_OK = true; }catch(_){}
      try{ document.body.setAttribute('data-jbg-watched','1'); }catch(_){}
      try{ localStorage.setItem('jbg_watched_'+adId(),'1'); }catch(_){}
      try{ document.dispatchEvent(new CustomEvent('jbg:watched_ok',{detail:{adId:adId(),pct:1}})); }catch(_){}
    }

    // --- ثبت «بازدید روزانه» پس از کامل‌دیدن (هر ۲۴ ساعت یک‌بار) ---
    var dailyTracked = false;
    function trackDailyView(){
      // نیازمند لوکالایز سرور: JBG_PLAYER.track = { url, nonce, adId, enabled:1 }
      try{
        if (!JBG_PLAYER || !JBG_PLAYER.track || !JBG_PLAYER.track.enabled || dailyTracked) return;
        dailyTracked = true;
        fetch(JBG_PLAYER.track.url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': JBG_PLAYER.track.nonce
          },
          body: JSON.stringify({ ad_id: JBG_PLAYER.track.adId })
        }).catch(function(){ /* silent */ });
      }catch(_){}
    }
    // ---------------------------------------------------------------

    function unlock(){
      if (unlocked) return;
      unlocked = true;
      showQuiz();
      markUnlocked();
      trackDailyView(); // ← پس از کامل‌دیدن ویدیو
      if (statusEl) statusEl.textContent = '100% watched';
    }

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
