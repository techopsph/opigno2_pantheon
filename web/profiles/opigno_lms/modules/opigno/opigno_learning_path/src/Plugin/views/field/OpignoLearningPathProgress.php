<?php

namespace Drupal\opigno_learning_path\Plugin\views\field;

use Drupal\opigno_learning_path\Entity\LatestActivity;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to output user progress for current LP.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_learning_path_progress")
 */
class OpignoLearningPathProgress extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function render(ResultRow $values) {
    $account = \Drupal::currentUser();
    $uid = $account->id();
    // Get an entity object.
    $entity = $values->_entity;
    $group = $entity instanceof LatestActivity ? $entity->getTraining() : $entity;
    if (!is_null($group)) {
      $progress_service = \Drupal::service('opigno_learning_path.progress');
      return $progress_service->getProgressAjaxContainer($group->id(), $account->id(), '', 'mini');
    };

    return '';
  }

}
