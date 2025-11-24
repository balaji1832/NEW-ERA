// <!-- Serving Diverse Industrial Needs -->

document.addEventListener("DOMContentLoaded", function () {
  const track = document.querySelector(".carousel-track");
  const container = document.querySelector(".carousel-container");
  const nextBtn = document.querySelector(".carousel-next-btn");
  const prevBtn = document.querySelector(".carousel-prev-btn");

  if (!track || !container || !nextBtn || !prevBtn) return;

  let scrollAmount = 0;

  // Step = card width + gap
  function getStepWidth() {
    const card = track.querySelector(".card");
    if (!card) return 0;

    const trackStyle = getComputedStyle(track);
    const gap =
      parseFloat(trackStyle.gap || trackStyle.columnGap || "0") || 0;

    return card.offsetWidth + gap;
  }

  function updatePosition() {
    const step = getStepWidth();
    if (!step) return;

    const maxScroll = track.scrollWidth - container.clientWidth;

    // Clamp scrollAmount within 0..maxScroll
    if (scrollAmount < 0) scrollAmount = 0;
    if (scrollAmount > maxScroll) scrollAmount = maxScroll;

    track.style.transform = `translateX(-${scrollAmount}px)`;
  }

  nextBtn.addEventListener("click", () => {
    scrollAmount += getStepWidth();
    updatePosition();
  });

  prevBtn.addEventListener("click", () => {
    scrollAmount -= getStepWidth();
    updatePosition();
  });

  // Recalculate on resize so 1 / 3 / 5 card layouts stay aligned
  window.addEventListener("resize", () => {
    // Optional: reset to start on resize
    scrollAmount = 0;
    updatePosition();
  });

  // Initial position
  updatePosition();
});

