document.addEventListener('DOMContentLoaded', function() {
    const blockSelector = '.obsidian-blog-grid-block';

    document.body.addEventListener('click', function(e) {
        // Check if the clicked element is a pagination link, a filter button, or a sort button
        const link = e.target.closest('.blog-grid-pagination a.page-numbers, .blog-filter-btn, .blog-sort-btn');
        
        if (link) {
            e.preventDefault();
            const url = link.href;
            const currentBlock = link.closest(blockSelector);
            
            if (!currentBlock) return;

            // Apply a loading state (fade out slightly)
            currentBlock.style.opacity = '0.5';
            currentBlock.style.pointerEvents = 'none';
            currentBlock.style.transition = 'opacity 0.3s ease';

            // Fetch the new page
            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text();
                })
                .then(html => {
                    // Parse the HTML string into a DOM element
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newBlock = doc.querySelector(blockSelector);

                    if (newBlock) {
                        // Replace the inner HTML of the current block with the new one
                        currentBlock.innerHTML = newBlock.innerHTML;
                        
                        // Update the browser URL and history
                        window.history.pushState({ path: url }, '', url);
                    }
                })
                .catch(error => {
                    console.error('Error fetching pagination data:', error);
                    // Fallback to normal navigation if fetch fails
                    window.location.href = url;
                })
                .finally(() => {
                    // Remove loading state
                    currentBlock.style.opacity = '1';
                    currentBlock.style.pointerEvents = 'auto';
                    
                    // Smoothly scroll back to the top of the block
                    const offset = currentBlock.getBoundingClientRect().top + window.scrollY - 100;
                    window.scrollTo({
                        top: offset,
                        behavior: 'smooth'
                    });
                });
        }
    });

    // Handle browser back/forward buttons to ensure correct content is shown
    window.addEventListener('popstate', function() {
        window.location.reload();
    });
});
