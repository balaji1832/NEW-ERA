(function () {
  const form = document.getElementById("contactForm");
  if (!form) return;

  const idMap = {
    "neeq-name": "name",
    "neeq-phone": "phone",
    "neeq-email": "email",
    "neeq-whatsapp": "whatsapp",
    "neeq-company": "company",
    "neeq-source": "source"
  };

  /* ========= Popup (Toast) ========= */
  const POPUP_ID = "neeq-form-popup";

  function ensurePopupStyles() {
    if (document.getElementById("neeq-form-popup-css")) return;
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
      #${POPUP_ID}.success{ background:#16a34a; }
      #${POPUP_ID}.error{ background:#dc2626; }
      #${POPUP_ID}.info{ background:#1f2937; }
    `;
    const style = document.createElement("style");
    style.id = "neeq-form-popup-css";
    style.textContent = css;
    document.head.appendChild(style);
  }

  function getPopup() {
    let el = document.getElementById(POPUP_ID);
    if (!el) {
      el = document.createElement("div");
      el.id = POPUP_ID;
      document.body.appendChild(el);
    }
    return el;
  }

  let popupTimer;
  function popup(msg, type = "info", ms = 3500) {
    ensurePopupStyles();
    const el = getPopup();
    el.className = "";
    el.textContent = msg;
    el.classList.add(type);
    void el.offsetWidth;
    el.classList.add("in");
    clearTimeout(popupTimer);
    popupTimer = setTimeout(() => el.classList.remove("in"), ms);
  }
  /* ========= /Popup ========= */

  form.setAttribute("novalidate", "");

  Array.from(form.elements).forEach((el) => {
    if (!el.matches(".form-control, .form-select")) return;
    el.addEventListener("input", () => validate(el));
    el.addEventListener("blur", () => validate(el));
  });

  function validate(el) {
    const sm = el.parentElement.querySelector(".invalid-msg");
    el.classList.remove("is-invalid", "is-valid");
    if (sm) sm.textContent = "";

    if (!el.checkValidity()) {
      el.classList.add("is-invalid");
      if (sm) sm.textContent = el.validationMessage;
      return false;
    }

    if (el.value) el.classList.add("is-valid");
    return true;
  }

  function clearErrors() {
    Object.keys(idMap).forEach((id) => {
      const el = document.getElementById(id);
      const sm = el?.parentElement.querySelector(".invalid-msg");
      el?.classList.remove("is-invalid", "is-valid");
      if (sm) sm.textContent = "";
    });
  }

  function applyServerErrors(errors) {
    let first;
    Object.entries(idMap).forEach(([id, key]) => {
      if (!errors[key]) return;
      const el = document.getElementById(id);
      const sm = el?.parentElement.querySelector(".invalid-msg");
      el?.classList.add("is-invalid");
      if (sm) sm.textContent = errors[key];
      if (!first) first = el;
    });
    if (first) {
      first.focus({ preventScroll: true });
      first.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  ["neeq-phone", "neeq-whatsapp"].forEach((id) => {
    const el = document.getElementById(id);
    el?.addEventListener("blur", () => {
      el.value = el.value.replace(/\s+/g, " ").trim();
    });
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors();

    let ok = true;
    Array.from(form.elements).forEach((el) => {
      if (!el.matches(".form-control, .form-select")) return;
      ok = validate(el) && ok;
    });

    if (!ok) {
      popup("Please fill all required fields correctly.", "error");
      return;
    }

    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = "Submitting…";

    try {
      const res = await fetch(form.action, {
        method: "POST",
        body: new FormData(form)
      });

      const data = await res.json().catch(() => ({}));

      // 🔴 SERVER-SIDE FIELD ERRORS (422)
      if (res.status === 422 && data.errors) {
        applyServerErrors(data.errors);
        popup(data.message || "Please correct the highlighted fields.", "error");
        return;
      }

      // 🔴 GENERAL ERROR MESSAGE FROM BACKEND
      if (!res.ok || data.status === "error") {
        popup(data.message || "Something went wrong.", "error");
        return;
      }

      // 🟢 SUCCESS — show backend message
      popup(data.message || "Thank you! We will contact you soon.", "success");

      form.reset();
      form.querySelectorAll(".is-valid").forEach((el) => el.classList.remove("is-valid"));

    } catch (err) {
      popup(err.message || "Network error, please try again.", "error");
    } finally {
      btn.disabled = false;
      btn.textContent = originalText;
    }
  });

  // Replace default alert()
  window.alert = (msg) => popup(String(msg), "info");
})();
