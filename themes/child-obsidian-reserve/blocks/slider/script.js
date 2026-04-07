document.addEventListener('DOMContentLoaded', () => {
    const sliders = document.querySelectorAll('.obsidian-slider-wrapper');

    sliders.forEach(slider => {
        const section = slider.closest('.obsidian-slider-section');
        const track = slider.querySelector('.slider-track');
        const slides = slider.querySelectorAll('.slider-slide');
        const prevBtn = slider.querySelector('.prev-slide');
        const nextBtn = slider.querySelector('.next-slide');

        if (!track || slides.length === 0) return;

        let currentIndex = 0;
        const totalSlides = slides.length;

        const showcaseDots = section ? section.querySelector('.showcase-dots') : null;

        function updateSlider() {
            track.style.transform = `translateX(-${currentIndex * 100}%)`;

            slider.querySelectorAll('.slide-dots').forEach(dotContainer => {
                const dots = dotContainer.querySelectorAll('.dot');
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === currentIndex);
                });
            });

            if (showcaseDots) {
                showcaseDots.querySelectorAll('.dot').forEach((dot, i) => {
                    dot.classList.toggle('active', i === currentIndex);
                });
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                currentIndex = (currentIndex > 0) ? currentIndex - 1 : totalSlides - 1;
                updateSlider();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                currentIndex = (currentIndex < totalSlides - 1) ? currentIndex + 1 : 0;
                updateSlider();
            });
        }

        let touchStartX = 0;
        let touchEndX = 0;

        slider.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        slider.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            if (touchEndX < touchStartX - 50) {
                currentIndex = (currentIndex < totalSlides - 1) ? currentIndex + 1 : 0;
                updateSlider();
            }
            if (touchEndX > touchStartX + 50) {
                currentIndex = (currentIndex > 0) ? currentIndex - 1 : totalSlides - 1;
                updateSlider();
            }
        }, { passive: true });

        slider.querySelectorAll('.dot').forEach((dot, index) => {
            const dotIndex = index % totalSlides;
            dot.addEventListener('click', () => {
                currentIndex = dotIndex;
                updateSlider();
            });
        });

        if (showcaseDots) {
            showcaseDots.querySelectorAll('.dot').forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    currentIndex = index;
                    updateSlider();
                });
            });
        }

        updateSlider();
    });
});
