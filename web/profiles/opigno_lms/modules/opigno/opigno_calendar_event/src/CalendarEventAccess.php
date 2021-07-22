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
    if ($account->hasPermission($operation . ' opigno_calendar_event')) {
      return AccessResult::allowed();
    }
    if ($entity->get('uid')->target_id == $account->id() && $account->hasPermission($operation . ' own opigno calendar event')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($account->hasPermission('create opigno_calendar_event')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
