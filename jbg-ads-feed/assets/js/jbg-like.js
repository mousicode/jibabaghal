(function(){
  function ready(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);} else {fn();} }

  function clamp(n){ n = parseInt(n,10); return isNaN(n)?0:Math.max(0,n); }

  ready(function(){
    var root = document.getElementById('jbg-react-inline');
    if (!root || typeof JBG_REACT === 'undefined') return;

    var up   = root.querySelector('.jbg-react-btn.up');
    var down = root.querySelector('.jbg-react-btn.down');
    var likeCntEl    = root.querySelector('.cnt.like');
    var dislikeCntEl = root.querySelector('.cnt.dislike');

    var reaction     = (JBG_REACT.reaction || 'none');
    var likeCount    = clamp(JBG_REACT.likeCount);
    var dislikeCount = clamp(JBG_REACT.dislikeCount);

    function sync(){
      likeCntEl.textContent    = String(likeCount);
      dislikeCntEl.textContent = String(dislikeCount);
      up.setAttribute('aria-pressed', reaction==='like' ? 'true':'false');
      down.setAttribute('aria-pressed', reaction==='dislike' ? 'true':'false');
      if (!JBG_REACT.logged){
        up.disabled = true; down.disabled = true;
        root.title = 'برای رأی دادن وارد شوید';
      }
    }
    sync();

    function send(reactionVal){
      if (!JBG_REACT.logged) return;
      var body = { ad_id: JBG_REACT.adId, reaction: reactionVal };
      fetch(JBG_REACT.rest, {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-WP-Nonce': JBG_REACT.nonce || '' },
        credentials: 'same-origin',
        body: JSON.stringify(body)
      })
      .then(function(r){ return r.json().catch(function(){ return {}; }); })
      .then(function(res){
        if (!res || !res.ok) return;
        reaction     = res.reaction || 'none';
        likeCount    = clamp(res.likeCount);
        dislikeCount = clamp(res.dislikeCount);
        sync();
      })
      .catch(function(){ /* silent */ });
    }

    up  .addEventListener('click', function(){
      var target = (reaction === 'like') ? 'none' : 'like';
      send(target);
    });
    down.addEventListener('click', function(){
      var target = (reaction === 'dislike') ? 'none' : 'dislike';
      send(target);
    });
  });
})();
