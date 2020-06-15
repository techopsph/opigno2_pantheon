(function ($, Drupal) {
  /**
   * Implemented autocomplete with multiselect to work together.
   *
   * @param settings
   */
  Drupal.opignoAutocompliteMultiselect = function (settings) {
    // Example settings argument.
    // {
    //   m_selected: '#training_users',
    //   m_available: '#training_users-available',
    //   autocomplete: '#training_users_autocomplete',
    //   dropdown: '.ui-widget.ui-widget-content',
    //   context: context,
    // }

    var $m_selected = $(settings.m_selected, settings.context);
    var $m_available = $(settings.m_available, settings.context);
    var $autocomplete = $(settings.autocomplete, settings.context);
    var $dropdown = $(settings.dropdown);

    function hideDropdown() {
      $dropdown.addClass('invisible');
    }

    function setDropdownDefault() {
      $dropdown.removeClass('invisible');
    }

    $autocomplete.once('autocompleteselect').on('autocompleteselect', function (e, ui) {
      // Get ids of the already selected options.
      var selected_ids = $('option', $m_selected)
        .map(function () {
          return $(this).val();
        }).get();
      // Replace available options list with the selected option.
      $m_available.empty();
      if (selected_ids.indexOf(ui.item.id) === -1) {
        var option_html = '<option value="' + ui.item.id + '">'
          + ui.item.label
          + '</option>';
        $m_available.append(option_html);
      }

      $m_available.trigger('updateCount');
      $m_selected.trigger('updateCount');
      hideDropdown();
    });

    $autocomplete.once('autocompleteresponse').on('autocompleteresponse', function (e, ui) {
      // Get ids of the already selected options.
      var selected_ids = $('option', $m_selected)
        .map(function () {
          return $(this).val();
        }).get();
      // Get available options without the already selected.
      var options = ui.content.filter(function (option) {
        return selected_ids.indexOf(option.id) === -1;
      });
      // Replace available options list with the available options.
      $m_available.empty();
      options.forEach(function (option) {
        var option_html = '<option value="' + option.id + '">'
          + option.label
          + '</option>';
        $m_available.append(option_html);
      });

      $m_available.trigger('updateCount');
      $m_selected.trigger('updateCount');
      hideDropdown();
    });

    $autocomplete.on('blur', function () {
      setDropdownDefault();
    });
  };

  /**
   * Added counter for multiselect option.
   *
   * @param context
   */
  Drupal.behaviors.opignoMultiselect = {
    attach: function (context) {
      $('.multiselect-wrapper', context).once('mobileMultiselect').each(function () {
        var $this = $(this);
        var $select = $this.find('select');
        var $buttons = $this.find('.multiselect-btns li');

        const updateCount = function () {
          // Use set timeout to move function bottom of the stack.
          setTimeout(function () {
            $select.each(function () {
              var $select = $(this);
              $select.prev('label').find('span').text(' (' + $select.find('option').length + ')');
            });
          });
        };

        updateCount();

        // Insert count element.
        $select.each(function () {
          $(this).prev('label').append('<span></span>');
        });

        // Option count.
        $buttons.on('click.multiselect', updateCount);
        $select.on('dblclick.multiselect updateCount', updateCount);
      });
    }
  };

  // Fixes multiselect issue 2123241.
  if (Drupal.behaviors.multiSelect && !Drupal.behaviors.multiSelect.detach) {
    Drupal.behaviors.multiSelect.detach = function (context, settings, trigger) {
      if (trigger === 'serialize') {
        $('select.multiselect-selected').selectAll();
      }
    };
  }

}(jQuery, Drupal));
