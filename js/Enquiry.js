(function(){
  const form = document.getElementById('contactForm');
  const idMap = {
    'neeq-name': 'name',
    'neeq-phone': 'phone',
    'neeq-email': 'email',
    'neeq-whatsapp': 'whatsapp',
    'neeq-company': 'company'
  };

  /* ============ Popup (no HTML change) ============ */
  const POPUP_ID = 'neeq-form-popup';

  function ensurePopupStyles(){
    if (document.getElementById('neeq-form-popup-css')) return;
    const css = `
      #${POPUP_ID}{
        position:fixed; left:50%; bottom:-90px; transform:translateX(-50%);
        background:#222; color:#fff; padding:12px 16px; border-radius:10px;
        font:500 14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
        box-shadow:0 12px 28px rgba(0,0,0,.28);
        opacity:0; transition:all .35s cubic-bezier(.22,.61,.36,1);
        z-index:999999; pointer-events:none; max-width:90vw; text-align:center;
      }
      #${POPUP_ID}.in{ bottom:20px; opacity:1; }
      #${POPUP_ID}.success{ background:#16a34a; }  /* green */
      #${POPUP_ID}.error{ background:#dc2626; }    /* red */
      #${POPUP_ID}.info{ background:#1f2937; }     /* slate */
      @media (prefers-reduced-motion: reduce){
        #${POPUP_ID}{ transition:opacity .2s ease; }
      }
    `;
    const style = document.createElement('style');
    style.id = 'neeq-form-popup-css';
    style.textContent = css;
    document.head.appendChild(style);
  }

  function getPopup(){
    let el = document.getElementById(POPUP_ID);
    if (!el){
      el = document.createElement('div');
      el.id = POPUP_ID;
      document.body.appendChild(el);
    }
    return el;
  }

  let popupTimer;
  function popup(msg, type='info', ms=3000){
    ensurePopupStyles();
    const el = getPopup();
    el.className = ''; // reset
    el.textContent = msg;
    el.classList.add(type);
    // force reflow for restart animation
    void el.offsetWidth;
    el.classList.add('in');
    clearTimeout(popupTimer);
    popupTimer = setTimeout(()=> el.classList.remove('in'), ms);
  }
  /* ============ /Popup ============ */

  /* ADDED: kill native validation bubble */
  form.setAttribute('novalidate', '');

  // live validation
  Array.from(form.elements).forEach(el=>{
    if (!el.classList.contains('form-control')) return;
    el.addEventListener('input', ()=> validate(el));
    el.addEventListener('blur',  ()=> validate(el));
  });

  function validate(el){
    const sm = el.parentElement.querySelector('.invalid-msg');
    el.classList.remove('is-invalid','is-valid');
    if (sm) sm.textContent = '';
    if (!el.checkValidity()) {
      el.classList.add('is-invalid');
      if (sm) sm.textContent = el.validationMessage || 'Invalid value';
      return false;
    }
    if (el.value) el.classList.add('is-valid');
    return true;
  }

  function clearErrors(){
    Object.keys(idMap).forEach(id=>{
      const el = document.getElementById(id);
      const sm = el?.parentElement.querySelector('.invalid-msg');
      el?.classList.remove('is-invalid','is-valid');
      if (sm) sm.textContent = '';
    });
  }

  function applyServerErrors(errors){
    let first;
    Object.entries(idMap).forEach(([id,key])=>{
      if (!errors[key]) return;
      const el = document.getElementById(id);
      const sm = el?.parentElement.querySelector('.invalid-msg');
      el?.classList.add('is-invalid');
      if (sm) sm.textContent = errors[key];
      if (!first) first = el;
    });
    first?.focus();
    first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // tidy phone-like on blur
  ['neeq-phone','neeq-whatsapp'].forEach(id=>{
    const el = document.getElementById(id);
    el?.addEventListener('blur', ()=> el.value = el.value.replace(/\s+/g,' ').trim());
  });

  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    clearErrors();

    // validate all fields and mark invalids
    let ok = true;
    Array.from(form.elements).forEach(el=>{
      if (!el.classList.contains('form-control')) return;
      ok = validate(el) && ok;
    });

    // if anything invalid, block submit, focus + scroll to first invalid and notify
    if (!ok) {
      const firstError = form.querySelector('.is-invalid');
      if (firstError) {
        firstError.focus({ preventScroll: true });
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      popup('Please fill all the required fields correctly.', 'error');
      return;
    }

    const btn = form.querySelector('button[type="submit"]');
    const txt = btn.textContent;
    btn.disabled = true; btn.textContent = 'Submitting…';

    try {
      const res = await fetch(form.action, { method:'POST', body:new FormData(form) });
      const data = await res.json().catch(()=> ({}));

      if (res.status === 422 && data.errors) {
        applyServerErrors(data.errors);
        popup('Please correct the highlighted fields and submit again.', 'error');
        return;
      }
      if (!res.ok || !data.ok) throw new Error(data.error || 'Submission failed');

      form.reset();
      Array.from(form.querySelectorAll('.is-valid')).forEach(e=>e.classList.remove('is-valid'));
      popup(data.message || 'Thanks! We will reach out shortly.', 'success');
    } catch (err) {
      popup(err.message || 'Something went wrong.', 'error');
    } finally {
      btn.disabled = false; btn.textContent = txt;
    }
  });

  /* ADDED: catch any stray alert() calls from other scripts */
  window.alert = (msg)=> popup(String(msg), 'info');
})();