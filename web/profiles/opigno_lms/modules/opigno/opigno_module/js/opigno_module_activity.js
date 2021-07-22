(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoModuleActivity = {
    attach: function (context, settings) {
      var that = this;
      var fullScreen = {
        show: function () {
          $('body').addClass('fullscreen');
          Cookies.set('fullscreen', 1);
        },
        hide: function () {
          $('body').removeClass('fullscreen');
          Cookies.set('fullscreen', 0);
        }
      };

      $('.fullscreen-link a', context).once('opignoModuleActivity').on('click', function(e) {
        e.preventDefault();

        if ($('body').hasClass('fullscreen')) {
          fullScreen.hide();
        }
        else {
         fullScreen.show();
        }
      });

      var activityDeleteForm = $('form.opigno-activity-with-answers');
      if (activityDeleteForm.length) {
        activityDeleteForm.submit();
      }
    },
  };
}(jQuery, Drupal, drupalSettings));
