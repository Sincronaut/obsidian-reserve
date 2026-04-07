document.addEventListener('DOMContentLoaded', () => {
    const blocks = document.querySelectorAll('.obsidian-three-cards');

    blocks.forEach((root) => {
        const grid = root.querySelector('.cards-grid');
        const cards = root.querySelectorAll('.card-item');
        const dots = root.querySelectorAll('.cards-dot');
        
        if (!grid || !cards.length) return;

        let current = 0;
        const total = cards.length;
        let interval = null;
        const DELAY = 4000;

        const isMobile = () => window.innerWidth <= 991;

        const goTo = (idx) => {
            current = ((idx % total) + total) % total;
            if (!isMobile()) return;
            const cardWidth = cards[0].offsetWidth;
            grid.style.transform = `translateX(-${current * cardWidth}px)`;
            dots.forEach((d, i) => {
                d.classList.toggle('active', i === current);
            });
        };

        const startAuto = () => {
            stopAuto();
            interval = setInterval(() => {
                if (isMobile()) goTo(current + 1);
            }, DELAY);
        };

        const stopAuto = () => clearInterval(interval);

        dots.forEach((dot) => {
            dot.addEventListener('click', function() {
                goTo(parseInt(this.dataset.index, 10));
                startAuto();
            });
        });

        let startX = 0;
        grid.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            stopAuto();
        }, { passive: true });

        grid.addEventListener('touchend', (e) => {
            const diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) {
                goTo(current + (diff > 0 ? 1 : -1));
            }
            startAuto();
        }, { passive: true });

        const onResize = () => {
            if (isMobile()) {
                const cardWidth = cards[0].offsetWidth;
                grid.style.transform = `translateX(-${current * cardWidth}px)`;
            } else {
                grid.style.transform = '';
            }
        };

        window.addEventListener('resize', onResize);
        onResize();
        startAuto();
    });
});
