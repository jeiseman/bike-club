function adjustSliderHeight() {
  const slider = document.querySelector('.bk-slider');
  const sliderWidth = slider.offsetWidth;
  const aspectRatio = .49; // Adjust this ratio as per your desired aspect ratio

  const sliderHeight = sliderWidth * aspectRatio;
  slider.style.height = `${sliderHeight}px`;
}

function startSlider() {
  const slides = Array.from(document.querySelectorAll('.bk-slide'));
  let currentSlide = 0;

  function showSlide(index) {
    slides.forEach((slide) => slide.classList.remove('active'));
    slides[index].classList.add('active');
  }

  function nextSlide() {
    currentSlide = (currentSlide + 1) % slides.length;
    showSlide(currentSlide);
  }

  setInterval(nextSlide, 8000); // Auto-advance every 8 seconds

  if ( slides.length > 0 ) {
      showSlide(currentSlide);
      adjustSliderHeight();
  }
}

const slider = document.querySelector('.bk-slider');
if ( slider !== null ) {
    window.addEventListener('resize', adjustSliderHeight);
    startSlider();
}
