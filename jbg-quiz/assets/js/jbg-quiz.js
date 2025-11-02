/*!
 * JBG Quiz Controller
 * - قبل از تکمیل ویدیو: آزمون مخفی
 * - بعد از تکمیل: آزمون «جای باکس پلیر» ظاهر می‌شود (با انیمیشن ملایم)
 * - ارسال پاسخ و نمایش دکمۀ «ویدئوی بعدی» پس از پاسخ صحیح
 */
(function(){
  if (typeof JBG_QUIZ === 'undefined') return;

  // آماده‌سازی DOM
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

    // استایل‌های انیمیشن (یک‌بار)
    function injectAnimStyles(){
      if (document.getElementById('jbg-anim-styles')) return;
      var css = [
        '.jbg-enter{opacity:0;transform:translateY(8px)}',
        '.jbg-enter-active{opacity:1;transform:none;transition:opacity .35s ease,transform .35s ease}'
      ].join('');
      var st=document.createElement('style'); st.id='jbg-anim-styles'; st.type='text/css'; st.appendChild(document.createTextNode(css));
      document.head.appendChild(st);
    }

    // پیام وضعیت
    function gateMsg(txt, cls){
      if (!result) return;
      result.textContent = txt;
      result.className = 'jbg-quiz-result' + (cls ? ' ' + cls : '');
    }

    // دی‌اکتیو/اکتیو کردن فرم
    function disableInputs(){
      if (!form) return;
      form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = true; });
    }
    function enableInputs(){
      if (!form) return;
      form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = false; });
    }

    // قفل/باز بودن آزمون
    function disableQuiz(){
      disableInputs();
      gateMsg('ابتدا ویدیو را کامل تماشا کنید 🔔', 'jbg-quiz-result--warn');
    }
    function enableQuiz(){
      enableInputs();
      gateMsg('', '');
    }

    // بررسی وضعیت تکمیل ویدیو
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

    // حالت اولیه: آزمون را قبل از unlock پنهان نگه‌دار
    if (box){
      if (!isUnlocked()){
        box.style.display = 'none';     // ← پنهان تا زمان تکمیل ویدیو
        disableQuiz();
      } else {
        box.style.display = 'block';
        enableQuiz();
      }
    }

    // رویداد «ویدیو کامل شد» از پلیر
    document.addEventListener('jbg:watched_ok', function(ev){
      var ok = true;
      try{
        if (adId && ev && ev.detail && ev.detail.adId && String(ev.detail.adId) !== adId) ok = false;
      }catch(_){}
      if (!ok) return;

      enableQuiz();

      try{
        // آزمون را دقیقاً «جای باکس پلیر» بیاوریم، اگر player.js این کار را نکرده بود
        var wrap = document.querySelector('.jbg-player-wrapper');
        if (box && wrap && wrap.parentNode && wrap.style.display !== 'none'){
          wrap.parentNode.insertBefore(box, wrap);
          // پنهان‌سازی نرم wrap (اگر هنوز پنهان نشده باشد)
          var h = wrap.offsetHeight;
          wrap.style.height = h + 'px';
          wrap.style.opacity = '1';
          wrap.style.overflow = 'hidden';
          wrap.style.transition = 'height .4s ease, opacity .25s ease, margin .4s ease';
          wrap.getBoundingClientRect();
          requestAnimationFrame(function(){
            wrap.style.height = '0px';
            wrap.style.opacity = '0';
            wrap.style.marginTop = '0';
            wrap.style.marginBottom = '0';
          });
          wrap.addEventListener('transitionend', function te(){
            wrap.removeEventListener('transitionend', te);
            wrap.style.display = 'none';
            wrap.style.height = '';
            wrap.style.opacity = '';
            wrap.style.overflow = '';
            wrap.style.transition = '';
            wrap.style.marginTop = '';
            wrap.style.marginBottom = '';
          });
        }

        // نمایش نرم آزمون
        injectAnimStyles();
        box.style.display = 'block';
        box.classList.add('jbg-enter');
        box.getBoundingClientRect();
        box.classList.add('jbg-enter-active');
        setTimeout(function(){ box.classList.remove('jbg-enter','jbg-enter-active'); }, 400);

        try{ box.scrollIntoView({behavior:'smooth', block:'start'}); }catch(_){}

        // توقف ویدیو (ایمنی)
        var v = document.getElementById('jbg-player');
        if (v){ try{ v.pause(); }catch(_){ } }
      }catch(_){}
    }, false);

    // ارسال پاسخ آزمون
    if (form){
      form.addEventListener('submit', function(e){
        e.preventDefault();

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
            // پیام امتیاز (در صورت تعریف)
            var pts = 0;
            try { if (JBG_QUIZ && +JBG_QUIZ.points > 0) pts = parseInt(JBG_QUIZ.points, 10) || 0; } catch(_){}
            if (pts > 0){
              gateMsg('تبریک! ' + pts + ' امتیاز دریافت شد.', 'jbg-quiz-result--ok');
            } else {
              gateMsg('✔ پاسخ صحیح بود!', 'jbg-quiz-result--ok');
            }

            // جلوگیری از ارسال دوباره
            disableInputs();

            // نمایش دکمۀ «ویدئوی بعدی»
            showNextIfAny();

            // ایونت سفارشی برای استفاده‌های دیگر
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
