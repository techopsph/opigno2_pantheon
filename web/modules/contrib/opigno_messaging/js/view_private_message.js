(function ($, Drupal) {
  Drupal.behaviors.opignoViewPrivateMessage = {
    attach: function (context, settings) {
      var $rows = $('.view-private-message .views-row', context);
      var $readAll = $('#read-all-messages', context);
      var $unreadCount = $('#site-header #header-right .user-messages a .unread');
      var baseUrl = drupalSettings.path.baseUrl ? drupalSettings.path.baseUrl : '/';

      rowsClickListener($rows);

      $(document).ajaxSuccess(function() {
        var $rows = $('.view-private-message .views-row', context);
        rowsClickListener($rows);
      });

      // Mark all messages as read.
      $readAll.once('click').click(function(e) {
        e.preventDefault();

        $('.user-messages')
          .removeClass('show')
          .children('.dropdown-menu')
          .removeClass('show');

        $.ajax({
          url: baseUrl + 'ajax/messages/mark-read-all',
          success: function() {
            $unreadCount.text(0);
          }
        });

        return false;
      });
    }
  };

  function rowsClickListener($rows) {
    // Redirects to thread if user clicks on thread block.
    $rows.once('click').click(function(e) {
      e.preventDefault();

      var $thread = $(this).find('.private-message-thread');

      if (!$thread.length) {
        return false;
      }

      var id = $thread.attr('data-thread-id');
      window.location = '/private_messages/' + id;

      return false;
    });
  }

  // Fixes multiselect issue 2123241.
  if (Drupal.behaviors.multiSelect
      && !Drupal.behaviors.multiSelect.detach
  ) {
    Drupal.behaviors.multiSelect.detach = function (context, settings, trigger) {
      if (trigger === 'serialize') {
        $('select.multiselect-selected').selectAll();
      }
    };
  }
}(jQuery, Drupal));
