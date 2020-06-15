<?php

namespace Drupal\opigno_messaging;

use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class OpignoMessageThread.
 *
 * @package Drupal\opigno_messaging
 */
class OpignoMessageThread {

  /**
   * Gets message treads of current user.
   *
   * @param int $uid
   *   User uid.
   *
   * @return bool|array
   *   User threads.
   */
  public static function getUserThreads($uid) {
    $db_connection = \Drupal::service('database');
    $query = $db_connection->select('private_message_thread__members', 'tm');
    $query->fields('tm', ['entity_id'])
      ->condition('tm.members_target_id', $uid);
    $result = $query->execute()->fetchCol();

    if ($result) {
      return $result;
    }

    return FALSE;
  }

  /**
   * Returns unread threads count.
   *
   * @param string $return_fields
   *   Return unread threads fields.
   *
   * @return int|array
   *   Unread threads count or ids array.
   */
  public static function getUnreadThreadCount($return_fields = '') {
    $pm_service = \Drupal::service('private_message.service');
    $uid = \Drupal::currentUser()->id();

    if ($uid > 0 && isset($pm_service)) {
      $unread_count = \Drupal::service('private_message.service')->getUnreadThreadCount();
      if ($unread_count > 0) {
        if ($return_fields) {
          $db_connection = \Drupal::service('database');
          $query = $db_connection->select('private_message_thread__last_access_time', 'pmtlat');
          $query->join('pm_thread_access_time', 'pmtat', 'pmtat.id = pmtlat.last_access_time_target_id AND pmtat.owner = :uid', [':uid' => $uid]);
          $query->join('private_message_threads', 'pmt', 'pmt.id = pmtlat.entity_id AND pmt.updated >= pmtat.access_time');
          $query->join('private_message_thread__last_delete_time', 'pmtldt', 'pmtldt.entity_id = pmtlat.entity_id');
          $query->join('pm_thread_delete_time', 'pmtdt', 'pmtdt.id = pmtldt.last_delete_time_target_id AND pmtdt.delete_time < pmt.updated');
          $query->join('private_message_thread__members', 'pmtm', 'pmtm.entity_id = pmt.id AND pmtm.members_target_id = :uid', [':uid' => $uid]);
          $query->join('private_message_thread__private_messages', 'pmtpm', 'pmtpm.entity_id = pmt.id');
          $query->join('private_messages', 'pm', 'pm.id = pmtpm.private_messages_target_id AND NOT ((pm.owner = :uid) AND (pm.created = pmt.updated))', [':uid' => $uid]);
          $query->fields('pmtlat', ['entity_id']);
          $query->fields('pmtat', ['access_time', 'id']);
          $query->fields('pmt', ['updated']);

          $unread_thread = $query->execute()->fetchAllAssoc('entity_id');

          foreach ($unread_thread as $unread) {
            $ids[] = $unread->{$return_fields};
          }
          if (!empty($ids)) {
            return $ids;
          }
        }
        else {
          return $unread_count;
        }
      }
    }

    return $return_fields ? [] : 0;
  }

  /**
   * Marks all unread treads as read.
   */
  public function markReadAll() {
    $ids = self::getUnreadThreadCount('id');
    if ($ids) {
      $db_connection = \Drupal::service('database');
      $db_connection->update('pm_thread_access_time')
        ->fields(['access_time' => time()])
        ->condition('id', $ids, 'IN')
        ->execute();
    }
    return new JsonResponse([]);
  }

}
