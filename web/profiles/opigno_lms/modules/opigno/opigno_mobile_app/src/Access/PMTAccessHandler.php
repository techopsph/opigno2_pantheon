<?php

namespace Drupal\opigno_mobile_app\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\Access\PrivateMessageThreadAccessControlHandler;

/**
 * Override default access control handler for private message thread entities.
 */
class PMTAccessHandler extends PrivateMessageThreadAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('use private messaging system')) {
      switch ($operation) {
        case 'view':
          if ($entity->isMember($account->id())) {
              return AccessResult::allowed();
          }

          break;

        case 'delete':
          if ($entity->isMember($account->id())) {
            return AccessResult::allowed();
          }

          break;
      }
    }

    return AccessResult::neutral();
  }

}
