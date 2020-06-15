<?php

namespace Drupal\opigno_statistics;

use Drupal\group\Entity\Group;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_module\Entity\OpignoModule;

/**
 * Common helper methods for a statistics pages.
 */
trait StatisticsPageTrait {

  /**
   * Builds circle indicator for a value.
   *
   * @param float $value
   *   From 0 to 1.
   *
   * @return array
   *   Render array.
   */
  protected function buildCircleIndicator($value) {
    $width = 100;
    $height = 100;
    $cx = $width / 2;
    $cy = $height / 2;
    $radius = min($width / 2, $height / 2);

    $value_rad = $value * 2 * 3.14159 - 3.14159 / 2;
    $x = round($cx + $radius * cos($value_rad), 2);
    $y = round($cy + $radius * sin($value_rad), 2);

    if ($value_rad < 3.14159 / 2) {
      $template = '<svg class="indicator" viewBox="0 0 {{ width }} {{ height }}">
  <circle cx="{{ cx }}" cy="{{ cy }}" r="{{ radius }}"></circle>
  <path d="M{{ cx }},{{ cy }}
    L{{ cx }},0
    A{{ radius }},{{ radius }} 1 0,1 {{ x }},{{ y }} z"></path>
  <circle class="inner" cx="{{ cx }}" cy="{{ cy }}" r="{{ radius - 6 }}"></circle>
</svg>';
    }
    else {
      $template = '<svg class="indicator" viewBox="0 0 {{ width }} {{ height }}">
  <circle cx="{{ cx }}" cy="{{ cy }}" r="{{ radius }}"></circle>
  <path d="M{{ cx }},{{ cy }}
    L{{ cx }},0
    A{{ radius }},{{ radius }} 1 0,1 {{ cx }},{{ cy + radius }}
    A{{ radius }},{{ radius }} 1 0,1 {{ x }},{{ y }} z"></path>
  <circle class="inner" cx="{{ cx }}" cy="{{ cy }}" r="{{ radius - 6 }}"></circle>
</svg>';
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['indicator-wrapper'],
      ],
      [
        '#type' => 'inline_template',
        '#template' => $template,
        '#context' => [
          'width' => $width,
          'height' => $height,
          'cx' => $cx,
          'cy' => $cy,
          'radius' => $radius,
          'x' => $x,
          'y' => $y,
        ],
      ],
    ];
  }

  /**
   * Builds value for the training progress block.
   *
   * @param string $label
   *   Value label.
   * @param string $value
   *   Value.
   * @param string $help_text
   *   Help text.
   *
   * @return array
   *   Render array.
   */
  protected function buildValue($label, $value, $help_text = NULL) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['value-wrapper'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['value', ($help_text) ? 'p-relative' : NULL],
        ],
        '#value' => $value,
        ($help_text) ? [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => ['popover-help'],
            'data-toggle' => 'popover',
            'data-content' => $help_text,
          ],
          '#value' => '?',
        ] : NULL,
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['label'],
        ],
        '#value' => $label,
      ],
    ];
  }

  /**
   * Builds value with a indicator for the training progress block.
   *
   * @param string $label
   *   Value label.
   * @param float $value
   *   From 0 to 1.
   * @param null|string $value_text
   *   Formatted value (optional).
   * @param string $help_text
   *   Help text.
   *
   * @return array
   *   Render array.
   */
  protected function buildValueWithIndicator($label, $value, $value_text = NULL, $help_text = NULL) {
    $value_text = isset($value_text) ? $value_text : $this->t('@percent%', [
      '@percent' => round(100 * $value),
    ]);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['value-indicator-wrapper'],
      ],
      'value' => $this->buildValue($label, $value_text, $help_text),
      'indicator' => $this->buildCircleIndicator($value),
    ];
  }

  /**
   * Builds render array for a score value.
   *
   * @param int $value
   *   Score.
   *
   * @return array
   *   Render array.
   */
  protected function buildScore($value) {
    return [
      '#type' => 'container',
      'score' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('@score%', ['@score' => $value]),
      ],
      'score_bar' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['score-bar'],
        ],
        'score_bar_inner' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['score-bar-inner'],
            'style' => "width: $value%;",
          ],
        ],
      ],
    ];
  }

  /**
   * Builds render array for a status value.
   *
   * @param string $value
   *   Status.
   *
   * @return array
   *   Render array.
   */
  protected function buildStatus($value) {
    switch (strtolower($value)) {
      default:
      case 'pending':
        $status_icon = 'icon_state_pending';
        $status_text = $this->t('Pending');
        break;

      case 'expired':
        $status_icon = 'icon_state_expired';
        $status_text = $this->t('Expired');
        break;

      case 'failed':
        $status_icon = 'icon_state_failed';
        $status_text = $this->t('Failed');
        break;

      case 'completed':
      case 'passed':
        $status_icon = 'icon_state_passed';
        $status_text = $this->t('Passed');
        break;
    }

    return [
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['icon_state', $status_icon],
        ],
      ],
      [
        '#markup' => $status_text,
      ],
    ];
  }

  /**
   * Returns users with the training expired certification.
   *
   * They shouldn't have an attempts after expiration date.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return array
   *   Users IDs.
   */
  protected function getExpiredUsers(Group $group) {
    $output = [];
    $gid = $group->id();
    try {
      // Get users with the training expired certification.
      $output = $this->database->select('user_lp_status_expire', 'lpe')
        ->fields('lpe', ['uid'])
        ->condition('gid', $gid)
        ->condition('expire', time(), '<')
        ->execute()->fetchCol();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_statistics')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if ($output) {
      // Filter users with no attempts after expiration date.
      $output = array_filter($output, function ($uid) use ($group) {
        $gid = $group->id();
        $expiration_set = LPStatus::isCertificateExpireSet($group);
        if ($expiration_set) {
          $expire_timestamp = LPStatus::getCertificateExpireTimestamp($gid, $uid);
          if ($expire_timestamp) {
            $result = $this->database->select('user_module_status', 'ums')
              ->fields('ums', ['id'])
              ->condition('learning_path', $gid)
              ->condition('user_id', $uid)
              ->condition('finished', $expire_timestamp, '>')
              ->execute()->fetchField();

            if ($result) {
              return FALSE;
            }
          }
        }

        return TRUE;
      });
    }

    return $output;
  }

  /**
   * Returns training content data by each step.
   *
   * @param int $gid
   *   Group ID.
   *
   * @return array
   *   Training content data by each step.
   */
  protected function getTrainingContentStatistics($gid) {
    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->leftJoin('opigno_learning_path_step_achievements', 's', 's.gid = a.gid AND s.uid = a.uid');
    $query->leftJoin('opigno_learning_path_step_achievements', 'sc', 'sc.id = s.id AND sc.completed IS NOT NULL');
    $query->addExpression('COUNT(sc.uid)', 'completed');
    $query->addExpression('AVG(s.score)', 'score');
    $query->addExpression('AVG(s.time)', 'time');
    $query->addExpression('MAX(s.entity_id)', 'entity_id');
    $query->addExpression('MAX(s.parent_id)', 'parent_id');
    $query->addExpression('MAX(s.position)', 'position');
    $query->addExpression('MAX(s.typology)', 'typology');
    $query->addExpression('MAX(s.id)', 'id');
    $query->condition('a.uid', 0, '<>');

    $data = $query->fields('s', ['name'])
      ->condition('a.gid', $gid)
      ->groupBy('s.name')
      ->orderBy('position')
      ->orderBy('parent_id')
      ->execute()
      ->fetchAllAssoc('entity_id');

    $entity_ids = array_keys($data);

    // Get relationships between courses and modules.
    $query = \Drupal::database()
      ->select('group_content_field_data', 'g_c_f_d');
    $query->fields('g_c_f_d', ['entity_id', 'gid']);
    if (!empty($entity_ids)) {
      $query->condition('g_c_f_d.entity_id', $entity_ids, 'IN');
    }
    $group_content = $query
      ->execute()
      ->fetchAll();

    $modules_relationships = [];

    foreach ($group_content as $content) {
      $modules_relationships[$content->entity_id][] = $content->gid;
    }

    // Sort courses and modules.
    $rows = [];
    foreach ($data as $row) {
      if ($row->typology == 'Course') {
        $rows[] = $row;
        foreach ($data as $module) {
          if (in_array($row->entity_id, $modules_relationships[$module->entity_id])) {
            $rows[] = $module;
          }
        }
      }
      elseif (($row->typology == 'Module' && $row->parent_id == 0)
        || $row->typology == 'ILT' || $row->typology == 'Meeting') {
        $rows[] = $row;
      }
    }

    return $rows;
  }

  /**
   * Returns group content average statistics for certificated training.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param mixed $users
   *   Users IDs array.
   * @param \Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent $content
   *   Group content object.
   *
   * @return array
   *   Group content average statistics for certificated training.
   *
   * @throws \Exception
   */
  protected function getGroupContentStatisticsCertificated(Group $group, $users, OpignoGroupManagedContent $content) {
    $gid = $group->id();
    $entity_type_manager = \Drupal::entityTypeManager();

    $name = '';
    $completed = 0;
    $score = 0;
    $time = 0;

    foreach ($users as $user) {
      $uid = $user->uid;

      $id = $content->getEntityId();
      $type = $content->getGroupContentTypeId();
      $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);

      switch ($type) {
        case 'ContentTypeModule':
          if ($opigno_module = OpignoModule::load($id)) {
            $name = $opigno_module->getName();
            $step_info = opigno_learning_path_get_module_step($gid, $uid, $opigno_module, $latest_cert_date);
            $step_score = $opigno_module->getKeepResultsOption() == 'newest' ? $step_info["current attempt score"] : $step_info["best score"];
          }
          break;

        case 'ContentTypeCourse':
          if ($course = Group::load($id)) {
            $name = $course->label();
            $step_info = opigno_learning_path_get_course_step($gid, $uid, $course, $latest_cert_date);
            $step_score = $step_info["best score"];
          }
          break;

        case 'ContentTypeMeeting':
          if ($meeting = $entity_type_manager->getStorage('opigno_moxtra_meeting')->load($id)) {
            $step_info = opigno_learning_path_get_meeting_step($gid, $uid, $meeting);
            $step_score = $step_info["best score"];
          }
          break;

        case 'ContentTypeILT':
          if ($ilt = $entity_type_manager->getStorage('opigno_ilt')->load($id)) {
            $name = $ilt->label();
            $step_info = opigno_learning_path_get_ilt_step($gid, $uid, $ilt);
            $step_score = $step_info["best score"];
          }
          break;
      }

      if (!empty($step_info["completed on"])) {
        $completed++;
      }

      if (!empty($step_score)) {
        $score = $score + $step_score;
      }

      if (!empty($step_info["time spent"])) {
        $time = $time + $step_info["time spent"];
      }
    }

    return [
      'name' => $name,
      'completed' => $completed,
      'score' => $score,
      'time' => $time,
    ];
  }

}
