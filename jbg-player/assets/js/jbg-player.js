(function(){
  if (typeof JBG_PLAYER === 'undefined') return;

  function onReady(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }

  onReady(function(){
    var wrap  = document.querySelector('.jbg-player-wrapper');
    var video = document.getElementById('jbg-player');
    if (!wrap || !video) return;

    // منبع
    var src = wrap.getAttribute('data-src') || video.currentSrc || video.src || '';
    try{
      if (window.Hls && window.Hls.isSupported() && /\.m3u8(\?|$)/i.test(src)){
        var hls=new Hls({enableWorker:true,lowLatencyMode:true}); hls.loadSource(src); hls.attachMedia(video);
      } else if (src && !video.src){ video.src=src; }
    }catch(_){}
    try{ new Plyr(video,{controls:['play','progress','current-time','mute','volume','pip','airplay','fullscreen']}); }catch(_){}

    function adId(){
      if (window.JBG_PLAYER && JBG_PLAYER.track && JBG_PLAYER.track.adId) return String(JBG_PLAYER.track.adId);
      var d = wrap.getAttribute('data-ad-id');
      return d ? String(d) : '';
    }

    // بازدید روزانه فقط یک بار (گارد کلاینت)
    function trackDailyOnce(){
      try{
        if (!JBG_PLAYER || !JBG_PLAYER.track || !JBG_PLAYER.track.enabled) return;
        var id = adId(); if(!id) return;
        var ymd = new Date().toISOString().slice(0,10);
        var key = 'jbg_viewed_'+id+'_'+ymd;
        if (localStorage.getItem(key)) return;
        localStorage.setItem(key, '1');
        fetch(JBG_PLAYER.track.url, {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-WP-Nonce': JBG_PLAYER.track.nonce},
          credentials: 'same-origin',
          body: JSON.stringify({ ad_id: JBG_PLAYER.track.adId })
        }).catch(function(){});
      }catch(_){}
    }

    // وقتی ویدیو تمام شد، یک بار بازدید را بفرست
    video.addEventListener('ended', trackDailyOnce);
  });
})();
