/*!
 * JBG Quiz Controller
 * - قبل از تکمیل ویدیو: آزمون «همیشه» مخفی و قفل است (حتی اگر قبلاً پاس شده باشد)
 * - پس از تکمیل همین‌بار: آزمون «با Fade In» دقیقاً جای باکس پلیر ظاهر می‌شود
 *   و اگر player.js پنهان نکرده باشد، wrap را اینجا «با Fade Out» پنهان می‌کنیم (Fallback)
 * - ارسال پاسخ و نمایش دکمۀ «ویدئوی بعدی» در صورت پاسخ صحیح
 */
(function(){
  if (typeof JBG_QUIZ === 'undefined') return;

  // ابزار اجرای امن روی DOM آماده
  function onReady(fn){
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',fn);
    else fn();
  }

  onReady(function(){
    var box     = document.getElementById('jbg-quiz');         // ← باکس آزمون
    var form    = document.getElementById('jbg-quiz-form');    // ← فرم آزمون
    var result  = document.getElementById('jbg-quiz-result');  // ← خروجی پیام‌ها
    var nextBtn = document.getElementById('jbg-next-btn');     // ← دکمه ویدیوی بعدی
    var adId    = (JBG_QUIZ && JBG_QUIZ.adId) ? String(JBG_QUIZ.adId) : '';

    /* استایل‌های انیمیشن (همگام با player.js) - فقط یک‌بار تزریق می‌شوند */
    function injectAnimStyles(){
      if (document.getElementById('jbg-anim-styles')) return;
      var css = [
        '.jbg-enter{opacity:0;transform:translateY(6px)}',
        '.jbg-enter-active{opacity:1;transform:none;transition:opacity .35s ease,transform .35s ease}',
        '.jbg-fade-out{opacity:1;transition:opacity .35s ease,height .4s ease,margin .4s ease}',
        '.jbg-fade-out.is-leaving{opacity:0}'
      ].join('');
      var st=document.createElement('style'); st.id='jbg-anim-styles'; st.type='text/css'; st.appendChild(document.createTextNode(css));
      document.head.appendChild(st);
    }

    // پیام وضعیتِ بالای آزمون (ساده و ایمن)
    function gateMsg(txt, cls){
      if (!result) return;
      result.textContent = txt;
      result.className = 'jbg-quiz-result' + (cls ? ' ' + cls : '');
    }

    // فعال/غیرفعال کردن ورودی‌های فرم
    function disableInputs(){ if (!form) return; form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = true; }); }
    function enableInputs(){  if (!form) return; form.querySelectorAll('input,button,select,textarea').forEach(function(el){ el.disabled = false; }); }

    // نمایش دکمه «ویدئوی بعدی» (در صورت موجود بودن داده‌های next)
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

    /* حالت اولیه: آزمون «همیشه» تا زمان تکمیل ویدیو پنهان و قفل باشد */
    if (box){
      box.style.display = 'none';
      disableInputs();
      gateMsg('ابتدا ویدیو را کامل تماشا کنید 🔔', 'jbg-quiz-result--warn');
    }

    // بعد از «تماشای کامل» همین‌بار (سیگنال از player.js)
    document.addEventListener('jbg:watched_ok', function(ev){
      enableInputs();
      injectAnimStyles();

      var wrap = document.querySelector('.jbg-player-wrapper');

      // آزمون را دقیقاً جای wrap بیاور
      if (box && wrap && wrap.parentNode){
        wrap.parentNode.insertBefore(box, wrap);
      }

      // نمایش نرم آزمون (Fade In)
      if (box){
        box.style.display = 'block';
        box.classList.add('jbg-enter');
        box.getBoundingClientRect();                  // ← تریگر
        box.classList.add('jbg-enter-active');
        setTimeout(function(){ box.classList.remove('jbg-enter','jbg-enter-active'); }, 400);
        try{ box.scrollIntoView({behavior:'smooth', block:'start'}); }catch(_){}
      }

      // اگر player.js wrap را پنهان نکرده باشد، اینجا با Fade Out پنهانش کنیم (Fallback)
      if (wrap && wrap.style.display !== 'none'){
        var h = wrap.offsetHeight;
        wrap.style.height = h + 'px';
        wrap.style.overflow = 'hidden';
        wrap.classList.add('jbg-fade-out');
        wrap.getBoundingClientRect();
        requestAnimationFrame(function(){
          wrap.classList.add('is-leaving');
          wrap.style.height = '0px';
          wrap.style.marginTop = '0';
          wrap.style.marginBottom = '0';
        });
        wrap.addEventListener('transitionend', function te(){
          wrap.removeEventListener('transitionend', te);
          wrap.style.display = 'none';
          wrap.classList.remove('jbg-fade-out','is-leaving');
          ['height','overflow','marginTop','marginBottom'].forEach(function(k){ wrap.style[k] = ''; });
        });
      }

      // توقف ویدیو (ایمنی)
      var v = document.getElementById('jbg-player');
      if (v){ try{ v.pause(); }catch(_){ } }
    }, false);

    // ارسال پاسخ آزمون
    if (form){
      form.addEventListener('submit', function(e){
        e.preventDefault();

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
            // پیام موفقیت و نمایش دکمه ویدیوی بعدی
            gateMsg('✔ پاسخ صحیح بود!', 'jbg-quiz-result--ok');
            disableInputs();
            showNextIfAny();

            // ایونت سفارشی برای سایر ماژول‌ها (Billing/Unlock مرحله بعد و …)
            try{ document.dispatchEvent(new CustomEvent('jbg:quiz_passed', { detail: { adId: adId }})); }catch(_){}
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
