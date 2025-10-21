(function () {
  // داده‌ی محلی‌سازی شده از PHP
  if (!window.JBG_REACT) return;
  var CFG = window.JBG_REACT;

  // کمک‌ها
  function ready(fn){ if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  function $(sel, ctx){ return (ctx||document).querySelector(sel); }
  function findTitle() {
    var sels = Array.isArray(CFG.selectors) ? CFG.selectors : [];
    for (var i=0;i<sels.length;i++) {
      var el = $(sels[i]);
      if (el) return el;
    }
    return null;
  }

  // ساخت UI داخلی
  function buildUI() {
    var wrap = document.createElement('span');
    wrap.id = 'jbg-react-inline';
    wrap.className = 'jbg-like-inline';
    wrap.setAttribute('dir','ltr');
    wrap.innerHTML =
      '<button type="button" class="jbg-like-btn up" data-act="like" aria-pressed="false" title="پسندیدم">👍</button>' +
      '<button type="button" class="jbg-like-btn down" data-act="dislike" aria-pressed="false" title="نپسندیدم">👎</button>' +
      '<span class="jbg-like-count" aria-label="like count">0</span>';
    return wrap;
  }

  // سینک وضعیت روی UI
  function syncState(ui, state){
    if (!ui) return;
    var up   = ui.querySelector('.jbg-like-btn.up');
    var down = ui.querySelector('.jbg-like-btn.down');
    var cnt  = ui.querySelector('.jbg-like-count');

    if (state && state.liked === true) { up.classList.add('is-on'); down.classList.remove('is-on'); }
    else if (state && state.disliked === true) { down.classList.add('is-on'); up.classList.remove('is-on'); }
    else { up.classList.remove('is-on'); down.classList.remove('is-on'); }

    var c = 0;
    if (state && typeof state.likeCount === 'number') c = state.likeCount;
    if (cnt) cnt.textContent = String(c);
  }

  // خواندن وضعیت اولیه از سرور
  function fetchStatus(ui){
    var url = String(CFG.rest || '').replace(/\/$/,'') + '/status?ad_id=' + encodeURIComponent(CFG.adId||0);
    fetch(url, {
      credentials: 'same-origin',
      headers: CFG.nonce ? {'X-WP-Nonce': CFG.nonce} : {}
    })
    .then(function(r){ return r.ok ? r.json() : {}; })
    .then(function(res){ syncState(ui, res || {}); })
    .catch(function(){});
  }

  // ارسال واکنش
  function sendReaction(ui, action){
    if (!CFG.logged) { alert('برای استفاده از این دکمه‌ها وارد شوید.'); return; }
    var url = String(CFG.rest || '').replace(/\/$/,'') + '/toggle';
    var body = new FormData();
    body.append('ad_id', CFG.adId||0);
    body.append('action', action); // like | dislike | none

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: CFG.nonce ? {'X-WP-Nonce': CFG.nonce} : {},
      body: body
    })
    .then(function(r){ if (r.status===401) throw new Error('login'); return r.json().catch(function(){return {};}); })
    .then(function(res){ syncState(ui, res || {}); })
    .catch(function(err){ if (err && err.message==='login') alert('برای استفاده از این دکمه‌ها وارد شوید.'); });
  }

  ready(function () {
    var host = findTitle();
    if (!host) {
      try {
        new MutationObserver(function(){ var t=findTitle(); if (t && !document.getElementById('jbg-react-inline')) { var ui=buildUI(); t.appendChild(ui); fetchStatus(ui); } })
          .observe(document.body, {childList:true, subtree:true});
      } catch(_){}
      return;
    }

    var ui = buildUI();
    host.appendChild(ui);
    fetchStatus(ui);

    // delegation برای کلیک
    ui.addEventListener('click', function(e){
      var b = e.target && e.target.closest && e.target.closest('.jbg-like-btn');
      if (!b) return;
      var act = b.getAttribute('data-act') || 'like';
      sendReaction(ui, act);
    });
  });
})();
