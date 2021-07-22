;(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoNotificationView = {
    attach: function (context) {
      var $readAll = $('#read-all-notifications', context);
      var $unreadCount = $('#site-header #header-right .user-notifications a .unread');
      var $viewNotifications = $('header#site-header .user-notifications .view-opigno-notifications .views-row');
      var baseUrl = drupalSettings.path.baseUrl ? drupalSettings.path.baseUrl : '/';

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
          }
        });

        return false;
      });

      // Update messages.
      // Run every 30sec and refresh.
      setInterval(function () {
        $.ajax({
          url: baseUrl + 'ajax/notifications/get-messages',
          success: function(response) {
            var notificationsCounter = $('.user-notifications .unread', context);
            var notifications = $('.user-notifications #notifications-wrapper', context);
            var messagesCounter = $('.user-messages .unread', context);
            var messages = $('.user-messages #messages-wrapper', context);
            // Update counter and items.
            if (response['notifications_unread_count'] > 0) {
              notificationsCounter.html(response['notifications_unread_count']);
              notificationsCounter.show();
              notifications.html(response['notifications_unread']);
            }
            else {
              notificationsCounter.hide();
              notifications.html('');
            }
            if (response['unread_thread_count'] > 0) {
              messagesCounter.html(response['unread_thread_count']);
              messagesCounter.show();
              messages.html(response['private_messages']);
            }
            else {
              messagesCounter.hide();
            }
          }
        });
      }, 1000 * 30 );

      // Hide counters onload if 0;
      // Need this because .hidden overridden.
      $('.user-notifications .hidden', context).hide();
      $('.user-messages .hidden', context).hide();
    }
  };
}(jQuery, Drupal, drupalSettings));
