;(function ($, Drupal) {
  Drupal.behaviors.opignoNotificationView = {
    attach: function (context, settings) {
      var $readAll = $('#read-all-notifications', context);
      var $notifications = $('.notification', context);
      var $unreadCount = $('#site-header #header-right .user-notifications a .unread');
      var $viewNotifications = $('header#site-header .user-notifications .view-opigno-notifications .views-row');
      var baseUrl = drupalSettings.path.baseUrl ? drupalSettings.path.baseUrl : '/';
      // var $markReadTrigger = $('header#site-header .user-notifications #read-all-notifications');

      // Mark all notifications as read.
      $readAll.once('click').click(function(e) {
        e.preventDefault();

        $('.user-notifications')
          .removeClass('show')
          .children('.dropdown-menu')
          .removeClass('show');

        $.ajax({
          url: baseUrl + 'ajax/notifications/mark-read-all',
          success: function() {
            $unreadCount.text(0);
            $viewNotifications.remove();
            // $markReadTrigger.remove();
          },
        });

        return false;
      });
    },
  };
}(jQuery, Drupal));
