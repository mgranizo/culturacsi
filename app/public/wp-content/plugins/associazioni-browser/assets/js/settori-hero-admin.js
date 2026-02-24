(function ($) {
  'use strict';

  function setOverridePreview($row, attachment) {
    var $idInput = $row.find('.abf-hero-override-id');
    var $preview = $row.find('.abf-hero-override-preview');
    var $empty = $row.find('.abf-hero-override-preview-empty');
    var $idLabel = $row.find('.abf-hero-override-id-label');
    var $open = $row.find('.abf-hero-override-open');

    if (!attachment || !attachment.id) {
      $idInput.val('');
      $preview.attr('src', '').hide();
      $empty.show();
      $idLabel.text('Override non impostato');
      $open.attr('href', '#').hide();
      return;
    }

    var thumbUrl = '';
    if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
      thumbUrl = attachment.sizes.thumbnail.url;
    } else if (attachment.url) {
      thumbUrl = attachment.url;
    }

    $idInput.val(String(attachment.id));
    if (thumbUrl) {
      $preview.attr('src', thumbUrl).show();
      $empty.hide();
    } else {
      $preview.attr('src', '').hide();
      $empty.show();
    }

    $idLabel.text('Attachment ID: ' + String(attachment.id));
    if (attachment.url) {
      $open.attr('href', attachment.url).show();
    } else {
      $open.attr('href', '#').hide();
    }
  }

  function openMediaPicker($row) {
    var frame = wp.media({
      title: 'Seleziona immagine settore',
      button: { text: 'Usa questa immagine' },
      library: { type: 'image' },
      multiple: false
    });

    frame.on('select', function () {
      var selection = frame.state().get('selection');
      var first = selection && selection.first ? selection.first() : null;
      if (!first) return;
      setOverridePreview($row, first.toJSON ? first.toJSON() : null);
    });

    frame.open();
  }

  $(document).on('click', '.abf-hero-select-image', function (event) {
    event.preventDefault();
    var $row = $(this).closest('tr');
    if (!$row.length) return;
    openMediaPicker($row);
  });

  $(document).on('click', '.abf-hero-clear-image', function (event) {
    event.preventDefault();
    var $row = $(this).closest('tr');
    if (!$row.length) return;
    setOverridePreview($row, null);
  });
})(jQuery);
