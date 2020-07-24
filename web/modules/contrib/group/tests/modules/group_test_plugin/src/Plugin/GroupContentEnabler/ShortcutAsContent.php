<?php

namespace Drupal\group_test_plugin\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Provides a content enabler for shortcuts.
 *
 * @GroupContentEnabler(
 *   id = "shortcut_as_content",
 *   label = @Translation("Group shortcut"),
 *   description = @Translation("Adds shortcuts to groups."),
 *   entity_type_id = "shortcut",
 *   entity_access = TRUE,
 *   pretty_path_key = "shortcut",
 *   reference_label = @Translation("Shortcut"),
 *   reference_description = @Translation("The name of the shortcut you want to add to the group"),
 *   handlers = {
 *     "access" = "Drupal\group\Plugin\GroupContentAccessControlHandler",
 *     "permission_provider" = "Drupal\group\Plugin\GroupContentPermissionProvider",
 *   },
 *   admin_permission = "administer shortcut_as_content"
 * )
 */
class ShortcutAsContent extends GroupContentEnablerBase {
}
