/**
 * Global Animations — Intersection Observer Logic
 *
 * This script detects elements with the '.reveal' or '.reveal-stagger' classes
 * and adds the '.is-visible' class when they enter the viewport.
 */
(function () {
	'use strict';

	let observer;

	/**
	 * Initialize Intersection Observer for reveal elements.
	 */
	function initObsidianAnimations() {
		const revealElements = document.querySelectorAll('.reveal:not(.is-observed), .reveal-stagger:not(.is-observed)');

		if (!revealElements.length) return;

		if (!observer) {
			const observerOptions = {
				root: null,
				rootMargin: '0px',
				threshold: 0.15
			};

			observer = new IntersectionObserver((entries) => {
				entries.forEach(entry => {
					if (entry.isIntersecting) {
						entry.target.classList.add('is-visible');
						observer.unobserve(entry.target);
					}
				});
			}, observerOptions);
		}

		revealElements.forEach(el => {
			el.classList.add('is-observed');
			observer.observe(el);
		});
	}

	// Run on initial load
	document.addEventListener('DOMContentLoaded', initObsidianAnimations);

	// Expose to window for manual calls if needed
	window.obsidianInitAnimations = initObsidianAnimations;

	/**
	 * Watch for DOM changes to support AJAX content (like blog pagination).
	 */
	const mutationObserver = new MutationObserver((mutations) => {
		let shouldInit = false;
		mutations.forEach(mutation => {
			if (mutation.addedNodes.length > 0) {
				shouldInit = true;
			}
		});

		if (shouldInit) {
			initObsidianAnimations();
		}
	});

	mutationObserver.observe(document.body, {
		childList: true,
		subtree: true
	});

})();
