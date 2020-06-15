<?php

namespace Drupal\opigno_module\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_learning_path\Entity\LPStatus;


/**
 * Provides a 'LearningPathProgressBlock' block.
 *
 * @Block(
 *  id = "opigno_module_learning_path_progress_block",
 *  admin_label = @Translation("Learning path progress"),
 * )
 */
class LearningPathProgressBlock extends BlockBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function build() {
    if (!opigno_module_is_activity_route()) {
      return [];
    }

    $build = [];
    $home_link = NULL;
    $user = \Drupal::currentUser();

    if ($gid = OpignoGroupContext::getCurrentGroupId()) {
      if ($group = \Drupal::entityTypeManager()->getStorage('group')->load($gid)) {
        $home_link = Link::createFromRoute(t('home'), 'entity.group.canonical', ['group' => $group->id()], ['attributes' => ['class' => ['w-100']]])->toRenderable();
        $home_link = render($home_link);
      }
    }
    $latest_cert_date = LPStatus::getTrainingStartDate($group, $user->id());
    $progress = ($user && isset($group)) ? round(opigno_learning_path_progress($group->id(), $user->id(), $latest_cert_date) * 100) : 0;

    $build = [
      'home_link' => render($home_link),
      'progress' => $progress,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
