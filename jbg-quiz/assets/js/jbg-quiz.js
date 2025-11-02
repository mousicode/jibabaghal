/*!
 * JBG Quiz Controller
 * - گِیتِ نمایش آزمون بر اساس تماشای کامل ویدیو
 * - ارسال پاسخ و نمایش دکمۀ «ویدئوی بعدی» در صورت پاسخ صحیح
 * - نمایش خودکار آزمون پس از unlock + پنهان‌سازی ویدیو (fallback)
 */
(function(){
  if (typeof JBG_QUIZ === 'undefined') return;

  // ــ ابزار آماده‌سازی DOM ــ
  function onReady(fn){
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn);
    else fn();
  }

  onReady(function(){
    var box     = document.getElementById('jbg-quiz');         // ← باکس آزمون
    var form    = document.getElementById('jbg-quiz-form');    // ← فرم آزمون
    var result  = document.getElementById('jbg-quiz-result');  // ← پیام‌ها
    var nextBtn = document.getElementById('jbg-next-btn');     // ← دکمه ویدیوی بعدی
    var adId    = (JBG_QUIZ && JBG_QUIZ.adId) ? String(JBG_QUIZ.adId) : '';

    // پیام وضعیت در بالای آزمون
    function gateMsg(txt, cls){
      if (!result) return;
      result.textContent = txt;
      result.className = 'jbg-quiz-result' + (cls ? ' ' + cls : '');
    }

    // دی‌اکتیو/اکتیو کردن ورودی‌های فرم
    function disableInputs(){
      if (!form) return;
      form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = true; });
    }
    function enableInputs(){
      if (!form) return;
      form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = false; });
    }

    // قفل/بازبودن آزمون
    function disableQuiz(){
      disableInputs();
      gateMsg('ابتدا ویدیو را کامل تماشا کنید 🔔', 'jbg-quiz-result--warn');
    }
    function enableQuiz(){
      enableInputs();
      gateMsg('', '');
    }

    // بررسی اینکه ویدیو قبلاً کامل دیده شده یا خیر (فلگ‌های سراسری/لوکال)
    function isUnlocked(){
      try{ if (window.JBG_WATCHED_OK === true) return true; }catch(_){}
      try{ if (document.body.getAttribute('data-jbg-watched') === '1') return true; }catch(_){}
      try{ if (adId && localStorage.getItem('jbg_watched_' + adId) === '1') return true; }catch(_){}
      return false;
    }

    // نمایش دکمه «ویدئوی بعدی» (در صورت وجود)
    function showNextIfAny(){
      if (!nextBtn) return;
      var href = (JBG_QUIZ && JBG_QUIZ.nextHref) ? String(JBG_QUIZ.nextHref) : '';
      var ttl  = (JBG_QUIZ && JBG_QUIZ.nextTitle) ? String(JBG_QUIZ.nextTitle) : '';
      if (href){
        nextBtn.href = href;
        nextBtn.textContent = ttl ? ('ویدئوی بعدی: ' + ttl) : 'ویدئوی بعدی ▶';
        nextBtn.style.display = 'inline-block';
      } else {
        nextBtn.style.display = 'none';
      }
    }

    // حالت اولیه: اگر unlock بود، آزمون را نشان بده؛ وگرنه فقط قفل کن
    if (!isUnlocked()){
      disableQuiz();
      if (box) box.style.display = (box.style.display || ''); // ← پنهان نکن، فقط ورودی‌ها قفل باشند
    } else {
      enableQuiz();
      if (box) box.style.display = 'block'; // ← نمایش فوری آزمون
    }

    // وقتی پلیر سیگنال «تماشای کامل» داد، آزمون را باز و قابل‌مشاهده کن
    document.addEventListener('jbg:watched_ok', function(ev){
      var ok = true;
      try{
        if (adId && ev && ev.detail && ev.detail.adId && String(ev.detail.adId) !== adId) ok = false;
      }catch(_){}
      if (!ok) return;

      enableQuiz();

      /* تغییرات درخواستی: نمایش باکس آزمون و پنهان‌سازی ویدیو (fallback اگر player.js این کار را نکرده باشد) */
      try{
        if (box){
          box.style.display = 'block';
          try{ box.scrollIntoView({behavior:'smooth', block:'start'}); }catch(_){}
        }
        var v = document.getElementById('jbg-player');
        if (v){
          try{ v.pause(); }catch(_){}
          v.style.display = 'none';
        }
        var w = document.querySelector('.jbg-player-wrapper');
        if (w){
          var acts = w.querySelector('.jbg-actions');
          if (acts) acts.style.display = 'none';
        }
      }catch(_){}
      /* پایان تغییرات */
    }, false);

    // ارسال پاسخ آزمون
    if (form){
      form.addEventListener('submit', function(e){
        e.preventDefault();

        // ایمنی: اگر هنوز unlock نشده، جلوی ارسال را می‌گیریم
        if (!isUnlocked()){
          disableQuiz();
          return;
        }

        var answerEl = form.querySelector('input[name="jbg_answer"]:checked');
        if (!answerEl){
          gateMsg('یک گزینه را انتخاب کنید.', 'jbg-quiz-result--warn');
          return;
        }

        var answer = parseInt(answerEl.value, 10) || 0;
        var payload = { ad_id: adId, answer: answer };
        gateMsg('در حال بررسی...', 'jbg-quiz-result--info');

        fetch(JBG_QUIZ.rest, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (JBG_QUIZ && JBG_QUIZ.nonce) ? JBG_QUIZ.nonce : ''
          },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json().catch(function(){ return {}; }); })
        .then(function(data){
          if (data && data.correct){
            // پیام امتیاز (در صورت تعریف امتیاز برای این ویدیو)
            var pts = 0;
            try { if (JBG_QUIZ && +JBG_QUIZ.points > 0) pts = parseInt(JBG_QUIZ.points, 10) || 0; } catch(_){}
            if (pts > 0){
              gateMsg('تبریک! ' + pts + ' امتیاز دریافت شد.', 'jbg-quiz-result--ok');
            } else {
              gateMsg('✔ پاسخ صحیح بود!', 'jbg-quiz-result--ok');
            }

            // جلوگیری از ارسال دوباره
            disableInputs();

            // دکمۀ «ویدئوی بعدی»
            showNextIfAny();

            // ایونت سفارشی برای استفاده‌های دیگر (بیلینگ/آنلاک مرحله بعد و…)
            try{
              document.dispatchEvent(new CustomEvent('jbg:quiz_passed', { detail: { adId: adId, points: pts }}));
            }catch(_){}

          } else if (data && data.message){
            gateMsg('✖ ' + data.message, 'jbg-quiz-result--err');
          } else {
            gateMsg('✖ پاسخ نادرست. مجدد تلاش کنید.', 'jbg-quiz-result--err');
          }
        })
        .catch(function(){
          gateMsg('خطا در ارتباط. دوباره امتحان کنید.', 'jbg-quiz-result--err');
        });
      });
    }
  });
})();
