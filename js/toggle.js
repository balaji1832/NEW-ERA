
  const fab = document.getElementById("fab");
  const toggle = fab.querySelector(".fab-toggle");

  let hoverTimeout;

  function shakeToggle() {
    toggle.classList.add("shake");
    setTimeout(() => toggle.classList.remove("shake"), 500);
  }

  function autoAction() {
    // Shake first
    shakeToggle();

    // Open after shake
    setTimeout(() => {
      // Only open if not hovered
      if (!fab.matches(':hover')) {
        fab.classList.add("active");
      }
    }, 600);

    // Close after 3 sec if not hovered
    setTimeout(() => {
      if (!fab.matches(':hover')) {
        fab.classList.remove("active");
      }
    }, 3000);
  }

  /* ===== USER INTERACTION ===== */

  // Click toggles manually
  toggle.addEventListener("click", () => {
    fab.classList.toggle("active");
  });

  // Hover opens
  fab.addEventListener("mouseenter", () => {
    fab.classList.add("active");

    // Clear any auto-close timeout
    clearTimeout(hoverTimeout);
  });

  // Hover leave closes after small delay
  fab.addEventListener("mouseleave", () => {
    hoverTimeout = setTimeout(() => {
      fab.classList.remove("active");
    }, 500); // 0.5 sec delay to prevent flicker
  });

  /* ===== AUTO LOOP ===== */

  // Start after 10 sec
  setTimeout(autoAction, 10000);

  // Repeat every 10 sec
  setInterval(autoAction, 10000);
