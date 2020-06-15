(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoMoxtraMeetingForm = {
    attach: function (context, settings) {
      Drupal.opignoAutocompliteMultiselect({
        m_selected: '#meeting_members',
        m_available: '#meeting_members-available',
        autocomplete: '#meeting_members_autocomplete',
        dropdown: '.ui-widget.ui-widget-content',
        context: context,
      });
    },
  };
}(jQuery, Drupal, drupalSettings));
