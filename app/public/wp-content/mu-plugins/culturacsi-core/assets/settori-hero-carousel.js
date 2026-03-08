(function () {
	'use strict';

	function initCarousel(root) {
		if (!root || root.dataset.csiHeroReady === '1') {
			return;
		}

		var track = root.querySelector('.csc-hero-track');
		var slides = root.querySelectorAll('.csc-hero-slide');
		var dots = root.querySelectorAll('.csc-hero-dot');
		var prev = root.querySelector('.csc-hero-prev');
		var next = root.querySelector('.csc-hero-next');
		var total = slides.length;
		var current = 0;
		var timer = null;
		var autoplay = root.getAttribute('data-autoplay') === 'true';
		var interval = parseInt(root.getAttribute('data-interval') || '5000', 10);

		if (!track || total < 1) {
			return;
		}

		root.dataset.csiHeroReady = '1';

		function goTo(index) {
			current = ((index % total) + total) % total;
			track.style.transform = 'translateX(-' + (current * 100) + '%)';
			dots.forEach(function (dot, dotIndex) {
				var isActive = dotIndex === current;
				dot.classList.toggle('is-active', isActive);
				dot.style.background = isActive ? '#fff' : 'transparent';
			});
		}

		function startAutoplay() {
			if (!autoplay || total < 2) {
				return;
			}

			timer = window.setInterval(function () {
				goTo(current + 1);
			}, Math.max(1000, interval || 5000));
		}

		function stopAutoplay() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		if (prev) {
			prev.addEventListener('click', function () {
				stopAutoplay();
				goTo(current - 1);
				startAutoplay();
			});
		}

		if (next) {
			next.addEventListener('click', function () {
				stopAutoplay();
				goTo(current + 1);
				startAutoplay();
			});
		}

		dots.forEach(function (dot) {
			dot.addEventListener('click', function () {
				stopAutoplay();
				goTo(parseInt(dot.dataset.index || '0', 10));
				startAutoplay();
			});
		});

		root.addEventListener('mouseenter', stopAutoplay);
		root.addEventListener('mouseleave', startAutoplay);

		var touchStartX = 0;
		root.addEventListener('touchstart', function (event) {
			touchStartX = event.touches[0].clientX;
		}, { passive: true });

		root.addEventListener('touchend', function (event) {
			var deltaX = event.changedTouches[0].clientX - touchStartX;
			if (Math.abs(deltaX) > 40) {
				stopAutoplay();
				goTo(current + (deltaX < 0 ? 1 : -1));
				startAutoplay();
			}
		}, { passive: true });

		document.addEventListener('abf:hero:key', function (event) {
			var key = event && event.detail && typeof event.detail.key === 'string' ? event.detail.key : '';
			if (!key) {
				return;
			}

			for (var i = 0; i < slides.length; i++) {
				if (slides[i].dataset.heroKey === key) {
					stopAutoplay();
					goTo(i);
					startAutoplay();
					break;
				}
			}
		});

		startAutoplay();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.csc-hero-carousel').forEach(initCarousel);
	});
}());
