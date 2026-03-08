(function () {
	'use strict';

	// Public News search autosubmit.
	//
	// The script is loaded globally for simplicity, but it activates only when the
	// shortcode marks a form with data-csi-news-autosubmit="1".
	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('form[data-csi-news-autosubmit="1"]');
		if (!forms.length) {
			return;
		}

		forms.forEach(function (form) {
			var inputs = form.querySelectorAll('input, select');
			var debounceTimer = null;

			inputs.forEach(function (input) {
				if (input.type === 'text' || input.type === 'month') {
					input.addEventListener('input', function () {
						window.clearTimeout(debounceTimer);
						debounceTimer = window.setTimeout(function () {
							form.submit();
						}, 600);
					});
					return;
				}

				input.addEventListener('change', function () {
					form.submit();
				});
			});
		});
	});
})();
