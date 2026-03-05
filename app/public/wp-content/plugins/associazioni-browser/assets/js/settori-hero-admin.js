(function ($) {
  'use strict';

  var isSaving = false;

  function setOverridePreview($row, attachment, useAjax = true) {
    var key = $row.data('hero-key');
    var $actions = $row.find('.abf-hero-actions');
    
    var attachmentId = attachment && attachment.id ? attachment.id : 0;
    var nonce = $('#ab_settori_images_nonce').val();

    if (!useAjax) {
      updateUI($row, attachment);
      return;
    }

    if (isSaving) return;
    isSaving = true;
    $actions.css('opacity', '0.5');

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'abf_save_hero_image_override',
        nonce: nonce,
        hero_key: key,
        attachment_id: attachmentId
      },
      success: function(response) {
        if (response.success) {
          updateUI($row, attachment, response.data.url);
        } else {
          alert('Errore durante il salvataggio: ' + (response.data || 'Errore sconosciuto'));
        }
      },
      error: function() {
        alert('Errore di connessione durante il salvataggio.');
      },
      complete: function() {
        isSaving = false;
        $actions.css('opacity', '1');
      }
    });
  }

  function updateUI($row, attachment, thumbUrl) {
    var $idLabel = $row.find('.abf-hero-override-id-label');
    var $openLink = $row.find('.abf-hero-override-open');
    var $removeBtn = $row.find('.abf-hero-clear-image');
    var $assignedImg = $row.find('.abf-hero-assigned-img');
    var $assignedEmpty = $row.find('.abf-hero-assigned-empty');

    if (!thumbUrl && attachment) {
      if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
        thumbUrl = attachment.sizes.thumbnail.url;
      } else if (attachment.url) {
        thumbUrl = attachment.url;
      }
    }

    var attachmentId = attachment && attachment.id ? attachment.id : 0;
    
    if (attachmentId > 0 && thumbUrl) {
      $idLabel.text('Attachment ID: ' + attachmentId);
      $removeBtn.show();
      
      var openUrl = attachment && attachment.url ? attachment.url : thumbUrl;
      if (openUrl) {
        $openLink.attr('href', openUrl).show();
      } else {
        $openLink.attr('href', '#').hide();
      }

      $assignedImg.attr('src', thumbUrl).show();
      $assignedEmpty.hide();
      $row.removeClass('abf-hero-row-missing abf-hero-row-auto').addClass('abf-hero-row-override');
    } else {
      $idLabel.text('Override non impostato');
      $openLink.attr('href', '#').hide();
      $removeBtn.hide();

      // Revert to auto-suggestion if available
      var autoUrl = $row.data('auto-url');
      if (autoUrl) {
        $assignedImg.attr('src', autoUrl).show();
        $assignedEmpty.hide();
        $row.removeClass('abf-hero-row-missing abf-hero-row-override').addClass('abf-hero-row-auto');
      } else {
        $assignedImg.attr('src', '').hide();
        $assignedEmpty.show();
        $row.removeClass('abf-hero-row-auto abf-hero-row-override').addClass('abf-hero-row-missing');
      }
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

  $(document).on('click', ' .abf-hero-select-image'.trim(), function (event) {
    event.preventDefault();
    var $row = $(this).closest('tr');
    if (!$row.length) return;
    openMediaPicker($row);
  });

  $(document).on('click', ' .abf-hero-clear-image'.trim(), function (event) {
    event.preventDefault();
    var $row = $(this).closest('tr');
    if (!$row.length) return;
    setOverridePreview($row, null);
  });

  $(document).on('click', ' .abf-hero-accept-suggestion'.trim(), function (event) {
    event.preventDefault();
    var $row = $(this).closest('tr');
    if (!$row.length) return;
    var suggestionId = $row.data('suggestion-id');
    if (!suggestionId) return;
    
    // Create a mock attachment object to update UI
    var attachment = {
      id: suggestionId,
      sizes: { thumbnail: { url: $row.data('auto-url') } },
      url: $row.data('auto-url')
    };
    
    setOverridePreview($row, attachment);
  });

  // Verify image loads
  $(document).on('error', ' .abf-hero-preview img'.trim(), function() {
    $(this).hide();
    $(this).siblings('.abf-hero-preview-empty').text('Errore caricamento').show();
  });

})(jQuery);
