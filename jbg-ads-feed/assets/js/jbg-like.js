(function(){
  if (!window.JBG_LIKE) return;

  var REST  = JBG_LIKE.rest || {};
  var TOGGLE_URL = REST.toggle || (JBG_LIKE.rest_base || '') + '/like/toggle';
  var STATUS_URL = REST.status || (JBG_LIKE.rest_base || '') + '/like/status';
  var AD_ID = parseInt(JBG_LIKE.adId || JBG_LIKE.ad_id || 0, 10) || 0;
  var NONCE = JBG_LIKE.nonce || '';
  var LOGGED = !!JBG_LIKE.logged;

  // عناصر UI
  function $q(sel, ctx){ return (ctx||document).querySelector(sel); }
  function $qa(sel, ctx){ return [].slice.call((ctx||document).querySelectorAll(sel)); }

  // کانتینر پیش‌فرض: کنار عنوانی که خودمان زیر پلیر گذاشته‌ایم
  var wrap = $q('.jbg-like-ui') || (function(){
    var h = $q('.single-jbg_ad .entry-title, .single-jbg_ad h1');
    if (!h) return null;
    var box = document.createElement('div');
    box.className = 'jbg-like-ui';
    // اگر مارک‌آپ دکمه‌ها وجود ندارد بساز:
    if (!h.querySelector('[data-jbg-like]')) {
      box.innerHTML =
        '<button type="button" class="jbg-like-btn" data-jbg-like="up" aria-label="like">👍</button>' +
        '<button type="button" class="jbg-like-btn" data-jbg-like="down" aria-label="dislike">👎</button>' +
        '<span class="jbg-like-count" data-jbg-like-count>0</span>';
      h.appendChild(box);
    }
    return h.querySelector('.jbg-like-ui');
  })();

  if (!wrap || !AD_ID) return;

  var btnLike    = $q('[data-jbg-like="up"]', wrap);
  var btnDislike = $q('[data-jbg-like="down"]', wrap);
  var elCount    = $q('[data-jbg-like-count]', wrap);

  function setState(json){
    if (!json) return;
    if (typeof json.likes === 'number' && elCount) elCount.textContent = String(json.likes);
    if (btnLike)    btnLike.classList.toggle('is-on', !!json.liked);
    if (btnDislike) btnDislike.classList.toggle('is-on', !!json.disliked);
  }

  // دریافت وضعیت اولیه
  (function init(){
    if (!STATUS_URL) return;
    fetch(STATUS_URL + '?ad_id=' + encodeURIComponent(AD_ID), {
      credentials: 'same-origin',
      headers: NONCE ? {'X-WP-Nonce': NONCE} : {}
    }).then(function(r){ return r.ok ? r.json() : null; })
      .then(setState).catch(function(){});
  })();

  function send(action){
    if (!LOGGED){
      alert('برای استفاده از لایک باید وارد سایت شوید.');
      return;
    }
    if (!TOGGLE_URL) return;

    var body = new FormData();
    body.append('ad_id', AD_ID);
    body.append('action', action); // 'like' یا 'dislike' یا 'none'

    fetch(TOGGLE_URL, {
      method: 'POST',
      credentials: 'same-origin',
      headers: NONCE ? {'X-WP-Nonce': NONCE} : {},
      body: body
    })
    .then(function(r){ return r.json(); })
    .then(function(json){
      // پاسخ مورد انتظار: {liked:bool, disliked:bool, likes:int, dislikes:int}
      setState(json);
    })
    .catch(function(){ /* سکوت */ });
  }

  // بایند کلیک با delegation تا با جابه‌جایی DOM از کار نیفتد
  wrap.addEventListener('click', function(e){
    var b = e.target && e.target.closest('[data-jbg-like]');
    if (!b) return;
    var type = b.getAttribute('data-jbg-like'); // 'up' | 'down'
    if (type === 'up')    send('like');
    if (type === 'down')  send('dislike');
  });
})();
