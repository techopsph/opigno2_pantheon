<?php

namespace Drupal\opigno_notification\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\opigno_messaging\OpignoMessageThread;
use Drupal\opigno_notification\Entity\OpignoNotification;
use Drupal\opigno_notification\OpignoNotificationInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides the controller for OpignoNotification entity pages.
 *
 * @ingroup opigno_notification
 */
class OpignoNotificationController extends ControllerBase {

  /**
   * Ajax callback. Returns unread notifications count.
   */
  public function count() {
    $count = OpignoNotification::unreadCount();

    return new JsonResponse([
      'count' => $count,
    ]);
  }

  /**
   * Ajax callback. Marks the notification as read.
   */
  public function markRead(OpignoNotificationInterface $opigno_notification = NULL) {
    if ($opigno_notification === NULL) {
      throw new NotFoundHttpException();
    }

    $uid = \Drupal::currentUser()->id();

    if ($opigno_notification->getUser() !== $uid) {
      throw new AccessDeniedHttpException();
    }

    $opigno_notification->setHasRead(TRUE);
    $opigno_notification->save();

    return new JsonResponse([]);
  }

  /**
   * Ajax callback. Marks all current user notifications as read.
   */
  public function markReadAll() {
    $uid = \Drupal::currentUser()->id();

    $query = \Drupal::entityQuery('opigno_notification');
    $query->condition('uid', $uid);
    $query->condition('has_read', FALSE);
    $ids = $query->execute();

    /* @var OpignoNotificationInterface[] $notifications */
    $notifications = OpignoNotification::loadMultiple($ids);

    foreach ($notifications as $notification) {
      $notification->setHasRead(TRUE);
      $notification->save();
    }

    return new JsonResponse([]);
  }

  /**
   * Ajax callback. Get messages and its count.
   */
  public function getMessages() {
    $uid = \Drupal::currentUser()->id();
    $renderer = \Drupal::service('renderer');
    $variables = [];
    $notifications_unread = views_embed_view('opigno_notifications', 'block_unread', $uid);
    $private_messages = views_embed_view('private_message', 'block_last', $uid);
    $variables['notifications_unread_count'] = OpignoNotification::unreadCount();
    $variables['notifications_unread'] = $renderer->render($notifications_unread);
    $variables['private_messages'] = $renderer->render($private_messages);
    $variables['unread_thread_count'] = OpignoMessageThread::getUnreadThreadCount();
    return new JsonResponse($variables);
  }

}
