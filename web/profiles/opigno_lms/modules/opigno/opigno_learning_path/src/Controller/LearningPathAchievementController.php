<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\opigno_module\OpignoModuleBadges;
use Drupal\opigno_learning_path\Progress;

/**
 * Class LearningPathAchievementController.
 */
class LearningPathAchievementController extends ControllerBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Progress bar service.
   *
   * @var \Drupal\opigno_learning_path\Progress
   */
  protected $progress;


  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database, Progress $progress) {
    $this->database = $database;
    $this->progress = $progress;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('database'), $container->get('opigno_learning_path.progress'));
  }

  /**
   * Returns max score that user can have in this module & activity.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module object.
   * @param \Drupal\opigno_module\Entity\OpignoActivity $activity
   *   Activity object.
   *
   * @return int
   *   Max score.
   */
  protected function get_activity_max_score($module, $activity) {
    $moduleHandler = \Drupal::service('module_handler');

    if ($moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive() && $module->getModuleSkillsGlobal()) {
      $query = $this->database->select('opigno_module_relationship', 'omr')
        ->fields('omr', ['max_score'])
        ->condition('omr.child_id', $activity->id())
        ->condition('omr.child_vid', $activity->getRevisionId())
        ->condition('omr.activity_status', 1);
      $results = $query->execute()->fetchAll();
    }
    else {
      $query = $this->database->select('opigno_module_relationship', 'omr')
        ->fields('omr', ['max_score'])
        ->condition('omr.parent_id', $module->id())
        ->condition('omr.parent_vid', $module->getRevisionId())
        ->condition('omr.child_id', $activity->id())
        ->condition('omr.child_vid', $activity->getRevisionId())
        ->condition('omr.activity_status', 1);
      $results = $query->execute()->fetchAll();
    }

    if (empty($results)) {
      return 0;
    }

    $result = reset($results);
    return $result->max_score;
  }

  /**
   * Returns step renderable array.
   *
   * @param array $step
   *   Step.
   *
   * @return array
   *   Step renderable array.
   */
  protected function build_step_name(array $step) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['lp_step_name'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['lp_step_name_title'],
        ],
        '#value' => $step['name'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['lp_step_name_activities'],
        ],
        '#value' => ' &dash; ' . $step['activities'] . ' Activities',
      ],
    ];
  }

  /**
   * Returns step score renderable array.
   *
   * @param array $step
   *   Step.
   *
   * @return array
   *   Step score renderable array.
   */
  protected function build_step_score(array $step) {
    $uid = $this->currentUser()->id();

    if (opigno_learning_path_is_attempted($step, $uid)) {
      $score = $step['best score'];

      return [
        '#type' => 'container',
        [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $score . '%',
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lp_step_result_bar'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => ['lp_step_result_bar_value'],
              'style' => "width: $score%",
            ],
            '#value' => '',
          ],
        ],
      ];
    }

    return ['#markup' => '&nbsp;'];
  }

  /**
   * Returns step state renderable array.
   *
   * @param array $step
   *   Step.
   *
   * @return array
   *   Step state renderable array.
   */
  protected function build_step_state(array $step) {
    $uid = $this->currentUser()->id();
    $status = opigno_learning_path_get_step_status($step, $uid);
    $markups = [
      'pending' => '<span class="lp_step_state_pending"></span>'
      . t('Pending'),
      'failed' => '<span class="lp_step_state_failed"></span>'
      . t('Failed'),
      'passed' => '<span class="lp_step_state_passed"></span>'
      . t('Passed'),
    ];
    $markup = isset($markups[$status]) ? $markups[$status] : '&dash;';
    return ['#markup' => $markup];
  }

  /**
   * Returns module panel renderable array.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Group.
   * @param null|\Drupal\group\Entity\GroupInterface $course
   *   Group.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return array
   *   Module panel renderable array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function build_module_panel(GroupInterface $training, GroupInterface $course = NULL, OpignoModule $module) {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $user = $this->currentUser();
    $moduleHandler = \Drupal::service('module_handler');

    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($training, $user->id());

    $parent = isset($course) ? $course : $training;
    $step = opigno_learning_path_get_module_step($parent->id(), $user->id(), $module, $latest_cert_date);
    $completed_on = $step['completed on'];
    $completed_on = $completed_on > 0
      ? $date_formatter->format($completed_on, 'custom', 'F d, Y')
      : '';

    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = OpignoModule::load($step['id']);
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $attempts */
    $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date);

    if ($moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive() && $module->getModuleSkillsGlobal() && !empty($attempts)) {
      $activities_from_module = $module->getModuleActivities();
      $activity_ids = array_keys($activities_from_module);
      $attempt = $this->getTargetAttempt($attempts, $module);

      $db_connection = \Drupal::service('database');
      $query = $db_connection->select('opigno_answer_field_data', 'o_a_f_d');
      $query->leftJoin('opigno_module_relationship', 'o_m_r', 'o_a_f_d.activity = o_m_r.child_id');
      $query->addExpression('MAX(o_a_f_d.activity)', 'id');
      $query->condition('o_a_f_d.user_id', $user->id())
        ->condition('o_a_f_d.module', $module->id());

      if (!$module->getModuleSkillsGlobal()) {
        $query->condition('o_a_f_d.activity', $activity_ids, 'IN');
      }

      $query->condition('o_a_f_d.user_module_status', $attempt->id())
        ->groupBy('o_a_f_d.activity');

      $activities = $query->execute()->fetchAllAssoc('id');
    }
    else {
      $activities = $module->getModuleActivities();
    }
    /** @var \Drupal\opigno_module\Entity\OpignoActivity[] $activities */
    $activities = array_map(function ($activity) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
      return OpignoActivity::load($activity->id);
    }, $activities);

    if (!empty($attempts)) {
      // If "newest" score - get the last attempt,
      // else - get the best attempt.
      $attempt = $this->getTargetAttempt($attempts, $module);
      $max_score = $attempt->calculateMaxScore();
      $score_percent = $attempt->getAttemptScore();
      $score = round($score_percent * $max_score / 100);
    }
    else {
      $attempt = NULL;
      $max_score = !empty($activities)
        ? array_sum(array_map(function ($activity) use ($module) {
          return (int) $this->get_activity_max_score($module, $activity);
        }, $activities))
        : 0;
      $score_percent = 0;
      $score = 0;
    }

    $activities = array_map(function ($activity) use ($user, $module, $attempt) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
      /** @var \Drupal\opigno_module\Entity\OpignoAnswer $answer */
      $answer = isset($attempt)
        ? $activity->getUserAnswer($module, $attempt, $user)
        : NULL;
      $score = isset($answer) ? $answer->getScore() : 0;
      $max_score = (int) $this->get_activity_max_score($module, $activity);

      if ($max_score == 0 && $activity->get('auto_skills')->getValue()[0]['value'] == 1) {
        $max_score = 10;
      }

      if ($answer && $activity->hasField('opigno_evaluation_method') && $activity->get('opigno_evaluation_method')->value && !$answer->isEvaluated()) {
        $state_class = 'lp_step_state_pending';
      }
      else {
        $state_class = isset($answer) ? 'lp_step_state_passed' : 'lp_step_state_failed';
      }

      return [
        ['data' => $activity->getName()],
        [
          'data' => [
            '#markup' => $score . '/' . $max_score,
          ],
        ],
        [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => [$state_class],
            ],
            '#value' => '',
          ],
        ],
      ];
    }, $activities);

    $activities = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['lp_module_panel_activities_overview'],
      ],
      '#rows' => $activities,
    ];

    $training_id = $training->id();
    $module_id = $module->id();
    if (isset($course)) {
      $course_id = $course->id();
      $id = "module_panel_${training_id}_${course_id}_${module_id}";
    }
    else {
      $id = "module_panel_${training_id}_${module_id}";
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => $id,
        'class' => ['lp_module_panel'],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_module_panel_header'],
        ],
        [
          '#markup' => '<a href="#" class="lp_module_panel_close">&times;</a>',
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => [
            'class' => ['lp_module_panel_title'],
          ],
          '#value' => $step['name'] . ' '
          . (!empty($completed_on)
              ? t('completed')
              : ''),
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'hr',
        '#value' => '',
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_module_panel_content'],
        ],
        (!empty($completed_on)
          ? [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => t('@name completed on @date', [
              '@name' => $step['name'],
              '@date' => $completed_on,
            ]),
          ]
          : []),
        !($max_score == 0) ? [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => t('User got @score of @max_score possible points.', [
            '@score' => $score,
            '@max_score' => $max_score,
          ]),
        ] : [],
        !($max_score == 0) ? [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => t('Total score @percent%', [
            '@percent' => $score_percent,
          ]),
        ] : [],
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => [
            'class' => ['lp_module_panel_overview_title'],
          ],
          '#value' => t('Activities Overview'),
        ],
        $activities,
        (isset($attempt)
          ? [
            Link::createFromRoute(
              $this->t('Details'),
              'opigno_module.module_result',
              [
                'opigno_module' => $module->id(),
                'user_module_status' => $attempt->id(),
              ],
              ['query' => ['skip-links' => TRUE]]
            )->toRenderable(),
          ]
          : []),
      ],
    ];
  }

  /**
   * Returns module approved activities.
   *
   * @param int $parent
   *   Group ID.
   * @param int $module
   *   Module ID.
   *
   * @return int
   *   Approved activities.
   */
  protected function module_approved_activities($parent, $module, $latest_cert_date = NULL) {
    $approved = 0;
    $user = $this->currentUser();
    $parent = Group::load($parent);
    $module = OpignoModule::load($module);
    $moduleHandler = \Drupal::service('module_handler');

    $step = opigno_learning_path_get_module_step($parent->id(), $user->id(), $module, $latest_cert_date);

    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = OpignoModule::load($step['id']);
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $attempts */
    $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date);

    if ($moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive() && $module->getModuleSkillsGlobal() && !empty($attempts)) {
      $activities_from_module = $module->getModuleActivities();
      $activity_ids = array_keys($activities_from_module);
      $attempt = $this->getTargetAttempt($attempts, $module);

      $db_connection = \Drupal::service('database');
      $query = $db_connection->select('opigno_answer_field_data', 'o_a_f_d');
      $query->leftJoin('opigno_module_relationship', 'o_m_r', 'o_a_f_d.activity = o_m_r.child_id');
      $query->addExpression('MAX(o_a_f_d.activity)', 'id');
      $query->condition('o_a_f_d.user_id', $user->id())
        ->condition('o_a_f_d.module', $module->id());

      if (!$module->getModuleSkillsGlobal()) {
        $query->condition('o_a_f_d.activity', $activity_ids, 'IN');
      }

      $query->condition('o_a_f_d.user_module_status', $attempt->id())
        ->condition('o_m_r.max_score', '', '<>')
        ->groupBy('o_a_f_d.activity');

      $activities = $query->execute()->fetchAllAssoc('id');
    }
    else {
      $activities = $module->getModuleActivities();
    }
    /** @var \Drupal\opigno_module\Entity\OpignoActivity[] $activities */
    $activities = array_map(function ($activity) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
      return OpignoActivity::load($activity->id);
    }, $activities);

    if (!empty($attempts)) {
      // If "newest" score - get the last attempt,
      // else - get the best attempt.
      $attempt = $this->getTargetAttempt($attempts, $module);
      $max_score = $attempt->calculateMaxScore();
      $score_percent = $attempt->getAttemptScore();
      $score = round($score_percent * $max_score / 100);
    }
    else {
      $attempt = NULL;
      $max_score = !empty($activities)
        ? array_sum(array_map(function ($activity) use ($module) {
          return (int) $this->get_activity_max_score($module, $activity);
        }, $activities))
        : 0;
      $score_percent = 0;
      $score = 0;
    }

    $activities = array_map(function ($activity) use ($user, $module, $attempt) {
      /** @var \Drupal\opigno_module\Entity\OpignoActivity $activity */
      /** @var \Drupal\opigno_module\Entity\OpignoAnswer $answer */
      $answer = isset($attempt)
        ? $activity->getUserAnswer($module, $attempt, $user)
        : NULL;
      $score = isset($answer) ? $answer->getScore() : 0;
      $max_score = (int) $this->get_activity_max_score($module, $activity);

      return [
        isset($answer) ? 'lp_step_state_passed' : 'lp_step_state_failed',
      ];
    }, $activities);

    foreach ($activities as $activity) {

      if ($activity[0] == 'lp_step_state_passed') {
        $approved++;
      }
    }

    return $approved;
  }

  /**
   * Returns course steps renderable array.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Parent training group entity.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course group entity.
   *
   * @return array
   *   Course steps renderable array.
   */
  protected function build_course_steps(GroupInterface $training, GroupInterface $course) {
    $user = $this->currentUser();
    $steps = opigno_learning_path_get_steps($course->id(), $user->id());
    $rows = array_map(function ($step) use ($training, $course, $user) {

      return [
        'data-training' => $training->id(),
        'data-course' => $course->id(),
        'data-module' => $step['id'],
        'data' => [
          ['data' => $this->build_step_name($step)],
          ['data' => $this->build_step_score($step)],
          ['data' => $this->build_step_state($step)],
          !empty($step['id']) && !empty($step['best_attempt']) ? [
            'data' => Link::createFromRoute($this->t('details'), 'opigno_module.module_result', [
              'opigno_module' => $step['id'],
              'user_module_status' => $step['best_attempt'],
            ])->toRenderable(),
          ] : [],
        ],
      ];
    }, $steps);

    $module_steps = array_filter($steps, function ($step) {
      return $step['typology'] === 'Module';
    });
    $module_panels = array_map(function ($step) use ($training, $course) {
      $training_id = $training->id();
      $course_id = $course->id();
      $module_id = $step['id'];
      return [
        '#type' => 'container',
        '#attributes' => [
          'id' => "module_panel_${training_id}_${course_id}_${module_id}",
          'class' => ['lp_module_panel'],
        ],
      ];
    }, $module_steps);

    return [
      '#theme' => 'opigno_learning_path_training_course_content',
      '#course_id' => $course->id(),
      [
        '#type' => 'table',
        '#attributes' => [
          'class' => ['lp_course_steps', 'mb-0'],
        ],
        '#header' => [
          t('Module'),
          t('Results'),
          t('State'),
          t('Details'),
        ],
        '#rows' => $rows,
      ],
      $module_panels,
    ];
  }

  /**
   * Returns course passed steps.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Parent training group entity.
   * @param \Drupal\group\Entity\GroupInterface $course
   *   Course group entity.
   *
   * @return array
   *   Course passed steps.
   */
  protected function course_steps_passed(GroupInterface $training, GroupInterface $course, $latest_cert_date = NULL) {
    $user = $this->currentUser();
    $steps = opigno_learning_path_get_steps($course->id(), $user->id(), NULL, $latest_cert_date);

    $passed = 0;
    foreach ($steps as $step) {
      $status = opigno_learning_path_get_step_status($step, $user->id(), FALSE, $latest_cert_date);
      if ($status == 'passed') {
        $passed++;
      }
    }

    return [
      'passed' => $passed,
      'total' => count($steps),
    ];
  }

  /**
   * Returns LP steps.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   LP steps.
   */
  protected function build_lp_steps(GroupInterface $group) {
    $user = $this->currentUser();
    $uid = $user->id();

    // Get training latest certification timestamp.
    $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);

    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);
    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = opigno_learning_path_get_all_steps($group->id(), $uid, NULL, $latest_cert_date);
    }
    else {
      // Get guided steps.
      $steps = opigno_learning_path_get_steps($group->id(), $uid, NULL, $latest_cert_date);
    }

    $steps = array_filter($steps, function ($step) use ($user) {
      if ($step['typology'] === 'Meeting') {
        // If the user have not the collaborative features role.
        if (!$user->hasPermission('view meeting entities')) {
          return FALSE;
        }

        // If the user is not a member of the meeting.
        /** @var \Drupal\opigno_moxtra\MeetingInterface $meeting */
        $meeting = \Drupal::entityTypeManager()
          ->getStorage('opigno_moxtra_meeting')
          ->load($step['id']);
        if (!$meeting->isMember($user->id())) {
          return FALSE;
        }
      }
      elseif ($step['typology'] === 'ILT') {
        // If the user is not a member of the ILT.
        /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
        $ilt = \Drupal::entityTypeManager()
          ->getStorage('opigno_ilt')
          ->load($step['id']);
        if (!$ilt->isMember($user->id())) {
          return FALSE;
        }
      }

      return TRUE;
    });
    $steps = array_map(function ($step) use ($uid, $latest_cert_date) {
      $step['status'] = opigno_learning_path_get_step_status($step, $uid, TRUE, $latest_cert_date);
      $step['attempted'] = opigno_learning_path_is_attempted($step, $uid);
      $step['progress'] = opigno_learning_path_get_step_progress($step, $uid, FALSE, $latest_cert_date);
      return $step;
    }, $steps);

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');

    $status = [
      'pending' => [
        'class' => 'lp_summary_step_state_in_progress',
        'title' => t('Pending'),
      ],
      'failed' => [
        'class' => 'lp_summary_step_state_failed',
        'title' => t('Failed'),
      ],
      'passed' => [
        'class' => 'lp_summary_step_state_passed',
        'title' => t('Passed'),
      ],
    ];

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'training_steps_' . $group->id(),
        'class' => ['lp_details'],
      ],
      array_map(function ($step) use ($group, $uid, $date_formatter, $status, $user, $latest_cert_date) {
        $is_module = $step['typology'] === 'Module';
        $is_course = $step['typology'] === 'Course';

        $time_spent = ($step['attempted'] && $step['time spent'] > 0) ? $date_formatter->formatInterval($step['time spent']) : 0;
        $completed = ($step['attempted'] && $step['completed on'] > 0) ? $date_formatter->format($step['completed on'], 'custom', 'F d Y') : '';

        if ($is_module) {
          $module = OpignoModule::load($step['id']);
          $moduleHandler = \Drupal::service('module_handler');

          if ($moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive()) {
            $attempts = $module->getModuleAttempts($user, NULL, $latest_cert_date);
            $attempt = $this->getTargetAttempt($attempts, $module);

            $account = \Drupal\user\Entity\user::load($attempt->getOwnerId());
            $answers = $module->userAnswers($account, $attempt);
            $count_answers_from_step = count($answers);

            $approved = $count_answers_from_step . '/' . $count_answers_from_step;
            $approved_percent = 100;

            $step['progress'] = 1;
          }
          else {
            $approved_activities = $this->module_approved_activities($group->id(), $step['id'], $latest_cert_date);
            $approved = $approved_activities . '/' . $step['activities'];
            $approved_percent = $approved_activities / $step['activities'] * 100;
          }
        }

        if ($is_course) {
          $course_steps = $this->course_steps_passed($group, Group::load($step['id']), $latest_cert_date);
          $passed = $course_steps['passed'] . '/' . $course_steps['total'];
          $passed_percent = ($course_steps['passed'] / $course_steps['total']) * 100;
          $score = $step['best score'];
          $score_percent = $score;
        }

        // Get existing badge count.
        $badges = 0;
        if (\Drupal::moduleHandler()->moduleExists('opigno_module') && ($is_course || $is_module)) {
          $result = OpignoModuleBadges::opignoModuleGetBadges($uid, $group->id(), $step['typology'], $step['id']);
          if ($result) {
            $badges = $result;
          }
        }

        $content = [
          '#theme' => 'opigno_learning_path_training_step',
          '#step' => $step,
          '#is_module' => $is_module,
          ($is_module ? [
            '#theme' => 'opigno_learning_path_training_module',
            '#status' => isset($status[$step['status']]) ? $status[$step['status']] : $status['pending'],
            '#group_id' => $group->id(),
            '#step' => $step,
            '#time_spent' => $time_spent,
            '#completed' => $completed,
            '#badges' => $badges,
            '#approved' => [
              'value' => $approved,
              'percent' => $approved_percent,
            ],
          ] : []),
          ($is_course ? [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['lp_step_summary_wrapper'],
            ],
            [
              '#theme' => 'opigno_learning_path_training_course',
              '#passed' => [
                'value' => $passed,
                'percent' => $passed_percent
              ],
              '#score' => $score,
              '#step' => $step,
              '#completed' => $completed,
              '#badges' => $badges,
              '#time_spent' => $time_spent,
            ],
            $this->build_course_steps($group, Group::load($step['id'])),
          ] : []),
        ];

        return $content;
      }, $steps),
    ];
  }

  /**
   * Returns training timeline.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   Training timeline.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function build_training_timeline(GroupInterface $group) {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $user = $this->currentUser();

    $latest_cert_date = LPStatus::getTrainingStartDate($group, $user->id());

    $result = (int) $this->database
      ->select('opigno_learning_path_achievements', 'a')
      ->fields('a')
      ->condition('uid', $user->id())
      ->condition('gid', $group->id())
      ->condition('status', 'completed')
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($latest_cert_date || $result === 0) {
      // If training is not completed, generate steps.
      $steps = opigno_learning_path_get_steps($group->id(), $user->id(), NULL, $latest_cert_date);
      $steps = array_filter($steps, function ($step) {
        return $step['mandatory'];
      });
      $steps = array_filter($steps, function ($step) use ($user) {
        if ($step['typology'] === 'Meeting') {
          // If the user have not the collaborative features role.
          if (!$user->hasPermission('view meeting entities')) {
            return FALSE;
          }

          // If the user is not a member of the meeting.
          /** @var \Drupal\opigno_moxtra\MeetingInterface $meeting */
          $meeting = \Drupal::entityTypeManager()
            ->getStorage('opigno_moxtra_meeting')
            ->load($step['id']);
          if (!$meeting->isMember($user->id())) {
            return FALSE;
          }
        }
        elseif ($step['typology'] === 'ILT') {
          // If the user is not a member of the ILT.
          /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
          $ilt = \Drupal::entityTypeManager()
            ->getStorage('opigno_ilt')
            ->load($step['id']);
          if (!$ilt->isMember($user->id())) {
            return FALSE;
          }
        }

        return TRUE;
      });
      $steps = array_map(function ($step) use ($user, $latest_cert_date) {
        $status = opigno_learning_path_get_step_status($step, $user->id(), TRUE, $latest_cert_date);
        if ($status == 'passed') {
          $step['passed'] = opigno_learning_path_is_passed($step, $user->id());
        }
        return $step;
      }, $steps);
    }
    else {
      // Load steps from cache table.
      $results = $this->database
        ->select('opigno_learning_path_step_achievements', 'a')
        ->fields('a', [
          'name', 'status', 'completed', 'typology', 'entity_id',
        ])
        ->condition('uid', $user->id())
        ->condition('gid', $group->id())
        ->condition('mandatory', 1)
        ->execute()
        ->fetchAll();

      $steps = array_map(function ($result) {
        // Convert datetime string to timestamp.
        if (isset($result->completed)) {
          $completed = DrupalDateTime::createFromFormat(DrupalDateTime::FORMAT, $result->completed);
          $completed_timestamp = $completed->getTimestamp();
        }
        else {
          $completed_timestamp = 0;
        }

        return [
          'name' => $result->name,
          'passed' => $result->status === 'passed',
          'completed on' => $completed_timestamp,
          'typology' => $result->typology,
          'id' => 	$result->entity_id,
        ];
      }, $results);
    }

    $items = [];
    foreach ($steps as $step) {
      $items[] = [
        'label' => $step['name'],
        'completed_on' => $step['completed on'] > 0 ?
          $date_formatter->format($step['completed on'], 'custom', 'F d, Y') : '',
        'status' => opigno_learning_path_get_step_status($step, $user->id(), TRUE, $latest_cert_date),
      ];
    }

    return [
      '#theme' => 'opigno_learning_path_training_timeline',
      '#steps' => $items,
    ];
  }

  /**
   * Returns training summary.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   Training summary.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function build_training_summary(GroupInterface $group) {
    $gid = $group->id();
    $user = $this->currentUser();
    $uid = $user->id();

    return $this->progress->getProgressAjaxContainer($gid, $uid, '', 'achievements-page');
  }

  /**
   * Returns training array.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   Training array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function build_training(GroupInterface $group) {
    return [
      '#theme' => 'opigno_learning_path_training',
      '#label' => $group->label(),
      'timeline' =>  $this->build_training_timeline($group),
      'info' => [
        '#theme' => 'opigno_learning_path_training_timeline_info',
        '#label' => t('Timeline'),
        '#text' => t('In your timeline are shown only successfully passed mandatory steps from your training'),
      ],
      'summary' => $this->build_training_summary($group),
      'details' => [
        '#theme' => 'opigno_learning_path_training_details',
        '#group_id' => $group->id(),
      ],
    ];
  }

  /**
   * Loads module panel with a AJAX.
   *
   * @param \Drupal\group\Entity\GroupInterface $training
   *   Training group.
   * @param null|\Drupal\group\Entity\GroupInterface $course
   *   Course group.
   * @param \Drupal\opigno_module\Entity\OpignoModule $opigno_module
   *   Opigno module.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function course_module_panel_ajax(GroupInterface $training, GroupInterface $course, OpignoModule $opigno_module) {
    $training_id = $training->id();
    $course_id = $course->id();
    $module_id = $opigno_module->id();
    $selector = "#module_panel_${training_id}_${course_id}_${module_id}";
    $content = $this->build_module_panel($training, $course, $opigno_module);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Loads module panel with a AJAX.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\opigno_module\Entity\OpignoModule $opigno_module
   *   Opigno module.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function training_module_panel_ajax(GroupInterface $group, OpignoModule $opigno_module) {
    $training_id = $group->id();
    $module_id = $opigno_module->id();
    $selector = "#module_panel_${training_id}_${module_id}";
    $content = $this->build_module_panel($group, NULL, $opigno_module);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Loads steps for a training with a AJAX.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function training_steps_ajax(Group $group) {
    $selector = '#training_steps_' . $group->id();
    $content = $this->build_lp_steps($group);
    $content['#attributes']['data-ajax-loaded'] = TRUE;
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Returns training page array.
   *
   * @param int $page
   *   Page id.
   *
   * @return array
   *   Training page array.
   */
  protected function build_page($page = 0) {
    $per_page = 5;

    $user = $this->currentUser();
    $uid = $user->id();

    $query = $this->database->select('group_content_field_data', 'gc');
    $query->innerJoin(
      'groups_field_data',
      'g',
      'g.id = gc.gid'
    );
    // Opigno Module group content.
    $query->leftJoin(
      'group_content_field_data',
      'gc2',
      'gc2.gid = gc.gid AND gc2.type = \'group_content_type_162f6c7e7c4fa\''
    );
    $query->leftJoin(
      'opigno_group_content',
      'ogc',
      'ogc.entity_id = gc2.entity_id AND ogc.is_mandatory = 1'
    );
    $query->leftJoin(
      'user_module_status',
      'ums',
    'ums.user_id = gc.uid AND ums.module = gc2.entity_id'
    );
    $query->addExpression('max(ums.started)', 'started');
    $query->addExpression('max(ums.finished)', 'finished');
    $gids = $query->fields('gc', ['gid'])
      ->condition('gc.type', 'learning_path-group_membership')
      ->condition('gc.entity_id', $uid)
      ->groupBy('gc.gid')
      ->orderBy('finished', 'DESC')
      ->orderBy('started', 'DESC')
      ->orderBy('gc.gid', 'DESC')
      ->range($page * $per_page, $per_page)
      ->execute()
      ->fetchCol();
    $groups = Group::loadMultiple($gids);

    return array_map([$this, 'build_training'], $groups);
  }

  /**
   * Get last or best user attempt for Module.
   *
   * @param array $attempts
   *   User module attempts.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatus
   *   $attempt
   */
  protected function getTargetAttempt(array $attempts, OpignoModule $module) {
    if ($module->getKeepResultsOption() == 'newest') {
      $attempt = end($attempts);
    }
    else {
      $attempt = opigno_learning_path_best_attempt($attempts);
    }

    return $attempt;
  }

  /**
   * Loads next achievements page with a AJAX.
   *
   * @param int $page
   *   Page id.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function page_ajax($page = 0) {
    $selector = '#achievements-wrapper';

    $content = $this->build_page($page);

    $response = new AjaxResponse();
    if (!empty($content)) {
      $response->addCommand(new AppendCommand($selector, $content));
    }
    return $response;
  }

  /**
   * Returns index array.
   *
   * @param int $page
   *   Page id.
   *
   * @return array
   *   Index array.
   */
  public function index($page = 0) {
    $profile = file_exists('profiles/opigno_lms/libraries/slick/slick/slick.css') ? '_profile' : '';
    $content = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'achievements-wrapper',
      ],
      [
        '#theme' => 'opigno_learning_path_message',
        '#markup' => t('Consult your results and download the certificates for the trainings.'),
      ],
      '#attached' => [
        'library' => [
          'opigno_learning_path/achievements',
          'opigno_learning_path/achievements_slick' . $profile,
        ],
      ],
    ];

    $content[] = $this->build_page($page);
    return $content;
  }

}
