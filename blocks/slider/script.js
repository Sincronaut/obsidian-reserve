document.addEventListener('DOMContentLoaded', () => {
    const sliders = document.querySelectorAll('.obsidian-slider-wrapper');

    sliders.forEach(slider => {
        const track = slider.querySelector('.slider-track');
        const slides = slider.querySelectorAll('.slider-slide');
        const prevBtn = slider.querySelector('.prev-slide');
        const nextBtn = slider.querySelector('.next-slide');
        
        if (!track || slides.length === 0) return;

        let currentIndex = 0;
        const totalSlides = slides.length;

        function updateSlider() {
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
            
            // Update dots
            slider.querySelectorAll('.slide-dots').forEach(dotContainer => {
                const dots = dotContainer.querySelectorAll('.dot');
                dots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === currentIndex);
                });
            });
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

        // Add Touch Swiping Logic
        let touchStartX = 0;
        let touchEndX = 0;
        
        slider.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        slider.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            if (touchEndX < touchStartX - 50 && nextBtn) {
                // Swiped Left - Next
                currentIndex = (currentIndex < totalSlides - 1) ? currentIndex + 1 : 0;
                updateSlider();
            }
            if (touchEndX > touchStartX + 50 && prevBtn) {
                // Swiped Right - Prev
                currentIndex = (currentIndex > 0) ? currentIndex - 1 : totalSlides - 1;
                updateSlider();
            }
        }, { passive: true });

        // Add dot click events
        slider.querySelectorAll('.dot').forEach((dot, index) => {
            const dotIndex = index % totalSlides;
            dot.addEventListener('click', () => {
                currentIndex = dotIndex;
                updateSlider();
            });
        });

        // Initialize display
        updateSlider();
    });
});
