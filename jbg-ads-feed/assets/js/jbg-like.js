(function(){
  function ready(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);} else {fn();} }
  function clamp(n){ n = parseInt(n,10); return isNaN(n)?0:Math.max(0,n); }

  function buildUI(state){
    var root = document.createElement('span');
    root.id = 'jbg-react-inline';
    root.className = 'jbg-react-inline';
    root.setAttribute('dir','ltr');
    root.setAttribute('aria-label','React');

    var up = document.createElement('button');
    up.type = 'button';
    up.className = 'jbg-react-btn up';
    up.title = 'پسندیدم';
    up.setAttribute('aria-pressed','false');
    up.innerHTML =
      '<svg viewBox="0 0 24 24" width="16" height="16" class="icon"><path d="M2 21h4V9H2v12zM22 9c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L13 0 6.59 6.41C6.21 6.78 6 7.3 6 7.83V19c0 1.1.9 2 2 2h9c.82 0 1.54-.5 1.84-1.22l3-7c.11-.23.16-.48.16-.74V9z"/></svg>' +
      '<span class="cnt like">0</span>';

    var down = document.createElement('button');
    down.type = 'button';
    down.className = 'jbg-react-btn down';
    down.title = 'نپسندیدم';
    down.setAttribute('aria-pressed','false');
    down.innerHTML =
      '<svg viewBox="0 0 24 24" width="16" height="16" class="icon"><path d="M22 3h-4v12h4V3zM2 15c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L11 24l6.41-6.41c.38-.37.59-.89.59-1.42V5c0-1.1-.9-2-2-2H7c-.82 0-1.54.5-1.84 1.22l-3 7c-.11.23-.16.48-.16.74V15z"/></svg>' +
      '<span class="cnt dislike">0</span>';

    root.appendChild(up);
    root.appendChild(down);

    function sync(){
      var r  = state.reaction || 'none';
      var lc = clamp(state.likeCount);
      var dc = clamp(state.dislikeCount);
      root.querySelector('.cnt.like').textContent    = String(lc);
      root.querySelector('.cnt.dislike').textContent = String(dc);
      up.setAttribute('aria-pressed',   r==='like'    ? 'true':'false');
      down.setAttribute('aria-pressed', r==='dislike' ? 'true':'false');

      if (!state.logged){
        up.disabled = true; down.disabled = true;
        root.title = 'برای رأی دادن وارد شوید';
      }
    }

    function send(reactionVal){
      if (!state.logged) return;
      var body = { ad_id: state.adId, reaction: reactionVal };
      fetch(state.rest, {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-WP-Nonce': state.nonce || '' },
        credentials: 'same-origin',
        body: JSON.stringify(body)
      })
      .then(function(r){ return r.json().catch(function(){ return {}; }); })
      .then(function(res){
        if (!res || !res.ok) return;
        state.reaction     = res.reaction || 'none';
        state.likeCount    = clamp(res.likeCount);
        state.dislikeCount = clamp(res.dislikeCount);
        sync();
      })
      .catch(function(){ /* silent */ });
    }

    up  .addEventListener('click', function(){ send(state.reaction==='like'    ? 'none'    : 'like');    });
    down.addEventListener('click', function(){ send(state.reaction==='dislike' ? 'none'    : 'dislike'); });

    // expose helpers
    root.__sync = sync;
    return root;
  }

  function findTitle(){
    var sels = (window.JBG_REACT && JBG_REACT.selectors) || [];
    for (var i=0;i<sels.length;i++){
      var t = document.querySelector(sels[i]);
      if (t) return t;
    }
    return null;
  }

  ready(function(){
    if (typeof JBG_REACT === 'undefined' || !JBG_REACT.adId) return;

    // Avoid duplicate injection
    if (document.getElementById('jbg-react-inline')) return;

    var target = findTitle();
    if (!target) {
      try {
        new MutationObserver(function(){
          var tt = findTitle();
          if (tt && !document.getElementById('jbg-react-inline')){
            var ui = buildUI(JBG_REACT);
            tt.appendChild(ui);
            ui.__sync();
          }
        }).observe(document.body, {childList:true, subtree:true});
      } catch(_) {}
      return;
    }

    var ui = buildUI(JBG_REACT);
    target.appendChild(ui);
    ui.__sync();
  });
})();
