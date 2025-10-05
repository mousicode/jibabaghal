(function () {
  function ready(fn){ if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }

  function buildInline(id, count, isOn){
    var wrap = document.createElement('span');
    wrap.className = 'jbg-like-inline';
    wrap.setAttribute('data-jbg-like-id', String(id));

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'jbg-like-btn' + (isOn ? ' is-on' : '');
    btn.setAttribute('aria-label', 'پسندیدن');
    btn.textContent = '❤';

    var cnt = document.createElement('span');
    cnt.className = 'jbg-like-count';
    cnt.textContent = String(count || 0);

    wrap.appendChild(btn);
    wrap.appendChild(cnt);
    return wrap;
  }

  function ensureInlineOnTitle(){
    if (typeof JBG_LIKE === 'undefined' || !JBG_LIKE.currentId) return;

    var id = JBG_LIKE.currentId;
    // اگر قبلاً برای همین آگهی تزریق شده، دوباره نساز
    if (document.querySelector('.jbg-like-inline[data-jbg-like-id="'+id+'"]')) return;

    var target = null;
    (JBG_LIKE.selectors || []).some(function (sel) {
      var el = document.querySelector(sel);
      if (el){ target = el; return true; }
      return false;
    });
    if (!target) return;

    var isOn  = Array.isArray(JBG_LIKE.liked) && JBG_LIKE.liked.indexOf(id) >= 0;
    var ui    = buildInline(id, JBG_LIKE.currentCount || 0, isOn);
    target.appendChild(ui);
  }

  function toggleLike(btn){
    var wrap = btn.closest('.jbg-like-inline');
    if (!wrap) return;
    var id = parseInt(wrap.getAttribute('data-jbg-like-id') || '0', 10);
    if (!id) return;

    fetch(JBG_LIKE.rest, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': JBG_LIKE.nonce || '' },
      body: JSON.stringify({ ad_id: id })
    })
    .then(function(r){
      if (r.status === 401) throw new Error('login');
      return r.json().catch(function(){ return {}; });
    })
    .then(function(res){
      var cnt = wrap.querySelector('.jbg-like-count');
      // سازگاری با هر دو پاسخ قدیمی/جدید
      var liked = !!(res && (res.liked === true || res.ok === true && res.liked));
      var count = (res && (typeof res.count === 'number' ? res.count : res.likeCount)) || 0;

      if (liked) btn.classList.add('is-on'); else btn.classList.remove('is-on');
      if (cnt) cnt.textContent = String(count);
    })
    .catch(function(err){
      if (err && err.message === 'login'){
        alert('برای پسندیدن باید وارد شوید.');
      }
    });
  }

  ready(function () {
    ensureInlineOnTitle();

    // اگر قالب بعداً DOM را تغییر داد، دوباره تلاش کن
    try {
      new MutationObserver(function(){ ensureInlineOnTitle(); })
        .observe(document.body, { childList: true, subtree: true });
    } catch(_){}

    // هندل کلیک
    document.addEventListener('click', function(e){
      var btn = e.target && e.target.closest && e.target.closest('.jbg-like-inline .jbg-like-btn');
      if (!btn) return;
      toggleLike(btn);
    }, false);
  });
})();
