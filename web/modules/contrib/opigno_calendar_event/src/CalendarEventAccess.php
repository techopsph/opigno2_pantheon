<?php

namespace Drupal\opigno_calendar_event;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\EntityAccessControlHandler;

/**
 * Defines access control handler for the calendar event entity type.
 */
class CalendarEventAccess extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return $this->checkAccessToEvent($entity, $account) ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  private function checkAccessToEvent(EntityInterface $entity, AccountInterface $account) {
    $members = $entity->get('field_calendar_event_members')->getValue();
    $members = array_column($members, 'target_id');
    $members['owner'] = $entity->getOwnerId();

    return in_array($account->id(), $members);
  }

}
