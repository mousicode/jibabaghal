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
    if (v) {
      v.addEventListener('ended', trackDailyOnce);

      // کنترل seekbar: فقط عقب رفتن مجاز است تا جایی که دیده شده
      var maxWatched = 0;
      v.addEventListener('timeupdate', function() {
        if (v.currentTime > maxWatched) maxWatched = v.currentTime;
      });
      v.addEventListener('seeking', function(e) {
        // اگر کاربر جلوتر از maxWatched برود، برگردان به maxWatched
        if (v.currentTime > maxWatched + 0.5) {
          v.currentTime = maxWatched;
        }
      });
    }
  });
})();
