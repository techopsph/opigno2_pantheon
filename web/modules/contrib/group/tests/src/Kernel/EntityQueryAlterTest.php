<?php

namespace Drupal\Tests\group\Kernel;

/**
 * Tests that Group properly checks access for grouped entities.
 *
 * @todo Test operations other than view.
 *
 * @coversDefaultClass \Drupal\group\QueryAccess\EntityQueryAlter
 * @group group
 */
class EntityQueryAlterTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['group_test_plugin', 'link', 'shortcut'];

  /**
   * The shortcut storage to use in testing.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $shortcutStorage;

  /**
   * The first group type to use in testing.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupTypeA;

  /**
   * The second group type to use in testing.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupTypeB;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('shortcut');
    $this->installConfig(['link', 'shortcut']);

    $this->shortcutStorage = $this->entityTypeManager->getStorage('shortcut');
    $this->groupTypeA = $this->createGroupType(['id' => 'foo', 'creator_membership' => FALSE]);
    $this->groupTypeB = $this->createGroupType(['id' => 'bar', 'creator_membership' => FALSE]);

    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->save($storage->createFromPlugin($this->groupTypeA, 'shortcut_as_content'));
    $storage->save($storage->createFromPlugin($this->groupTypeB, 'shortcut_as_content'));
  }

  /**
   * Tests that regular access checks still work.
   */
  public function testRegularAccess() {
    $shortcut_1 = $this->createShortcut();
    $shortcut_2 = $this->createShortcut();

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_1->id(), $shortcut_2->id()], array_keys($ids), 'Regular shortcut query access still works.');
  }

  /**
   * Tests that grouped shortcuts are properly hidden.
   */
  public function testGroupAccessWithoutPermission() {
    $shortcut_1 = $this->createShortcut();
    $shortcut_2 = $this->createShortcut();

    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($shortcut_1, 'shortcut_as_content');

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_2->id()], array_keys($ids), 'Only the ungrouped shortcut shows up.');
  }

  /**
   * Tests that grouped shortcuts are visible to members.
   */
  public function testGroupAccessWithMemberPermission() {
    $shortcut_1 = $this->createShortcut();
    $shortcut_2 = $this->createShortcut();

    $this->groupTypeA->getMemberRole()->grantPermission('administer shortcut_as_content')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($shortcut_1, 'shortcut_as_content');
    $group->addMember($this->getCurrentUser());

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_1->id(), $shortcut_2->id()], array_keys($ids), 'Members can see grouped shortcuts');
  }

  /**
   * Tests that grouped shortcuts are visible to non-members.
   */
  public function testGroupAccessWithNonMemberPermission() {
    $shortcut_1 = $this->createShortcut();
    $shortcut_2 = $this->createShortcut();

    $this->groupTypeA->getOutsiderRole()->grantPermission('administer shortcut_as_content')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($shortcut_1, 'shortcut_as_content');
    $this->createGroup(['type' => $this->groupTypeA->id()]);

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_1->id(), $shortcut_2->id()], array_keys($ids), 'Outsiders can see grouped shortcuts');
  }

  /**
   * Tests the viewing of entities.
   */
  public function testViewAccess() {
    $account = $this->createUser();
    $shortcut_1 = $this->createShortcut();
    $shortcut_2 = $this->createShortcut();
    $shortcut_3 = $this->createShortcut();

    $this->groupTypeA->getMemberRole()->grantPermission('view shortcut_as_content entity')->save();
    $this->groupTypeB->getMemberRole()->grantPermission('view shortcut_as_content entity')->save();

    $group_a = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group_a->addContent($shortcut_1, 'shortcut_as_content');
    $group_a->addMember($this->getCurrentUser());
    $group_a->addMember($account);

    $group_b = $this->createGroup(['type' => $this->groupTypeB->id()]);
    $group_b->addContent($shortcut_3, 'shortcut_as_content');
    $group_b->addMember($this->getCurrentUser());
    $group_b->addMember($account);

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_1->id(), $shortcut_2->id(), $shortcut_3->id()], array_keys($ids), 'Members can see any shortcuts.');

    $this->setCurrentUser($account);
    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_1->id(), $shortcut_2->id(), $shortcut_3->id()], array_keys($ids), 'Members can see any shortcuts.');

    $this->setCurrentUser($this->createUser());
    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_2->id()], array_keys($ids), 'Only the ungrouped shortcut shows up.');
  }

  /**
   * Tests that adding new group content clears caches.
   */
  public function testNewGroupContent() {
    $shortcut_1 = $this->createShortcut();
    $shortcut_2 = $this->createShortcut();
    $this->groupTypeA->getMemberRole()->grantPermission('view shortcut_as_content entity')->save();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_1->id(), $shortcut_2->id()], array_keys($ids), 'Outsiders can see ungrouped shortcuts');

    // This should clear the cache.
    $group->addContent($shortcut_1, 'shortcut_as_content');

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_2->id()], array_keys($ids), 'Outsiders can see ungrouped shortcuts');
  }

  /**
   * Tests that adding new permissions clears caches.
   *
   * This is actually tested in the permission calculator, but added here also
   * for additional hardening. It does not really clear the cached conditions,
   * but rather return a different set as your shortcut.group_permissions cache
   * context value changes.
   *
   * We will not test any further scenarios that trigger a change in your group
   * permissions as those are -as mentioned above- tested elsewhere. It just
   * seemed like a good idea to at least test one scenario here.
   */
  public function testNewPermission() {
    $shortcut_1 = $this->createShortcut();
    $shortcut_2 = $this->createShortcut();
    $group = $this->createGroup(['type' => $this->groupTypeA->id()]);
    $group->addContent($shortcut_1, 'shortcut_as_content');
    $group->addMember($this->getCurrentUser());

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_2->id()], array_keys($ids), 'Members can only see ungrouped shortcuts');

    // This should trigger a different set of conditions.
    $this->groupTypeA->getMemberRole()->grantPermission('view shortcut_as_content entity')->save();

    $ids = $this->shortcutStorage->getQuery()->execute();
    $this->assertEqualsCanonicalizing([$shortcut_1->id(), $shortcut_2->id()], array_keys($ids), 'Outsiders can see grouped shortcuts');
  }

  /**
   * Creates a shortcut.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\shortcut\ShortcutInterface
   *   The created shortcut entity.
   */
  protected function createShortcut(array $values = []) {
    $shortcut = $this->shortcutStorage->create($values + [
      'title' => $this->randomString(),
      'shortcut_set' => 'default',
    ]);
    $shortcut->enforceIsNew();
    $this->shortcutStorage->save($shortcut);
    return $shortcut;
  }

}
