(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoModuleActivity = {
    attach: function (context, settings) {
      var that = this;
      var fullScreen = {
        show: function () {
          $('body', context).addClass('fullscreen');
          that.goInFullscreen(document.querySelector('html'));
        },
        hide: function () {
          $('body', context).removeClass('fullscreen');
          that.goOutFullscreen();
        }
      };

      $(document).on('fullscreenchange', function (e) {
        if (!this.fullscreen) {
          fullScreen.hide();
        }
      });

      $('.fullscreen-link a', context).click(function(e) {
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

    goInFullscreen: function (element) {
      if (element.requestFullscreen) {
        element.requestFullscreen();
      }
      else if (element.mozRequestFullScreen) {
        element.mozRequestFullScreen();
      }
      else if (element.webkitRequestFullscreen) {
        element.webkitRequestFullscreen();
      }
      else if (element.msRequestFullscreen) {
        element.msRequestFullscreen();
      }
    },

    goOutFullscreen: function () {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      }
      else if (document.mozCancelFullScreen) {
        document.mozCancelFullScreen();
      }
      else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
      else if (document.msExitFullscreen) {
        document.msExitFullscreen();
      }
    },
  };
}(jQuery, Drupal, drupalSettings));
