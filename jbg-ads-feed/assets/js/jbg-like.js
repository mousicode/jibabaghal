(function(){
  function ready(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);} else {fn();} }

  ready(function(){
    var wrap = document.getElementById('jbg-like-inline');
    if (!wrap || typeof JBG_LIKE==='undefined') return;

    var btn   = wrap.querySelector('.jbg-like-btn');
    var countEl = wrap.querySelector('.jbg-like-count');

    var liked = !!JBG_LIKE.liked;
    var count = parseInt(JBG_LIKE.count,10) || 0;

    function sync(){
      wrap.classList.toggle('is-liked', liked);
      countEl.textContent = (count).toLocaleString();
      if (!JBG_LIKE.logged) btn.setAttribute('disabled','disabled');
    }
    sync();

    btn.addEventListener('click', function(){
      if (!JBG_LIKE.logged) return;

      btn.disabled = true;
      fetch(JBG_LIKE.rest.toggle, {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-WP-Nonce': JBG_LIKE.nonce },
        credentials: 'same-origin',
        body: JSON.stringify({ ad_id: JBG_LIKE.adId })
      }).then(function(r){ return r.json().catch(function(){ return {}; }); })
        .then(function(d){
          if (d && d.ok){
            liked = !!d.liked;
            count = parseInt(d.count,10) || 0;
          }
        }).finally(function(){
          btn.disabled = false; sync();
        });
    });
  });
})();
