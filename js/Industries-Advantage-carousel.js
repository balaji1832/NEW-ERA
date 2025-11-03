document.addEventListener("DOMContentLoaded",function(){
  new Swiper(".newera-chem-swiper",{
    speed:600,
    spaceBetween:24,
    slidesPerView:2,
    slidesPerGroup:1,
    grabCursor:true,
    navigation:{
      nextEl:".newera-chem-next",
      prevEl:".newera-chem-prev"
    },
    breakpoints:{
      0:{slidesPerView:1, spaceBetween:16},
      768:{slidesPerView:2, spaceBetween:24}
    }
  });
});