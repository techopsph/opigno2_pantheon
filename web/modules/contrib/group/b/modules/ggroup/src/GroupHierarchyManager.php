<?php

namespace Drupal\ggroup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ggroup\Graph\GroupGraphStorageInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoader;

/**
 * Manages the relationship between groups (as subgroups).
 */
class GroupHierarchyManager implements GroupHierarchyManagerInterface {

  /**
   * The group graph storage.
   *
   * @var \Drupal\ggroup\Graph\GroupGraphStorageInterface
   */
  protected $groupGraphStorage;

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
   * The group role inheritance manager.
   *
   * @var \Drupal\ggroup\GroupRoleInheritanceInterface
   */
  protected $groupRoleInheritanceManager;

  /**
   * Static cache for all group memberships per user.
   *
   * A nested array with all group memberships keyed by user ID.
   *
   * @var \Drupal\group\GroupMembership[][]
   */
  protected $userMemberships = [];

  /**
   * Static cache for all inherited group roles by user.
   *
   * A nested array with all inherited roles keyed by user ID and group ID.
   *
   * @var array
   */
  protected $mappedRoles = [];

  /**
   * Static cache for all outsider roles of group type.
   *
   * A nested array with all outsider roles keyed by group type ID and role ID.
   *
   * @var array
   */
  protected $groupTypeOutsiderRoles = [];

