(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('form.csi-content-hub-search').forEach(function (form) {
			var inputs = form.querySelectorAll('input, select');
			var debounce;
			inputs.forEach(function (input) {
				if (input.type === 'search' || input.type === 'text' || input.type === 'month') {
					input.addEventListener('input', function () {
						window.clearTimeout(debounce);
						debounce = window.setTimeout(function () {
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
}());
