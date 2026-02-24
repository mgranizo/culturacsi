(function (wp) {
  if (!wp || !wp.blocks || !wp.element) return;

  var registerBlockType = wp.blocks.registerBlockType;
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var InspectorControls = wp.blockEditor && wp.blockEditor.InspectorControls;
  var PanelBody = wp.components && wp.components.PanelBody;
  var TextControl = wp.components && wp.components.TextControl;
  var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (s) { return s; };
  var ServerSideRender = wp.serverSideRender;

  if (!InspectorControls || !PanelBody || !TextControl || !ServerSideRender) return;

  registerBlockType('culturacsi/events-calendar', {
    apiVersion: 2,
    title: __('Calendario Eventi', 'assoc-portal'),
    description: __('Calendario mensile eventi, inseribile e stilizzabile come blocco.', 'assoc-portal'),
    icon: 'calendar-alt',
    category: 'widgets',
    keywords: [
      __('calendario', 'assoc-portal'),
      __('eventi', 'assoc-portal'),
      __('portal', 'assoc-portal')
    ],
    attributes: {
      title: { type: 'string', default: '' },
      customClass: { type: 'string', default: '' }
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
            { title: __('Impostazioni Calendario', 'assoc-portal'), initialOpen: true },
            el(TextControl, {
              label: __('Titolo sezione (opzionale)', 'assoc-portal'),
              value: attributes.title || '',
              onChange: function (value) { setAttributes({ title: value || '' }); }
            }),
            el(TextControl, {
              label: __('Classe CSS aggiuntiva', 'assoc-portal'),
              value: attributes.customClass || '',
              onChange: function (value) { setAttributes({ customClass: value || '' }); }
            })
          )
        ),
        el(
          'div',
          { className: props.className },
          el(ServerSideRender, {
            block: 'culturacsi/events-calendar',
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
