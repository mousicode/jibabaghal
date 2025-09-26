(function(){
  function onReady(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }

  function buildInline(id, count, isOn){
    var wrap = document.createElement('span');
    wrap.className = 'jbg-like-inline';
    wrap.setAttribute('data-jbg-like-id', String(id));

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'jbg-like-btn'+(isOn?' is-on':'');
    btn.textContent = '❤';

    var cnt = document.createElement('span');
    cnt.className = 'jbg-like-count';
    cnt.textContent = String(count||0);

    wrap.appendChild(btn); wrap.appendChild(cnt);
    return wrap;
  }

  // تزریق کنار عنوان اگر نبود (چند سِلِکتور مقاوم)
  function ensureInlineOnTitle(){
    if (!JBG_LIKE || !JBG_LIKE.currentId) return;
    var id = JBG_LIKE.currentId;
    var sel = [
      '.jbg-single-header .jbg-title',
      '.jbg-single-header h1',
      '.entry-title',
      'h1[itemprop="headline"]',
      '.jbg-title', 'h1'
    ];
    var has = document.querySelector('.jbg-like-inline[data-jbg-like-id="'+id+'"]');
    if (has) return;

    var host=null;
    for (var i=0;i<sel.length;i++){
      var el = document.querySelector(sel[i]);
      if (el){ host = el; break; }
    }
    if (!host) return;

    var isOn = Array.isArray(JBG_LIKE.liked) && JBG_LIKE.liked.indexOf(id)>=0;
    var ui = buildInline(id, JBG_LIKE.currentCount||0, isOn);
    host.appendChild(ui);
  }

  function toggleLike(btn){
    var wrap = btn.closest('.jbg-like-inline');
    if (!wrap) return;
    var id = parseInt(wrap.getAttribute('data-jbg-like-id')||'0',10);
    if (!id) return;

    fetch(JBG_LIKE.rest, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-WP-Nonce': JBG_LIKE.nonce||''
      },
      credentials: 'same-origin',
      body: JSON.stringify({ ad_id:id })
    })
    .then(function(r){
      if (r.status===401) throw new Error('login');
      return r.json().catch(function(){return {};});
    })
    .then(function(data){
      if (!data) return;
      var cnt = wrap.querySelector('.jbg-like-count');
      if (typeof data.count==='number' && cnt) cnt.textContent = String(data.count);
      if (data.liked===true) btn.classList.add('is-on'); else btn.classList.remove('is-on');
    })
    .catch(function(err){
      if (err && err.message==='login'){
        alert('برای پسندیدن باید وارد شوید.');
      }
    });
  }

  onReady(function(){
    ensureInlineOnTitle();

    // اگر هدر با JS جا‌به‌جا شد، دوباره تلاش کن
    try { new MutationObserver(function(){ ensureInlineOnTitle(); })
      .observe(document.body,{childList:true,subtree:true}); } catch(_){}

    // کلیک
    document.addEventListener('click', function(e){
      var btn = e.target && e.target.closest && e.target.closest('.jbg-like-inline .jbg-like-btn');
      if (!btn) return;
      toggleLike(btn);
    }, false);
  });
})();
