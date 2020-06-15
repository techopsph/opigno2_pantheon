<?php

namespace Drupal\opigno_statistics\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_statistics\StatisticsPageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\group\Entity\Group;

/**
 * Implements the training statistics page.
 */
class TrainingForm extends FormBase {

  use StatisticsPageTrait;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $date_formatter;

  /**
   * TrainingForm constructor.
   */
  public function __construct(
    Connection $database,
    TimeInterface $time,
    DateFormatterInterface $date_formatter
  ) {
    $this->database = $database;
    $this->time = $time;
    $this->date_formatter = $date_formatter;
  }

  /**
   * Create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_statistics_training_form';
  }

  /**
   * Builds training progress.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param \Drupal\Core\Datetime\DrupalDateTime $datetime
   *   Date.
   * @param mixed $expired_uids
   *   Users uids with the training expired certification.
   *
   * @return array
   *   Render array.
   *
   * @throws \Exception
   */
  protected function buildTrainingsProgress(GroupInterface $group, DrupalDateTime $datetime, $expired_uids = NULL) {
    $time_str = $datetime->format(DrupalDateTime::FORMAT);
    $group_bundle = $group->bundle();

    // Get number of users with expired certificates.
    $expired_users_number = !empty($expired_uids) ? count($expired_uids) : 0;

    $query = $this->database->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('SUM(a.progress) / (COUNT(a.progress) + :expired_users_number) / 100', 'progress', [':expired_users_number' => $expired_users_number]);
    $query->addExpression('COUNT(a.completed) / (COUNT(a.registered) + :expired_users_number)', 'completion', [':expired_users_number' => $expired_users_number]);
    $query->condition('a.uid', 0, '<>');

    if (!empty($expired_uids)) {
      // Exclude users with the training expired certification.
      $query->condition('a.uid', $expired_uids, 'NOT IN');
    }

    $or_group = $query->orConditionGroup();
    $or_group->condition('a.completed', $time_str, '<');
    $or_group->isNull('a.completed');

    if ($group_bundle == 'learning_path') {
      $data = $query->condition('a.gid', $group->id())
        ->condition('a.registered', $time_str, '<')
        ->execute()
        ->fetchAssoc();
    }
    elseif ($group_bundle == 'opigno_class') {
      $query_class = $this->database->select('group_content_field_data', 'g_c_f_d')
        ->fields('g_c_f_d', ['gid'])
        ->condition('entity_id', $group->id())
        ->condition('type', 'group_content_type_27efa0097d858')
        ->execute()
        ->fetchAll();

      $lp_ids = [];
      foreach ($query_class as $result_ids) {
        $lp_ids[] = $result_ids->gid;
      }

      if (empty($lp_ids)) {
        $lp_ids[] = 0;
      }
      $query->condition('a.gid', $lp_ids, 'IN');

      $data = $query->execute()->fetchAssoc();
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['trainings-progress'],
      ],
      'progress' => $this->buildValueWithIndicator(
        $this->t('Training Progress'),
        $data['progress'],
        NULL,
        t('The training progress is the sum of progress for all the users registered to the training divided by the number of users registered to the training.')
      ),
      'completion' => $this->buildValueWithIndicator(
        $this->t('Training Completion'),
        $data['completion'],
        NULL,
        t('The training completion for a training is the total number of users being successful at the training divided by the number of users registered to the training.')
      ),
    ];
  }

  /**
   * Builds one block for the user metrics.
   *
   * @param string $label
   *   Label.
   * @param string $value
   *   Value.
   * @param string $help_text
   *   Help text.
   *
   * @return array
   *   Render array.
   */
  protected function buildUserMetric($label, $value, $help_text = NULL) {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['user-metric'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['user-metric-value'],
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
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['user-metric-label'],
        ],
        '#value' => $label,
      ],
    ];
  }

  /**
   * Builds user metrics.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   *
   * @return array
   *   Render array.
   */
  protected function buildUserMetrics(GroupInterface $group) {
    if ($group->bundle() == 'opigno_class') {
      $condition = 'AND gc.type IN (\'opigno_class-group_membership\')';
    }
    else {
      $condition = 'AND gc.type IN (\'learning_path-group_membership\', \'opigno_course-group_membership\')';
    }

    $query = $this->database->select('users', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
" . $condition . "
AND gc.gid = :gid",
      [
        ':gid' => $group->id(),
      ]
    );
    $users = $query
      ->condition('u.uid', 0, '<>')
      ->countQuery()
      ->execute()
      ->fetchField();

    $now = $this->time->getRequestTime();
    // Last 7 days.
    $period = 60 * 60 * 24 * 7;

    $query = $this->database->select('users_field_data', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
" . $condition . "
AND gc.gid = :gid",
      [
        ':gid' => $group->id(),
      ]
    );
    $new_users = $query
      ->condition('u.uid', 0, '<>')
      ->condition('u.created', $now - $period, '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    $query = $this->database->select('users_field_data', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
" . $condition . "
AND gc.gid = :gid",
      [
        ':gid' => $group->id(),
      ]
    );
    $active_users = $query
      ->condition('u.uid', 0, '<>')
      ->condition('u.login', $now - $period, '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    $users_block = $this->buildUserMetric(
      $this->t('Users'),
      $users,
      t('This is the number of users registered to that training.')
    );
    $new_users_block = $this->buildUserMetric(
      $this->t('New users'),
      $new_users,
      t('This is the number of users who registered during the last 7 days')
    );
    $active_users_block = $this->buildUserMetric(
      $this->t('Recently active users'),
      $active_users,
      t('This is the number of users who where active in that training within the last 7 days.')
    );

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['user-metrics'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['user-metrics-title'],
        ],
        '#value' => $this->t('Users metrics'),
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => ['popover-help'],
            'data-toggle' => 'popover',
            'data-content' => t('The metrics below are related to this training'),
          ],
          '#value' => '?',
        ],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['user-metrics-content'],
        ],
        'users' => $users_block,
        'new_users' => $new_users_block,
        'active_users' => $active_users_block,
      ],
    ];
  }

  /**
   * Builds training content.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param mixed $expired_uids
   *   Users uids with the training expired certification.
   *
   * @return array
   *   Render array.
   *
   * @throws \Exception
   */
  protected function buildTrainingContent(GroupInterface $group, $expired_uids = NULL) {
    $gid = $group->id();

    $query = $this->database->select('users', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
AND gc.type IN ('learning_path-group_membership', 'opigno_course-group_membership')
AND gc.gid = :gid",
      [
        ':gid' => $gid,
      ]
    );
    $query->fields('u', ['uid']);
    $users = $query
      ->condition('u.uid', 0, '<>')
      ->execute()
      ->fetchAll();

    $users_number = count($users);

    // Filter users with expired certificate.
    $users = array_filter($users, function ($user) use ($expired_uids) {
      return !in_array($user->uid, $expired_uids);
    });

    $table = [
      '#type' => 'table',
      '#attributes' => [
        'class' => [
          'statistics-table',
          'training-content-list',
          'table-striped',
        ],
      ],
      '#header' => [
        $this->t('Step'),
        $this->t('% Completed'),
        $this->t('Avg score'),
        $this->t('Avg time spent'),
      ],
      '#rows' => [],
    ];

    $rows = [];
    if (LPStatus::isCertificateExpireSet($group)) {
      // Calculate training content in condition of certification.
      $contents = OpignoGroupManagedContent::loadByGroupId($gid);
      if ($users && $contents) {
        foreach ($contents as $content) {
          $content_id = $content->id();
          $rows[$content_id] = new \stdClass();

          $rows[$content_id]->name = '';
          $rows[$content_id]->completed = 0;
          $rows[$content_id]->score = 0;
          $rows[$content_id]->time = 0;

          $statistics = $this->getGroupContentStatisticsCertificated($group, $users, $content);

          if (!empty($statistics['name'])) {
            // Step name.
            $rows[$content_id]->name = $statistics['name'];
            // Step average completed.
            $rows[$content_id]->completed = $statistics['completed'];
            // Step average score.
            $rows[$content_id]->score = $statistics['score'] / $users_number;
            // Step average score.
            $rows[$content_id]->time = round($statistics['time'] / $users_number);
          }
        }
      }
    }
    else {
      // Calculate training content without certification condition.
      $rows = $this->getTrainingContentStatistics($gid);
    }

    if ($rows) {
      foreach ($rows as $row) {
        if (!empty($row->name)) {
          $completed = round(100 * $row->completed / $users_number);
          $score = round($row->score);
          $time = $row->time > 0
            ? $this->date_formatter->formatInterval($row->time)
            : '-';

          $table['#rows'][] = [
            $row->name,
            $this->t('@completed%', ['@completed' => $completed]),
            $this->t('@score%', ['@score' => $score]),
            $time,
          ];
        }
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['training-content'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['training-content-title'],
        ],
        '#value' => $this->t('Training Content'),
      ],
      'list' => $table,
    ];
  }

  /**
   * Builds users results for Learning paths.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group.
   * @param mixed $expired_uids
   *   Users uids with the training expired certification.
   *
   * @return array
   *   Render array.
   */
  protected function buildUsersResultsLp(GroupInterface $group, $expired_uids = NULL) {
    $query = $this->database->select('users_field_data', 'u');
    $query->innerJoin(
      'group_content_field_data',
      'gc',
      "gc.entity_id = u.uid
AND gc.type IN ('learning_path-group_membership', 'opigno_course-group_membership')
AND gc.gid = :gid",
      [
        ':gid' => $group->id(),
      ]
    );
    $query->leftJoin(
      'opigno_learning_path_achievements',
      'a',
      'a.gid = gc.gid AND a.uid = u.uid'
    );

    $query->condition('u.uid', 0, '<>');

    $data = $query
      ->fields('u', ['uid', 'name'])
      ->fields('a', ['status', 'score', 'time'])
      ->execute()
      ->fetchAll();

    $table = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'users-results-list', 'table-striped', 'trainings-list'],
      ],
      '#header' => [
        $this->t('User'),
        $this->t('Score'),
        $this->t('Passed'),
        $this->t('Time spent'),
        $this->t('Details'),
      ],
      '#rows' => [],
    ];
    foreach ($data as $row) {
      // Expired training certification flag.
      $expired = FALSE;
      if (!empty($expired_uids) && in_array($row->uid, $expired_uids)) {
        $expired = TRUE;
      }

      if ($expired) {
        $row->score = 0;
      }
      $score = isset($row->score) ? $row->score : 0;
      $score = [
        'data' => $this->buildScore($score),
      ];

      if ($expired) {
        $row->status = 'expired';
      }
      $status = isset($row->status) ? $row->status : 'pending';
      $status = [
        'data' => $this->buildStatus($status),
      ];

      if ($expired) {
        $row->time = 0;
      }
      $time = $row->time > 0
        ? $this->date_formatter->formatInterval($row->time)
        : '-';

      $details_link = Link::createFromRoute(
        '',
        'opigno_statistics.user.training_details',
        [
          'user' => $row->uid,
          'group' => $group->id(),
        ]
      )->toRenderable();
      $details_link['#attributes']['class'][] = 'details';
      $details_link['#attributes']['data-user'][] = $row->uid;
      $details_link['#attributes']['data-training'][] = $group->id();
      $details_link = [
        'data' => $details_link,
      ];

      $user_link = Link::createFromRoute(
        $row->name,
        'entity.user.canonical',
        [
          'user' => $row->uid,
        ]
      )->toRenderable();
      $user_link['#attributes']['data-user'][] = $row->uid;
      $user_link['#attributes']['data-training'][] = $group->id();
      $user_link = [
        'data' => $user_link,
      ];

      $table['#rows'][] = [
        'class' => 'training',
        'data-training' => $group->id(),
        'data-user' => $row->uid,
        'data' => [
          $user_link,
          $score,
          $status,
          $time,
          $details_link,
        ]
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['users-results'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['users-results-title'],
        ],
        '#value' => $this->t('Users results'),
      ],
      'list' => $table,
    ];
  }

  /**
   * Builds users results for Classes.
   */
  protected function buildUsersResultsClass(GroupInterface $group, $lp_id = NULL) {
    if (!$lp_id) {
      return;
    }

    $members = $group->getMembers();
    $title = Group::load($lp_id)->label();

    foreach ($members as $member) {
      $user = $member->getUser();
      if ($user) {
        $members_ids[$user->id()] = $member->getUser()->id();
      }
    }
    if (empty($members_ids)) {
      $members_ids[] = 0;
    }

    $query = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid', 'name']);
    $query->condition('u.uid', $members_ids, 'IN');
    $query->condition('u.uid', 0, '<>');
    $query->innerJoin('group_content_field_data', 'g_c', 'g_c.entity_id = u.uid');
    $query->condition('g_c.type', ['learning_path-group_membership', 'opigno_course-group_membership'], 'IN');
    $query->condition('g_c.gid', $lp_id);
    $query->leftJoin('opigno_learning_path_achievements', 'a', 'a.gid = g_c.gid AND a.uid = u.uid');
    $query->fields('a', ['status', 'score', 'time', 'gid']);
    $query->orderBy('u.name', 'ASC');
    $statistic = $query->execute()->fetchAll();

    $table = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['statistics-table', 'users-results-list', 'table-striped'],
      ],
      '#header' => [
        $this->t('User'),
        $this->t('Score'),
        $this->t('Passed'),
        $this->t('Time spent'),
        $this->t('Details'),
      ],
      '#rows' => [],
    ];

    foreach ($statistic as $row) {
      $score = isset($row->score) ? $row->score : 0;
      $score = [
        'data' => $this->buildScore($score),
      ];

      $status = isset($row->status) ? $row->status : 'pending';
      $status = [
        'data' => $this->buildStatus($status),
      ];

      $time = $row->time > 0
        ? $this->date_formatter->formatInterval($row->time)
        : '-';

      $details_link = Link::createFromRoute(
        '',
        'opigno_statistics.user',
        [
          'user' => $row->uid,
        ]
      )->toRenderable();
      $details_link['#attributes']['class'][] = 'details';
      $details_link = [
        'data' => $details_link,
      ];

      $table['#rows'][] = [
        $row->name,
        $score,
        $status,
        $time,
        $details_link,
      ];
    }

    // Hide links on detail user pages if user don't have permissions.
    $account = \Drupal::currentUser();
    if (!$account->hasPermission('view module results')) {
      unset($table['#header'][4]);
      foreach ($table['#rows'] as $key => $table_row) {
        unset($table['#rows'][$key][4]);
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['users-results'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => [
          'class' => ['users-results-title'],
        ],
        '#value' => $this->t($title),
      ],
      'list' => $table,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
    $form['#title'] = $this->t('Training statistics - @training', [
      '@training' => $group->label(),
    ]);

    if ($group->bundle() == 'opigno_class') {
      $query_class = $this->database->select('group_content_field_data', 'g_c_f_d')
        ->fields('g_c_f_d', ['gid'])
        ->condition('entity_id', $group->id())
        ->condition('type', 'group_content_type_27efa0097d858')
        ->execute()
        ->fetchAll();

      $lp_ids = [];
      foreach ($query_class as $result_ids) {
        $lp_ids[] = $result_ids->gid;
      }
    }
    else {
      $lp_ids[] = $group->id();
    }

    if (empty($lp_ids)) {
      $lp_ids[] = 0;
    }

    $query = $this->database
      ->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('YEAR(a.registered)', 'year');
    $query->condition('a.gid', $lp_ids, 'IN');
    $data = $query->groupBy('year')
      ->orderBy('year', 'DESC')
      ->execute()
      ->fetchAll();
    $years = ['none' => '- None -'];
    foreach ($data as $row) {
      $year = $row->year;
      if (!isset($years[$year])) {
        $years[$year] = $year;
      }
    }

    $max_year = !empty($years) ? max(array_keys($years)) : NULL;
    $year_select = [
      '#type' => 'select',
      '#options' => $years,
      '#default_value' => 'none',
      '#ajax' => [
        'event' => 'change',
        'callback' => '::submitFormAjax',
        'wrapper' => 'statistics-trainings-progress',
      ],
    ];

    $year_current = $form_state->getValue('year');

    if ($year_current == NULL || $year_current == 'none') {
      $year = $max_year;
    }
    else {
      $year = $year_current;
    }

    $query = $this->database
      ->select('opigno_learning_path_achievements', 'a');
    $query->addExpression('MONTH(a.registered)', 'month');
    $query->addExpression('YEAR(a.registered)', 'year');
    $query->condition('a.gid', $lp_ids, 'IN');
    $data = $query->groupBy('month')
      ->groupBy('year')
      ->orderBy('month')
      ->execute()
      ->fetchAll();
    $months = ['none' => '- None -'];
    foreach ($data as $row) {
      $month = $row->month;
      if (!isset($months[$month]) && $row->year == $year) {
        $timestamp = mktime(0, 0, 0, $month, 1);
        $months[$month] = $this->date_formatter
          ->format($timestamp, 'custom', 'F');
      }
    }
    $max_month = !empty($months) ? max(array_keys($months)) : NULL;
    $month_select = [
      '#type' => 'select',
      '#options' => $months,
      '#default_value' => 'none',
      '#ajax' => [
        'event' => 'change',
        'callback' => '::submitFormAjax',
        'wrapper' => 'statistics-trainings-progress',
      ],
    ];
    $month = $form_state->getValue('month', $max_month);

    if ($month == 'none' || $year_current == NULL || $year_current == 'none') {
      $month = $max_month;
    }

    $timestamp = mktime(0, 0, 0, $month, 1, $year);
    $datetime = DrupalDateTime::createFromTimestamp($timestamp);
    $datetime->add(new \DateInterval('P1M'));

    // Get users with the training expired certification.
    $expired_uids = $this->getExpiredUsers($group);

    $form['trainings_progress'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'statistics-trainings-progress',
      ],
      'year' => $year_select,
      'month' => $month_select,
    ];

    if ($year_current != NULL && $year_current != 'none') {
      $form['trainings_progress']['month'] = $month_select;
    }

    $form['trainings_progress']['trainings_progress'] = $this->buildTrainingsProgress($group, $datetime, $expired_uids);

    if ($group->bundle() == 'opigno_class') {
      $form[] = [
        '#type' => 'container',
        'users' => $this->buildUserMetrics($group),
      ];

      foreach ($lp_ids as $lp_id) {
        $form[] = [
          'training_class_results_' . $lp_id => $this->buildUsersResultsClass($group, $lp_id),
        ];
      }
    }
    else {
      $form[] = [
        '#type' => 'container',
        'users' => $this->buildUserMetrics($group),
        'training_content' => $this->buildTrainingContent($group, $expired_uids),
        'user_results' => $this->buildUsersResultsLp($group, $expired_uids),
      ];
    }

    $form['#attached']['library'][] = 'opigno_statistics/training';
    $form['#attached']['library'][] = 'opigno_statistics/user';

    return $form;
  }

  /**
   * Ajax form submit.
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#name']) && $trigger['#name'] == 'year') {
      $form['trainings_progress']['month']['#value'] = 'none';
    }

    return $form['trainings_progress'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
