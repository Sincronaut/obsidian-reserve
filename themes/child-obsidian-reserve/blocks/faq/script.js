document.addEventListener('DOMContentLoaded', () => {
    const faqItems = document.querySelectorAll('.obsidian-faq-block .faq-item');

    faqItems.forEach(item => {
        const questionBtn = item.querySelector('.faq-question');
        
        questionBtn.addEventListener('click', () => {
            const isActive = item.classList.contains('is-active');

            // Close all other faqs
            faqItems.forEach(otherItem => {
                otherItem.classList.remove('is-active');
                otherItem.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
                otherItem.querySelector('.faq-answer').setAttribute('aria-hidden', 'true');
            });

            // Toggle current
            if (!isActive) {
                item.classList.add('is-active');
                questionBtn.setAttribute('aria-expanded', 'true');
                item.querySelector('.faq-answer').setAttribute('aria-hidden', 'false');
            }
        });
    });
});
