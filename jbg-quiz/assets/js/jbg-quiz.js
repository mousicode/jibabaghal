(function(){
  if (typeof JBG_QUIZ === 'undefined') return;

  function onReady(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn); else fn(); }

  onReady(function(){
    var box    = document.getElementById('jbg-quiz');
    var form   = document.getElementById('jbg-quiz-form');
    var result = document.getElementById('jbg-quiz-result');
    var adId   = JBG_QUIZ && JBG_QUIZ.adId ? String(JBG_QUIZ.adId) : '';

    function msg(txt, cls){
      if (!result) return;
      result.textContent = txt;
      result.className = 'jbg-quiz-result' + (cls ? ' '+cls : '');
    }

    function disableQuiz(){
      if (form) form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = true; });
      msg('Watch the video first 🔔', 'jbg-quiz-result--warn');
    }
    function enableQuiz(){
      if (form) form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = false; });
      msg('', '');
    }

    function isUnlocked(){
      try{ if (window.JBG_WATCHED_OK===true) return true; }catch(_){}
      try{ if (document.body.getAttribute('data-jbg-watched')==='1') return true; }catch(_){}
      try{ if (adId && localStorage.getItem('jbg_watched_'+adId)==='1') return true; }catch(_){}
      return false;
    }

    if (!isUnlocked()) disableQuiz(); else enableQuiz();

    document.addEventListener('jbg:watched_ok', function(ev){
      var ok = true;
      try{
        if (adId && ev && ev.detail && ev.detail.adId && String(ev.detail.adId)!==adId) ok=false;
      }catch(_){}
      if (ok) enableQuiz();
    }, false);

    if (form){
      form.addEventListener('submit', function(e){
        e.preventDefault();

        if (!isUnlocked()){
          disableQuiz();
          return;
        }

        var answer = form.querySelector('input[name="jbg_answer"]:checked');
        if (!answer){
          msg('یک گزینه را انتخاب کنید.', 'jbg-quiz-result--warn');
          return;
        }

        var payload = { ad_id: adId, answer: parseInt(answer.value,10) || 0 };
        msg('در حال بررسی...', 'jbg-quiz-result--info');

        fetch(JBG_QUIZ.rest, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': JBG_QUIZ.nonce || ''
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json().catch(function(){ return {}; }); })
        .then(function(data){
          if (data && data.correct){
            msg('✔ پاسخ صحیح بود!', 'jbg-quiz-result--ok');

            // 🔔 ایونت باز شدن «ویدئو بعدی» برای SingleLayout
            try {
              document.dispatchEvent(new CustomEvent('jbg:quiz_passed', { detail: { adId: parseInt(adId,10)||0 } }));
            } catch(_) {}

          } else if (data && data.message){
            msg('✖ '+data.message, 'jbg-quiz-result--err');
          } else {
            msg('✖ پاسخ نادرست. دوباره تلاش کنید.', 'jbg-quiz-result--err');
          }
        })
        .catch(function(){
          msg('خطا در ارتباط. دوباره امتحان کنید.', 'jbg-quiz-result--err');
        });
      });
    }
  });
})();
