(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoILTForm = {
    attach: function (context, settings) {
      Drupal.opignoAutocompliteMultiselect({
        m_selected: '#members',
        m_available: '#members-available',
        autocomplete: '#members_autocomplete',
        dropdown: '.ui-widget.ui-widget-content',
        context: context,
      });
    }
  };

}(jQuery, Drupal, drupalSettings));
