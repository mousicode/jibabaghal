(function () {
  function ready(fn){ if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }

  ready(function () {
    if (typeof JBG_LIKE === 'undefined') return;

    // پیدا کردن عنوان
    var target = null;
    (JBG_LIKE.selectors || []).some(function (sel) {
      var el = document.querySelector(sel);
      if (el) { target = el; return true; }
      return false;
    });
    if (!target) return;

    // ساخت UI
    var wrap = document.createElement('span');
    wrap.className = 'jbg-like-inline';
    wrap.setAttribute('data-jbg-like-id', String(JBG_LIKE.adId));

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'jbg-like-btn';
    btn.setAttribute('aria-label', 'پسندیدن');

    var icon = document.createElement('span');
    icon.className = 'jbg-like-icon';
    icon.textContent = '❤';

    var cnt = document.createElement('span');
    cnt.className = 'jbg-like-count';
    cnt.textContent = String(JBG_LIKE.count || 0);

    btn.appendChild(icon);
    wrap.appendChild(btn);
    wrap.appendChild(cnt);

    // اضافه کنار عنوان
    target.appendChild(wrap);

    function setState(liked, count){
      if (liked) btn.classList.add('is-on'); else btn.classList.remove('is-on');
      cnt.textContent = String(count || 0);
    }
    setState(!!JBG_LIKE.liked, JBG_LIKE.count || 0);

    // کلیک = فراخوانی REST
    btn.addEventListener('click', function(){
      fetch(JBG_LIKE.rest, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': JBG_LIKE.nonce || '' },
        body: JSON.stringify({ ad_id: JBG_LIKE.adId })
      })
      .then(function(r){ return r.json().catch(function(){ return {}; }); })
      .then(function(res){
        if (res && typeof res.count !== 'undefined') {
          setState(!!res.liked, parseInt(res.count, 10) || 0);
        }
      })
      .catch(function(){ /* silence */ });
    });
  });
})();
