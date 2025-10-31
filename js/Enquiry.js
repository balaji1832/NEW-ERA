 (function(){
    const form = document.getElementById('contactForm');
    const idMap = {
      'neeq-name': 'name',
      'neeq-phone': 'phone',
      'neeq-email': 'email',
      'neeq-whatsapp': 'whatsapp',
      'neeq-company': 'company'
    };

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
      el.addEventListener('blur', ()=> el.value = el.value.replace(/\\s+/g,' ').trim());
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
        alert('Please fill all the required fields correctly.');
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
          alert('Please correct the highlighted fields and submit again.');
          return;
        }
        if (!res.ok || !data.ok) throw new Error(data.error || 'Submission failed');

        form.reset();
        Array.from(form.querySelectorAll('.is-valid')).forEach(e=>e.classList.remove('is-valid'));
        alert(data.message || 'Thanks! We will reach out shortly.');
      } catch (err) {
        alert(err.message || 'Something went wrong.');
      } finally {
        btn.disabled = false; btn.textContent = txt;
      }
    });
  })();