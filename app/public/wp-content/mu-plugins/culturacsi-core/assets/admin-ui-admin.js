(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var profileForm = document.getElementById('your-profile')
			|| document.getElementById('editsite-user-profile')
			|| document.querySelector('form#profile-page');

		if (profileForm) {
			profileForm.setAttribute('enctype', 'multipart/form-data');
		}
	});
}());