  /**
   * Constructs a new GroupHierarchyManager.
   *
   * @param \Drupal\ggroup\Graph\GroupGraphStorageInterface $group_graph_storage
   *   The group graph storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\GroupMembershipLoader $membership_loader
   *   The group membership loader.
   * @param \Drupal\ggroup\GroupRoleInheritanceInterface $group_role_inheritance_manager
   *   The group membership loader.
   */
  public function __construct(GroupGraphStorageInterface $group_graph_storage, EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoader $membership_loader, GroupRoleInheritanceInterface $group_role_inheritance_manager) {
    $this->groupGraphStorage = $group_graph_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->membershipLoader = $membership_loader;
    $this->groupRoleInheritanceManager = $group_role_inheritance_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function addSubgroup(GroupContentInterface $group_content) {
    $plugin = $group_content->getContentPlugin();

    if ($plugin->getEntityTypeId() !== 'group') {
      throw new \InvalidArgumentException('Given group content entity does not represent a subgroup relationship.');
    }

    $parent_group = $group_content->getGroup();
    /** @var \Drupal\group\Entity\GroupInterface $child_group */
    $child_group = $group_content->getEntity();

    if ($parent_group->id() === NULL) {
      throw new \InvalidArgumentException('Parent group must be saved before it can be related to another group.');
    }

    if ($child_group->id() === NULL) {
      throw new \InvalidArgumentException('Child group must be saved before it can be related to another group.');
    }

    $new_edge_id = $this->groupGraphStorage->addEdge($parent_group->id(), $child_group->id());

    // @todo Invalidate some kind of cache?
  }

  /**
   * {@inheritdoc}
   */
  public function removeSubgroup(GroupContentInterface $group_content) {
    $plugin = $group_content->getContentPlugin();

    if ($plugin->getEntityTypeId() !== 'group') {
      throw new \InvalidArgumentException('Given group content entity does not represent a subgroup relationship.');
    }

    $parent_group = $group_content->getGroup();

    $child_group_id = $group_content->get('entity_id')->getValue();

    if (!empty($child_group_id)) {
      $child_group_id = reset($child_group_id)['target_id'];
      $this->groupGraphStorage->removeEdge($parent_group->id(), $child_group_id);
    }

    // @todo Invalidate some kind of cache?
  }

  /**
   * {@inheritdoc}
   */
  public function groupHasSubgroup(GroupInterface $group, GroupInterface $subgroup) {
    return $this->groupGraphStorage->isDescendant($subgroup->id(), $group->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSubgroups($group_id) {
    $subgroup_ids = $this->getGroupSubgroupIds($group_id);
    return $this->entityTypeManager->getStorage('group')->loadMultiple($subgroup_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSubgroupIds($group_id) {
    return $this->groupGraphStorage->getDescendants($group_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSupergroups($group_id) {
    $subgroup_ids = $this->getGroupSupergroupIds($group_id);
    return $this->entityTypeManager->getStorage('group')->loadMultiple($subgroup_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupSupergroupIds($group_id) {
    return $this->groupGraphStorage->getAncestors($group_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInheritedGroupRoleIdsByMembership(GroupMembership $group_membership, AccountInterface $account) {
    $account_id = $account->id();
    $group = $group_membership->getGroup();
    $group_id = $group->id();
    $roles = array_keys($group_membership->getRoles());

    if (isset($this->mappedRoles[$account_id][$group_id])) {
      return $this->mappedRoles[$account_id][$group_id];
    }

    // Statically cache the memberships of a user since this method could get
    // called a lot.
    if (empty($this->userMemberships[$account_id])) {
      $this->userMemberships[$account_id] = $this->membershipLoader->loadByUser($account);
    }

    $role_map = $this->groupRoleInheritanceManager->getAllInheritedGroupRoleIds($group);

    $mapped_role_ids = [[]];
    foreach ($this->userMemberships[$account_id] as $membership) {
      $membership_gid = $membership->getGroup()->id();

      if (empty($role_map[$group_id][$membership_gid])) {
        continue;
      }

      $membership_roles = array_keys($membership->getRoles());
      $mapped_role_ids[] = array_intersect_key(array_intersect($role_map[$group_id][$membership_gid], $roles), array_flip($membership_roles));
    }

    $mapped_role_ids = array_replace_recursive(...$mapped_role_ids);
    $this->mappedRoles[$account_id][$group_id] = $this->entityTypeManager->getStorage('group_role')->loadMultiple(array_keys(array_unique($mapped_role_ids)));

    return $this->mappedRoles[$account_id][$group_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getInheritedGroupOutsiderRoleIds(GroupInterface $group, AccountInterface $account) {

    $account_id = $account->id();
    $group_id = $group->id();

    if (isset($this->mappedRoles[$account_id][$group_id])) {
      return $this->mappedRoles[$account_id][$group_id];
    }

    if (empty($this->userMemberships[$account_id])) {
      $this->userMemberships[$account_id] = $this->membershipLoader->loadByUser($account);
    }

    $role_map = $this->groupRoleInheritanceManager->getAllInheritedGroupRoleIds($group);

    $mapped_role_ids = [[]];
    foreach ($this->userMemberships[$account_id] as $membership) {
      $membership_gid = $membership->getGroupContent()->gid->target_id;
      $role_mapping = [];

      // Get all outsider roles.
      $outsider_roles = $this->getOutsiderGroupRoles($membership->getGroupContent()->getGroup());
      if (!empty($role_map[$membership_gid][$group_id])) {
        $role_mapping = array_intersect_key($role_map[$membership_gid][$group_id], $outsider_roles);
      }
      else if (!empty($role_map[$group_id][$membership_gid])) {
        $role_mapping = array_intersect_key($role_map[$group_id][$membership_gid], $outsider_roles);
      }

      $mapped_role_ids[] = $role_mapping;
    }

    $mapped_role_ids = array_replace_recursive(...$mapped_role_ids);

    $this->mappedRoles[$account_id][$group_id] = $this->entityTypeManager->getStorage('group_role')->loadMultiple(array_unique($mapped_role_ids));
    return $this->mappedRoles[$account_id][$group_id];
  }

  /**
   * Get outsider group type roles.
   *
   * @param Group $group
   *   Group.
   * @return arrays
   *   Group type roles.
   */
  protected function getOutsiderGroupRoles(Group $group) {
    if (!isset($this->groupTypeOutsiderRoles[$group->getGroupType()->id()])) {
      $storage = $this->entityTypeManager->getStorage('group_role');
      $outsider_roles = $storage->loadSynchronizedByGroupTypes([$group->getGroupType()->id()]);
      $outsider_roles[$group->getGroupType()->getOutsiderRoleId()] = $group->getGroupType()->getOutsiderRole();
      $this->groupTypeOutsiderRoles[$group->getGroupType()->id()] = $outsider_roles;
    }
    return $this->groupTypeOutsiderRoles[$group->getGroupType()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getInheritedGroupAnonymousRoleIds(GroupInterface $group, array $groups) {
    // Anonymous user doesn't have id, but we want to cache it.
    $account_id = 0;
    $group_id = $group->id();

    $role_map = $this->groupRoleInheritanceManager->getAllInheritedGroupRoleIds($group);
    $mapped_role_ids = [[]];
    foreach ($groups as $group_item) {
      $group_item_gid = $group_item->id();
      $role_mapping = [];

      $anonymous_role = [$group_item->getGroupType()->getAnonymousRoleId() => $group_item->getGroupType()->getAnonymousRole()];

      if (!empty($role_map[$group_item_gid][$group_id])) {
        $role_mapping = array_intersect_key($role_map[$group_item_gid][$group_id], $anonymous_role);
      }
      else if (!empty($role_map[$group_id][$group_item_gid])) {
        $role_mapping = array_intersect_key($role_map[$group_id][$group_item_gid], $anonymous_role);
      }

      $mapped_role_ids[] = $role_mapping;
    }

    $mapped_role_ids = array_replace_recursive(...$mapped_role_ids);

    $this->mappedRoles[$account_id][$group_id] = $this->entityTypeManager->getStorage('group_role')->loadMultiple(array_unique($mapped_role_ids));
    return $this->mappedRoles[$account_id][$group_id];
  }

}
