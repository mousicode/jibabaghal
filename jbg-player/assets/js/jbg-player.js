(function(){
  if (typeof JBG_PLAYER === 'undefined') return;
  function onReady(fn){ if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fn); else fn(); }

  onReady(function(){
    // گارد بازدید روزانه (اختیاری) – اصلا به UI دست نمی‌زند
    function trackDailyOnce(){
      try{
        if (!JBG_PLAYER || !JBG_PLAYER.track || !JBG_PLAYER.track.enabled) return;
        var id = String(JBG_PLAYER.track.adId||''); if(!id) return;
        var ymd = new Date().toISOString().slice(0,10);
        var key = 'jbg_viewed_'+id+'_'+ymd;
        if (localStorage.getItem(key)) return;
        localStorage.setItem(key,'1');
        fetch(JBG_PLAYER.track.url,{
          method:'POST',
          headers:{'Content-Type':'application/json','X-WP-Nonce':JBG_PLAYER.track.nonce},
          credentials:'same-origin',
          body:JSON.stringify({ad_id:JBG_PLAYER.track.adId})
        }).catch(function(){});
      }catch(_){}
    }

    // وقتی ویدیو تمام شد ارسال کن (UI پلیر تغییر نمی‌کند)
    var v = document.querySelector('video');
    if (!v) return;

    // Helper: safe getter for ad id
    var adId = (JBG_PLAYER && JBG_PLAYER.track && JBG_PLAYER.track.adId) ? String(JBG_PLAYER.track.adId) : null;

    // max watched time (seconds) — persisted per-ad within session/localStorage
    var maxWatched = 0;
    var storageKey = adId ? 'jbg_maxwatched_' + adId : null;
    try{ if (storageKey && localStorage.getItem(storageKey)) maxWatched = parseFloat(localStorage.getItem(storageKey)) || 0; }catch(_){ }

    function persistMax() {
      try{ if (storageKey) localStorage.setItem(storageKey, String(maxWatched)); }catch(_){ }
    }

    // attach ended tracker
    v.addEventListener('ended', trackDailyOnce);

    // Ensure HLS is attached if needed, then init Plyr if available
    function initPlayer() {
      var player = null;
      try{ if (window.Plyr) player = new Plyr(v); }catch(_){ player = null; }

      // clamp helper
      function clampForward() {
        if (v.currentTime > maxWatched + 0.02) {
          // prevent forward progress
          v.currentTime = maxWatched;
        }
      }

      // update maxWatched as user watches forward
      v.addEventListener('timeupdate', function(){
        if (v.currentTime > maxWatched) {
          maxWatched = v.currentTime;
          persistMax();
        }
      });

      // intercept programmatic seeking
      v.addEventListener('seeking', function(){
        // allow tiny headroom for decimals
        if (v.currentTime > maxWatched + 0.02) {
          clampForward();
        }
      });

      // block clicks/drags on Plyr progress bar (if present)
      var progress = document.querySelector('.plyr__progress');
      if (progress) {
        progress.addEventListener('pointerdown', function(e){
          try {
            var rect = progress.getBoundingClientRect();
            var clickX = (e.clientX - rect.left);
            var pct = Math.max(0, Math.min(1, clickX / rect.width));
            var requested = (v.duration || 0) * pct;
            if (requested > maxWatched + 0.5) {
              // prevent seeking forward via click
              e.preventDefault();
              e.stopPropagation();
              if (player && typeof player.pause === 'function') player.pause();
              v.currentTime = maxWatched;
            }
          }catch(_){ }
        }, {passive:false});
      }

      // intercept keyboard forward seeks when player has focus
      document.addEventListener('keydown', function(e){
        // common forward keys: ArrowRight, PageDown, 'L' (some players)
        var step = 5;
        if (e.key === 'ArrowRight') step = 5;
        if (e.key === 'PageDown') step = 10;
        if (e.key === 'l' || e.key === 'L') step = 10;
        if (['ArrowRight','PageDown','l','L'].indexOf(e.key) === -1) return;
        // only when focus is inside player container
        if (!v.closest || !v.closest('.plyr')) return;
        var future = Math.min((v.duration||0), v.currentTime + step);
        if (future > maxWatched + 0.02) {
          e.preventDefault(); e.stopPropagation();
          v.currentTime = maxWatched;
        }
      }, true);

      // also guard clicks on native progress (some browsers)
      var nativeProgress = v;
      nativeProgress.addEventListener('click', function(e){
        setTimeout(function(){ if (v.currentTime > maxWatched + 0.02) v.currentTime = maxWatched; }, 1);
      }, true);
    }

    // If HLS needed
    var isHls = v.getAttribute('data-hls') === 'true' || (v.querySelector && v.querySelector('source') && (v.querySelector('source').getAttribute('type')||'').indexOf('mpegURL')>-1);
    if (isHls && window.Hls) {
      try{
        var hls = new Hls();
        var src = v.querySelector('source') ? v.querySelector('source').getAttribute('src') : v.getAttribute('src');
        if (src) { hls.loadSource(src); hls.attachMedia(v); hls.on(Hls.Events.MANIFEST_PARSED, function(){ initPlayer(); }); }
        else initPlayer();
      }catch(_){ initPlayer(); }
    } else {
      initPlayer();
    }
  });
})();
