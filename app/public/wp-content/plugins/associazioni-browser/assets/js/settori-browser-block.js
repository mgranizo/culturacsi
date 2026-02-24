(function (wp) {
  if (!wp || !wp.blocks || !wp.element) return;

  var registerBlockType = wp.blocks.registerBlockType;
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
  var PanelBody = wp.components && wp.components.PanelBody;
  var TextControl = wp.components && wp.components.TextControl;
  var RangeControl = wp.components && wp.components.RangeControl;
  var ToggleControl = wp.components && wp.components.ToggleControl;
  var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (s) { return s; };
  var ServerSideRender = wp.serverSideRender;

  if (!InspectorControls || !PanelBody || !TextControl || !RangeControl || !ToggleControl || !ServerSideRender) {
    return;
  }

  registerBlockType('culturacsi/settori-browser', {
    apiVersion: 2,
    title: __('Browser Settori', 'associazioni-browser'),
    description: __('Filtro associazioni per macro categoria, settore, territorio e localita.', 'associazioni-browser'),
    icon: 'filter',
    category: 'widgets',
    keywords: [
      __('settori', 'associazioni-browser'),
      __('associazioni', 'associazioni-browser'),
      __('filtri', 'associazioni-browser')
    ],
    attributes: {
      title: { type: 'string', default: 'Categorie Attivita' },
      instanceId: { type: 'string', default: 'settori' },
      perPage: { type: 'number', default: 24 },
      showTitle: { type: 'boolean', default: true },
      customClass: { type: 'string', default: '' },
      maxWidth: { type: 'string', default: '' },
      accent: { type: 'string', default: '' },
      cardRadius: { type: 'string', default: '' },
      cardBg: { type: 'string', default: '' },
      cardBorder: { type: 'string', default: '' }
    },
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;

      return el(
        Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __('Contenuto', 'associazioni-browser'), initialOpen: true },
            el(TextControl, {
              label: __('Titolo', 'associazioni-browser'),
              value: attributes.title || '',
              onChange: function (value) { setAttributes({ title: value }); }
            }),
            el(TextControl, {
              label: __('ID istanza (unico)', 'associazioni-browser'),
              help: __('Usa ID diversi se inserisci piu browser nella stessa pagina.', 'associazioni-browser'),
              value: attributes.instanceId || '',
              onChange: function (value) { setAttributes({ instanceId: value }); }
            }),
            el(RangeControl, {
              label: __('Risultati per pagina', 'associazioni-browser'),
              min: 10,
              max: 200,
              value: attributes.perPage || 24,
              onChange: function (value) { setAttributes({ perPage: value || 24 }); }
            }),
            el(ToggleControl, {
              label: __('Mostra titolo', 'associazioni-browser'),
              checked: !!attributes.showTitle,
              onChange: function (value) { setAttributes({ showTitle: !!value }); }
            })
          ),
          el(
            PanelBody,
            { title: __('Stile', 'associazioni-browser'), initialOpen: false },
            el(TextControl, {
              label: __('Classe CSS aggiuntiva', 'associazioni-browser'),
              value: attributes.customClass || '',
              onChange: function (value) { setAttributes({ customClass: value }); }
            }),
            el(TextControl, {
              label: __('Larghezza massima (es. 1200px)', 'associazioni-browser'),
              value: attributes.maxWidth || '',
              onChange: function (value) { setAttributes({ maxWidth: value }); }
            }),
            el(TextControl, {
              label: __('Colore accento (es. #0b4aa2)', 'associazioni-browser'),
              value: attributes.accent || '',
              onChange: function (value) { setAttributes({ accent: value }); }
            }),
            el(TextControl, {
              label: __('Raggio card (es. 16px)', 'associazioni-browser'),
              value: attributes.cardRadius || '',
              onChange: function (value) { setAttributes({ cardRadius: value }); }
            }),
            el(TextControl, {
              label: __('Sfondo card (es. #ffffff)', 'associazioni-browser'),
              value: attributes.cardBg || '',
              onChange: function (value) { setAttributes({ cardBg: value }); }
            }),
            el(TextControl, {
              label: __('Bordo card (es. #d7dce2)', 'associazioni-browser'),
              value: attributes.cardBorder || '',
              onChange: function (value) { setAttributes({ cardBorder: value }); }
            })
          )
        ),
        el(
          'div',
          { className: props.className },
          el(ServerSideRender, {
            block: 'culturacsi/settori-browser',
            attributes: attributes
          })
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp);
