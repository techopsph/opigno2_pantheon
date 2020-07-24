<?php

namespace Drupal\ggroup\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ggroup\GroupHierarchyManager;
use Drupal\group\Access\GroupPermissionCalculatorBase;
use Drupal\group\Access\RefinableCalculatedGroupPermissions;
use Drupal\group\Access\CalculatedGroupPermissionsItem;
use Drupal\group\Access\CalculatedGroupPermissionsItemInterface;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\GroupMembershipLoader;
use Drupal\group_permissions\Entity\GroupPermission;

/**
 * Calculates group permissions for an account.
 */
class InheritGroupPermissionCalculator extends GroupPermissionCalculatorBase {

  /**
   * The group hierarchy manager.
   *
   * @var \Drupal\ggroup\GroupHierarchyManager
   */
  protected $hierarchyManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $membershipLoader;

  /**
   * Constructs a InheritGroupPermissionCalculator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ggroup\GroupHierarchyManager $hierarchy_manager
   *   The group hierarchy manager.
   * @param \Drupal\group\GroupMembershipLoader $membership_loader
   *   The group membership loader.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GroupHierarchyManager $hierarchy_manager, GroupMembershipLoader $membership_loader) {
    $this->entityTypeManager = $entity_type_manager;
    $this->hierarchyManager = $hierarchy_manager;
    $this->membershipLoader = $membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateMemberPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions->addCacheContexts(['user']);

    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $calculated_permissions->addCacheableDependency($user);

    foreach ($this->membershipLoader->loadByUser($account) as $group_membership) {
      $group = $group_membership->getGroup();

      // Add group content types as a cache dependency.
      $plugins = $group->getGroupType()->getInstalledContentPlugins();
      foreach ($plugins as $plugin) {
        if ($plugin->getEntityTypeId() == 'group') {
          $group_content_types = GroupContentType::loadByContentPluginId($plugin->getPluginId());
          foreach ($group_content_types as $group_content_type) {
            $calculated_permissions->addCacheableDependency($group_content_type);
          }
        }
      }

      $group_roles = $this->hierarchyManager->getInheritedGroupRoleIdsByMembership($group_membership, $account);
      $permission_sets = [];

      foreach ($group_roles as $group_role) {
        $permission_sets[] = $group_role->getPermissions();
        $calculated_permissions->addCacheableDependency($group_role);
      }

      $permissions = $permission_sets ? array_merge(...$permission_sets) : [];

      $item = new CalculatedGroupPermissionsItem(
        CalculatedGroupPermissionsItemInterface::SCOPE_GROUP,
        $group->id(),
        $permissions
      );

      $calculated_permissions->addItem($item);
      $calculated_permissions->addCacheableDependency($group);
    }

    return $calculated_permissions;
  }

  public function calculateAnonymousPermissions() {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions->addCacheContexts(['user']);

    // We have to select all the groups, because we need the mapping in
    // both directions.
    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();

    foreach ($groups as $group) {
      $permission_sets = [];

      // Add group content types as a cache dependency.
      $plugins = $group->getGroupType()->getInstalledContentPlugins();
      foreach ($plugins as $plugin) {
        if ($plugin->getEntityTypeId() == 'group') {
          $group_content_types = GroupContentType::loadByContentPluginId($plugin->getPluginId());
          foreach ($group_content_types as $group_content_type) {
            $calculated_permissions->addCacheableDependency($group_content_type);
          }
        }
      }

      $group_roles = $this->hierarchyManager->getInheritedGroupAnonymousRoleIds($group, $groups);

      foreach ($group_roles as $group_role) {
        $permission_sets[] = $group_role->getPermissions();
        $calculated_permissions->addCacheableDependency($group_role);
      }

      $permissions = $permission_sets ? array_merge(...$permission_sets) : [];

      $item = new CalculatedGroupPermissionsItem(
        CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE,
        $group->id(),
        $permissions
      );

      $calculated_permissions->addItem($item);
      $calculated_permissions->addCacheableDependency($group);
    }

    return $calculated_permissions;
  }

  public function calculateOutsiderPermissions(AccountInterface $account) {
    $calculated_permissions = new RefinableCalculatedGroupPermissions();
    $calculated_permissions->addCacheContexts(['user']);

    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $calculated_permissions->addCacheableDependency($user);

    // We have to select all the groups, because we need the mapping in
    // both directions.
    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple();

    foreach ($groups as $group) {
      // We check only groups where the user is outsider.
      if ($group->getMember($user)) {
        continue;
      }

      // Add group content types as a cache dependency.
      $plugins = $group->getGroupType()->getInstalledContentPlugins();
      foreach ($plugins as $plugin) {
        if ($plugin->getEntityTypeId() == 'group') {
          $group_content_types = GroupContentType::loadByContentPluginId($plugin->getPluginId());
          foreach ($group_content_types as $group_content_type) {
            $calculated_permissions->addCacheableDependency($group_content_type);
          }
        }
      }

      $permission_sets = [];

      $group_roles = $this->hierarchyManager->getInheritedGroupOutsiderRoleIds($group, $user);

      foreach ($group_roles as $group_role) {
        $permission_sets[] = $group_role->getPermissions();
        $calculated_permissions->addCacheableDependency($group_role);
      }

      $permissions = $permission_sets ? array_merge(...$permission_sets) : [];

      $item = new CalculatedGroupPermissionsItem(
        CalculatedGroupPermissionsItemInterface::SCOPE_GROUP_TYPE,
        $group->id(),
        $permissions
      );

      $calculated_permissions->addItem($item);
      $calculated_permissions->addCacheableDependency($group);
    }

    return $calculated_permissions;
  }

}
