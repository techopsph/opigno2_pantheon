(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoLearningPathMemberAdd = {
    attach: function (context, settings) {
      Drupal.opignoAutocompliteMultiselect({
          m_selected: '#training_users',
          m_available: '#training_users-available',
          autocomplete: '#training_users_autocomplete',
          dropdown: '.ui-widget.ui-widget-content',
          context: context,
      });
    },
  };
}(jQuery, Drupal, drupalSettings));
