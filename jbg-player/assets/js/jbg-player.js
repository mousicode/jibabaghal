(function () {
  if (typeof window === 'undefined') return;

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function pct(curr, dur) {
    if (!dur || dur < 1) return 0;
    return Math.max(0, Math.min(1, curr / dur));
  }

  onReady(function () {
    var adId = (window.JBG_PLAYER && JBG_PLAYER.adId) ? parseInt(JBG_PLAYER.adId, 10) : 0;
    var video =
      document.getElementById('jbg-player') ||
      document.querySelector('.jbg-player video, video');
    if (!video) return;

    // HLS attach if needed + Plyr init
    try {
      var isHls =
        video.getAttribute('data-hls') === 'true' ||
        (video.currentSrc && /\.m3u8(\?|$)/i.test(video.currentSrc));

      if (isHls && window.Hls && Hls.isSupported()) {
        var hls = new Hls();
        var srcEl = video.querySelector('source');
        var src = srcEl ? srcEl.getAttribute('src') : (video.getAttribute('src') || '');
        if (src) {
          hls.loadSource(src);
          hls.attachMedia(video);
        }
      }
      if (window.Plyr) {
        new Plyr(video, (window.PLYR_DEFAULTS || {}));
      }
    } catch (_) {}

    var maxT = 0, sent = false;

    video.addEventListener('timeupdate', function () {
      if (isFinite(video.currentTime)) {
        if (video.currentTime > maxT) maxT = video.currentTime;
      }
      var p = pct(video.currentTime, video.duration);

      if (!sent && p >= 0.95) {
        sent = true;

        // فلگ‌های UI/لوکال برای آنلاک‌کردن کوییز
        try {
          document.body.setAttribute('data-jbg-watched', '1');
          if (adId) localStorage.setItem('jbg_watched_' + adId, '1');
          document.dispatchEvent(new CustomEvent('jbg:watched_ok', { detail: { adId: adId, pct: p } }));
        } catch (_) {}

        // ping به REST برای ثبت سمت سرور
        try {
          if (window.JBG_PLAYER && JBG_PLAYER.watch) {
            fetch(JBG_PLAYER.watch, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (JBG_PLAYER.nonce || '')
              },
              credentials: 'same-origin',
              body: JSON.stringify({ ad_id: adId, watch_pct: p })
            }).catch(function () { });
          }
        } catch (_) {}
      }
    });

    // جلوگیری از Seek به جلو (۲ ثانیه تلرانس)
    video.addEventListener('seeking', function () {
      try {
        if (video.currentTime > maxT + 2) {
          video.currentTime = Math.max(0, maxT - 0.25);
        }
      } catch (_) {}
    });
  });
})();
