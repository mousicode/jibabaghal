(function(){
  if (typeof JBG_QUIZ === 'undefined') return;

  function onReady(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }

  onReady(function(){
    var box    = document.getElementById('jbg-quiz');
    var form   = document.getElementById('jbg-quiz-form');
    var result = document.getElementById('jbg-quiz-result');
    var nextBtn= document.getElementById('jbg-next-btn');
    var adId   = JBG_QUIZ && JBG_QUIZ.adId ? String(JBG_QUIZ.adId) : '';

    function gateMsg(txt, cls){
      if (!result) return;
      result.textContent = txt;
      result.className = 'jbg-quiz-result' + (cls ? ' '+cls : '');
    }
    function disableQuiz(){
      if (form) form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = true; });
      gateMsg('Watch the video first 🔔', 'jbg-quiz-result--warn');
    }
    function enableQuiz(){
      if (form) form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = false; });
      gateMsg('', '');
    }
    function isUnlocked(){
      try{ if (window.JBG_WATCHED_OK===true) return true; }catch(_){}
      try{ if (document.body.getAttribute('data-jbg-watched')==='1') return true; }catch(_){}
      try{ if (adId && localStorage.getItem('jbg_watched_'+adId)==='1') return true; }catch(_){}
      return false;
    }
    function showNextIfAny(){
      if (!nextBtn) return;
      var href = (JBG_QUIZ && JBG_QUIZ.nextHref) ? String(JBG_QUIZ.nextHref) : '';
      var ttl  = (JBG_QUIZ && JBG_QUIZ.nextTitle) ? String(JBG_QUIZ.nextTitle) : '';
      if (href){
        nextBtn.href = href;
        nextBtn.textContent = ttl ? ('ویدیو بعدی: ' + ttl) : 'ویدیو بعدی ▶';
        nextBtn.style.display = 'inline-block';
      } else {
        nextBtn.style.display = 'none';
      }
    }

    // حالت اولیه
    if (!isUnlocked()) disableQuiz(); else enableQuiz();

    // وقتی پلیر سیگنال داد، باز کن
    document.addEventListener('jbg:watched_ok', function(ev){
      var ok = true;
      try{
        if (adId && ev && ev.detail && ev.detail.adId && String(ev.detail.adId)!==adId) ok=false;
      }catch(_){}
      if (ok) enableQuiz();
    }, false);

    // Submit با REST؛ بدون ری‌فرش
    if (form){
      form.addEventListener('submit', function(e){
        e.preventDefault();

        if (!isUnlocked()){
          disableQuiz();
          return;
        }

        var answer = form.querySelector('input[name="jbg_answer"]:checked');
        if (!answer){
          gateMsg('یک گزینه را انتخاب کنید.', 'jbg-quiz-result--warn');
          return;
        }

        var payload = { ad_id: adId, answer: parseInt(answer.value,10) || 0 };
        gateMsg('در حال بررسی...', 'jbg-quiz-result--info');

        fetch(JBG_QUIZ.rest, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': JBG_QUIZ.nonce || '' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json().catch(function(){ return {}; }); })
        .then(function(data){
          if (data && data.correct){
            gateMsg('✔ پاسخ صحیح بود!', 'jbg-quiz-result--ok');
            // بیلینگ در سرور انجام می‌شود و Unlock → user_meta را بالا می‌برد
            // دکمه‌ی «ویدیو بعدی» را همین‌جا نشان بده
            showNextIfAny();
          } else if (data && data.message){
            gateMsg('✖ '+data.message, 'jbg-quiz-result--err');
          } else {
            gateMsg('✖ پاسخ نادرست. دوباره تلاش کنید.', 'jbg-quiz-result--err');
          }
        })
        .catch(function(){
          gateMsg('خطا در ارتباط. دوباره امتحان کنید.', 'jbg-quiz-result--err');
        });
      });
    }
  });
})();
