
document.addEventListener("DOMContentLoaded", function () {
  /* =====================================================
   * 1) DESKTOP HOVER MEGA-MENU (≥ 992px)
   * ===================================================== */
  const DESKTOP_BP  = 992;
  const OPEN_DELAY  = 120;    // ms before opening
  const CLOSE_DELAY = 2000;   // ms before closing

  let isDesktop = window.innerWidth >= DESKTOP_BP;
  let currentOpen = null;
  const closeTimers = new WeakMap();   // dropdown -> timeout
  const openTimers  = new WeakMap();   // dropdown -> timeout
  const DROPDOWN_SEL = ".navbar .nav-item.dropdown";

  function getToggle(dd){ return dd?.querySelector('[data-bs-toggle="dropdown"]'); }
  function getMenu(dd){ return dd?.querySelector(".dropdown-menu"); }
  function getInstance(dd){
    const t = getToggle(dd);
    return t ? bootstrap.Dropdown.getOrCreateInstance(t) : null;
  }
  function clearTimers(dd){
    const t1 = closeTimers.get(dd); if (t1) { clearTimeout(t1); closeTimers.delete(dd); }
    const t2 = openTimers.get(dd);  if (t2) { clearTimeout(t2);  openTimers.delete(dd); }
  }
  function isHovered(dd){
    // stay open if cursor is over li, its toggle, or its menu
    if (dd.matches(":hover")) return true;
    const t = getToggle(dd), m = getMenu(dd);
    return (t && t.matches(":hover")) || (m && m.matches(":hover"));
  }
  function forceClose(dd){
    const inst = getInstance(dd); inst && inst.hide();
    clearTimers(dd);
    if (currentOpen === dd) currentOpen = null;
  }
  function openDropdown(dd){
    if (!isDesktop) return;
    if (currentOpen && currentOpen !== dd) forceClose(currentOpen);
    clearTimers(dd);
    const inst = getInstance(dd); inst && inst.show();
    currentOpen = dd;
  }
  function scheduleOpen(dd){
    clearTimers(dd);
    const id = setTimeout(() => openDropdown(dd), OPEN_DELAY);
    openTimers.set(dd, id);
  }
  function scheduleClose(dd){
    clearTimers(dd);
    const id = setTimeout(() => {
      if (!isHovered(dd) && currentOpen === dd) forceClose(dd);
    }, CLOSE_DELAY);
    closeTimers.set(dd, id);
  }

  function bindDropdown(dd){
    if (dd.__bound) return;
    dd.__bound = true;
    const menu   = getMenu(dd);
    const toggle = getToggle(dd);

    // open on hover (li, toggle, or panel)
    dd.addEventListener("mouseenter", () => scheduleOpen(dd));
    toggle && toggle.addEventListener("mouseenter", () => scheduleOpen(dd));
    menu && menu.addEventListener("mouseenter", () => scheduleOpen(dd));

    // schedule close when mouse leaves li or panel
    dd.addEventListener("mouseleave", () => scheduleClose(dd));
    menu && menu.addEventListener("mouseleave", () => scheduleClose(dd));

    // keep single-open even if user clicks
    dd.addEventListener("shown.bs.dropdown", () => {
      if (currentOpen && currentOpen !== dd) forceClose(currentOpen);
      currentOpen = dd;
    });
    dd.addEventListener("hide.bs.dropdown", () => {
      clearTimers(dd);
      if (currentOpen === dd) currentOpen = null;
    });
  }

  function unbindDropdown(dd){
    if (!dd.__bound) return;
    dd.__bound = false;
    forceClose(dd);
    // quickest way to remove listeners: clone
    const clone = dd.cloneNode(true);
    dd.parentNode.replaceChild(clone, dd);
  }

  function applyHoverMode(){
    const now = window.innerWidth >= DESKTOP_BP;
    if (now === isDesktop) return;
    isDesktop = now;
    document.querySelectorAll(DROPDOWN_SEL).forEach(dd => {
      if (isDesktop) bindDropdown(dd); else unbindDropdown(dd);
    });
  }

  // initial bind (desktop only)
  document.querySelectorAll(DROPDOWN_SEL).forEach(dd => { if (isDesktop) bindDropdown(dd); });
  window.addEventListener("resize", applyHoverMode);

  // ESC closes any open desktop dropdown
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && isDesktop && currentOpen) forceClose(currentOpen);
  });

  /* =====================================================
   * 2) SLIDE-DOWN SEARCH + LIGHTWEIGHT NAV SEARCH
   * ===================================================== */
  const box       = document.getElementById("neSearchBox");
  const input     = document.getElementById("neSearchInput");
  const resultsEl = document.getElementById("neSearchResults");
  const btnMobile = document.getElementById("neSearchToggleMobile");
  const btnDesk   = document.getElementById("neSearchToggle");
  const LOCAL_SEARCH_PAGE = null; // set to "search.html" to route locally

  let autoCloseTimer, currentIndex = -1, currentResults = [];

  function toggleSearch(){ box?.classList.contains("open") ? closeSearch() : openSearch(); }
  function openSearch(){
    box?.classList.add("open");
    setTimeout(() => input && input.focus(), 100);
    startAutoClose();
    showResults(); pinResultsToInput();
  }
  function closeSearch(){
    box?.classList.remove("open");
    stopAutoClose();
    hideResults(); clearResults();
    if (input) input.value = "";
  }
  function startAutoClose(){
    stopAutoClose();
    autoCloseTimer = setTimeout(() => {
      const inside = document.activeElement?.closest?.("#neSearchBox");
      if (!box?.matches(":hover") && !inside) closeSearch();
    }, 2000);
  }
  function stopAutoClose(){ if (autoCloseTimer) { clearTimeout(autoCloseTimer); autoCloseTimer = null; } }

  btnMobile?.addEventListener("click", toggleSearch);
  btnDesk  ?.addEventListener("click", toggleSearch);
  box?.addEventListener("mouseenter", stopAutoClose);
  box?.addEventListener("mouseleave", startAutoClose);

  // Build a tiny index from existing links (skip hashes)
  const rawLinks = Array.from(document.querySelectorAll(".dropdown-menu a[href], .navbar a.nav-link[href]"));
  const index = [];
  const seen  = new Set();
  rawLinks.forEach(a => {
    const href = (a.getAttribute("href") || "").trim();
    if (!href || href === "#" || href.startsWith("javascript:")) return;
    const title = (a.textContent || a.getAttribute("aria-label") || "").replace(/\s+/g, " ").trim();
    if (!title) return;
    const key = href + "||" + title.toLowerCase();
    if (seen.has(key)) return;
    seen.add(key);
    index.push({ title, href, group: groupHeadingFor(a) });
  });
  function groupHeadingFor(linkEl){
    const col = linkEl.closest(".col-lg-2, .col-lg-3, .col-lg-4, .col-md-6, .col-md-4");
    const h   = col ? col.querySelector("h6") : null;
    return h ? h.textContent.trim() : null;
  }

  // ranking
  const norm = s => (s||"").toLowerCase().replace(/\s+/g," ").trim();
  function score(q, item){
    const t = norm(item.title), g = norm(item.group||"");
    let s = 0;
    if (t.startsWith(q)) s += 100;
    if (t.includes(q))   s += 40;
    if (g && g.includes(q)) s += 15;
    s += Math.max(0, 20 - t.length/10);
    return s;
  }
  function searchIndex(query, limit=8){
    const q = norm(query);
    if (!q) return [];
    return index.map(it => ({ it, s: score(q,it) }))
                .filter(x => x.s > 0)
                .sort((a,b)=>b.s-a.s)
                .slice(0,limit)
                .map(x => x.it);
  }

  // results overlay styling (JS-only)
  function initResultsStyles(){
    if (!resultsEl) return;
    Object.assign(resultsEl.style, {
      position: "fixed",
      display: "none",
      background: "#fff",
      border: "1px solid rgba(0,0,0,.08)",
      borderRadius: "10px",
      boxShadow: "0 10px 28px rgba(0,0,0,.16)",
      maxHeight: "340px",
      overflowY: "auto",
      overflowX: "hidden",
      zIndex: "4000",
      textAlign: "left",
      padding: "0"
    });
    const t = document.createElement("style");
    t.textContent = "#neSearchResults::-webkit-scrollbar{display:none}";
    document.head.appendChild(t);
  }
  initResultsStyles();

  function pinResultsToInput(){
    if (!resultsEl || !input) return;
    const rect = input.getBoundingClientRect();
    const gap = 8;
    resultsEl.style.left  = rect.left + "px";
    resultsEl.style.top   = rect.bottom + gap + "px";
    resultsEl.style.width = rect.width + "px";
  }
  function showResults(){
    if (!resultsEl) return;
    pinResultsToInput();
    resultsEl.style.display = "block";
    window.addEventListener("resize", pinResultsToInput);
    document.addEventListener("scroll", pinResultsToInput, true);
  }
  function hideResults(){
    if (!resultsEl) return;
    resultsEl.style.display = "none";
    window.removeEventListener("resize", pinResultsToInput);
    document.removeEventListener("scroll", pinResultsToInput, true);
  }

  function renderResults(items){
    currentResults = items; currentIndex = -1;
    if (!resultsEl) return;
    resultsEl.innerHTML = "";
    if (!items.length) { hideResults(); return; }
    showResults();

    items.forEach((it,i) => {
      const a = document.createElement("a");
      a.href = it.href;
      a.setAttribute("role","option");
      a.setAttribute("data-index", String(i));
      a.className = "list-group-item";
      Object.assign(a.style, {
        border: "0",
        borderBottom: "1px solid rgba(0,0,0,.06)",
        padding: "10px 14px",
        textDecoration: "none",
        color: "#000",
        display: "block",
        transition: "background .2s ease",
        cursor: "pointer"
      });
      a.addEventListener("mouseenter", () => { a.style.background = "#f3f4f6"; });
      a.addEventListener("mouseleave", () => {
        const idx = Number(a.getAttribute("data-index"));
        a.style.background = (idx === currentIndex) ? "rgba(0,0,0,.06)" : "";
      });

      a.innerHTML = `
        <div class="fw-semibold">${escapeHtml(it.title)}</div>
        ${it.group ? `<small class="text-muted">${escapeHtml(it.group)}</small>` : ""}`;
      resultsEl.appendChild(a);
    });
    const last = resultsEl.lastElementChild; if (last) last.style.borderBottom = "0";
  }
  function clearResults(){ currentResults=[]; currentIndex=-1; if (resultsEl) resultsEl.innerHTML=""; }
  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }

  input?.addEventListener("input", () => {
    stopAutoClose();
    const q = input.value.trim();
    if (!q) { clearResults(); hideResults(); return; }
    renderResults(searchIndex(q));
    pinResultsToInput();
  });

  input?.addEventListener("keydown", (e) => {
    if (!currentResults.length) {
      if (e.key === "Enter") { e.preventDefault(); fullSearch(input.value.trim()); }
      if (e.key === "Escape") closeSearch();
      return;
    }
    const items = resultsEl.querySelectorAll(".list-group-item");
    if (e.key === "ArrowDown") {
      e.preventDefault(); currentIndex = (currentIndex + 1) % items.length; highlight(items);
    } else if (e.key === "ArrowUp") {
      e.preventDefault(); currentIndex = (currentIndex - 1 + items.length) % items.length; highlight(items);
    } else if (e.key === "Enter") {
      e.preventDefault(); if (currentIndex >= 0) window.location.href = items[currentIndex].href;
    } else if (e.key === "Escape") {
      closeSearch();
    }
  });

  function highlight(items){
    items.forEach((el,i) => {
      const active = i === currentIndex;
      el.classList.toggle("active", active);
      el.style.background = active ? "rgba(0,0,0,.06)" : "";
    });
    if (currentIndex >= 0 && items[currentIndex]) {
      const el = items[currentIndex], parent = resultsEl;
      const elTop = el.offsetTop, elBottom = elTop + el.offsetHeight;
      const viewTop = parent.scrollTop, viewBottom = viewTop + parent.clientHeight;
      if (elTop < viewTop) parent.scrollTop = elTop;
      else if (elBottom > viewBottom) parent.scrollTop = elBottom - parent.clientHeight;
    }
  }

  resultsEl?.addEventListener("click", () => closeSearch());
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && box?.classList.contains("open")) closeSearch();
  });

  function fullSearch(query){
    if (!query) return;
    if (LOCAL_SEARCH_PAGE) {
      window.location.href = `${LOCAL_SEARCH_PAGE}?q=${encodeURIComponent(query)}`;
    } else {
      const host = window.location.hostname || "neweraengineers.com";
      window.location.href = `https://www.google.com/search?q=site:${host}+${encodeURIComponent(query)}`;
    }
  }
});

