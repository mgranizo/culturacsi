(function ($) {
	'use strict';

	$(function () {
		var frame = null;
		var $fileId = $('#csi-content-hub-file-id');
		var $fileName = $('#csi-content-hub-file-name');
		var $current = $('#csi-content-hub-current-file');
		var $externalUrl = $('#csi-content-hub-external-url');
		var $buttonLabel = $('#csi-content-hub-button-label');

		function updateChecklist() {
			var hasTitle = $.trim($('#title').val() || '') !== '';
			var hasSection = $('input[name="tax_input[csi_content_section][]"]:checked').length > 0;
			var hasSummary = $.trim($('#excerpt').val() || '') !== '' || $.trim($('#content').val() || '') !== '';
			var hasMedia = ($.trim($('#_thumbnail_id').val() || '') !== '' && $('#_thumbnail_id').val() !== '-1')
				|| $.trim($fileId.val() || '') !== ''
				|| $.trim($externalUrl.val() || '') !== '';

			var steps = {
				title: hasTitle,
				section: hasSection,
				summary: hasSummary,
				media: hasMedia
			};

			$('.csi-hub-guide-item').each(function () {
				var $item = $(this);
				var key = $item.data('step');
				var done = !!steps[key];

				$item.toggleClass('is-complete', done);
				$item.find('.csi-hub-guide-status').text(done ? 'OK' : '...');
			});
		}

		function clearFileSelection() {
			$fileId.val('');
			$fileName.val('');
			$current.empty();
			updateChecklist();
		}

		$('.js-csi-content-hub-select-file').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'Seleziona file da scaricare',
				button: { text: 'Usa questo file' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var label = attachment.filename || attachment.title || ('ID ' + attachment.id);

				$fileId.val(attachment.id || '');
				$fileName.val(label);

				if ($.trim($buttonLabel.val() || '') === '') {
					$buttonLabel.val('Scarica');
				}

				if (attachment.url) {
					$current.html('<a href="' + attachment.url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>');
				} else {
					$current.text(label);
				}

				updateChecklist();
			});

			frame.open();
		});

		$('.js-csi-content-hub-remove-file').on('click', function (event) {
			event.preventDefault();
			clearFileSelection();
		});

		$externalUrl.on('blur', function () {
			if ($.trim($externalUrl.val() || '') !== '' && $.trim($buttonLabel.val() || '') === '' && $.trim($fileId.val() || '') === '') {
				$buttonLabel.val('Visita');
			}

			updateChecklist();
		});

		$(document).on('input change', '#title, #excerpt, #content, #_thumbnail_id, input[name="tax_input[csi_content_section][]"]', updateChecklist);
		updateChecklist();
	});
})(jQuery);
