<?php

namespace Drupal\opigno_learning_path;

use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class JoinService.
 */
class Progress {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The database layer.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The RequestStack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request_stack;

  /**
   * Constructs a new Progress object.
   */
  public function __construct(AccountInterface $current_user, $database, RequestStack $request_stack) {
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->requestStack = $request_stack;
  }

  /**
   * Calculates progress in a group for a user.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Latest certification date.
   *
   * @return float
   *   Attempted activities count / total activities count.
   */
  public function getProgress($group_id, $account_id, $latest_cert_date) {
    $activities = opigno_learning_path_get_activities($group_id, $account_id, $latest_cert_date);

    $total = count($activities);
    $attempted = count(array_filter($activities, function ($activity) {
      return $activity['answers'] > 0;
    }));

    return $total > 0 ? $attempted / $total : 0;
  }

  /**
   * Get round integer of progress.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Latest certification date.
   *
   * @return integer
   *   Attempted activities count / total activities count.
   */
  public function getProgressRound($group_id, $account_id, $latest_cert_date = '') {

    if (!$latest_cert_date) {
      $group = Group::load($group_id);
      $latest_cert_date = LPStatus::getTrainingStartDate($group, $account_id);
    }

    return round(100 * $this->getProgress($group_id, $account_id, $latest_cert_date));
  }

  /**
   * Get html container where progress will be loaded via ajax.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Latest certification date.
   * @param string $class
   *   identifier for progress bar.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressAjaxContainer($group_id, $account_id, $latest_cert_date = '', $class = 'basic') {

    if (!$latest_cert_date) {
      $group = Group::load($group_id);
      $latest_cert_date = LPStatus::getTrainingStartDate($group, $account_id);
    }

    // If latest_cert_date is empty we just set 0 to avoid any errors for empty args.
    if (!$latest_cert_date) {
      $latest_cert_date = 0;
    }

    // Maybe in some cases we need to have pre-loaded progress bar without ajax.
    // An example unit tests or so.
    $preload = $this->requestStack->getCurrentRequest()->query->get('preload-progress');
    if ($preload) {
      return $this->getProgressBuild($group_id, $account_id, $latest_cert_date, $class);
    }

    // HTML structure for ajax comntainer.
    return [
      '#theme' => 'opigno_learning_path_progress_ajax_container',
      '#group_id' => $group_id,
      '#account_id' => $account_id,
      '#latest_cert_date' => $latest_cert_date,
      '#class' => $class,
      '#attached' => ['library' => ['opigno_learning_path/progress']],
    ];
  }

  /**
   * Get get progress bar it self.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Latest certification date.
   * @param string $class
   *   identifier for progress bar.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressBuild($group_id, $account_id, $latest_cert_date, $class) {

    // If $latest_cert_date argument is 0 than it means it empty;
    if ($latest_cert_date === 0) {
      $latest_cert_date = '';
    }

    // Progress should be shown only for member of group.
    $group = Group::load($group_id);
    $account = User::load($account_id);
    $existing = $group->getMember($account);
    if ($existing === FALSE) {
      $class = 'empty';
    }

    switch ($class) {
      case 'group-page':
        return $this->getProgressBuildGroupPage($group_id, $account_id, $latest_cert_date);

      case 'module-page':
        return $this->getProgressBuildModulePage($group_id, $account_id, $latest_cert_date);

      case 'achievements-page':
        return $this->getProgressBuildAchievementsPage($group_id, $account_id, $latest_cert_date);

      case 'full':
        // Full: label, value, bar.
        return [
          '#theme' => 'opigno_learning_path_progress',
          '#label' => $this->t('Some title'),
          '#class' => $class,
          '#value' => $this->getProgressRound($group_id, $account_id, $latest_cert_date),
        ];

      case 'mini':
        // Mini: value, bar.
        return [
          '#theme' => 'opigno_learning_path_progress',
          '#class' => $class,
          '#value' => $this->getProgressRound($group_id, $account_id, $latest_cert_date),
        ];

      case 'empty':
        // Empty progress.
        return [
           '#markup' => '',
        ];

      default:
        // Only value.
        return $this->getProgressRound($group_id, $account_id, $latest_cert_date);
    }
  }

  /**
   * Get get progress for group page.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Latest certification date.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressBuildGroupPage($group_id, $account_id, $latest_cert_date) {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = Group::load($group_id);
    $account = User::load($account_id);


    $date_formatter = \Drupal::service('date.formatter');

    $expiration_message = '';
    $expiration_set = LPStatus::isCertificateExpireSet($group);
    if ($expiration_set) {
      if ($expiration_message = LPStatus::getCertificateExpireTimestamp($group->id(), $account_id)) {
        $expiration_message = ' ' . $date_formatter->format($expiration_message, 'custom', 'F d, Y');
      }
    }

    // If training certification not expired
    // or expiration not set.
    $progress = $this->getProgressRound($group_id, $account_id, $latest_cert_date);

    $is_passed = opigno_learning_path_is_passed($group, $account_id);
    if ($is_passed || $progress == 100) {
      $score = opigno_learning_path_get_score($group_id, $account_id);

      $completed = opigno_learning_path_completed_on($group_id, $account_id);
      $completed = $completed > 0
        ? $date_formatter->format($completed, 'custom', 'F d, Y')
        : '';

      $state = $is_passed ? $this->t('Passed') : $this->t('Failed');
      // Expire message if necessary.
      if ($expiration_set) {
        // Expiration set, create expiration message.
        if ($expiration_message) {
          $expiration_message = ' - ' . $this->t('Valid until') . $expiration_message;
        }
      }

      $summary = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_progress_summary'],
        ],
        // H2 Need for correct structure.
        [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Progress status'),
          '#attributes' => [
            'class' => ['sr-only']
          ]
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => $is_passed ? ['lp_progress_summary_passed'] : ['lp_progress_summary_failed'],
          ],
          '#value' => '',
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => [
            'class' => ['lp_progress_summary_title'],
          ],
          '#value' => $state . $expiration_message,
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_summary_score'],
          ],
          '#value' => $this->t('Average score : @score%', [
            '@score' => $score,
          ]),
        ],
        !empty($completed) ? [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_summary_date'],
          ],
          '#value' => $this->t('Completed on @date', [
            '@date' => $completed,
          ]),
        ] : [],
      ];
    }
    elseif ($expiration_set && LPStatus::isCertificateExpired($group, $account_id)) {
      $summary = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_progress_summary'],
        ],
        // H2 Need for correct structure.
        [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Progress status'),
          '#attributes' => [
            'class' => ['sr-only']
          ]
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_summary_expired'],
          ],
          '#value' => '',
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#attributes' => [
            'class' => ['lp_progress_summary_title'],
          ],
          '#value' => $this->t('Expired on') . ' ' . $expiration_message,
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_summary_score'],
          ],
          '#value' => $this->t('Please start this training again to get new certification'),
        ],
      ];
    }

    $content = [];
    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-sm-9', 'mb-3'],
      ],
      [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_progress'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_label'],
          ],
          '#value' => $this->t('Global Training Progress'),
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['lp_progress_value'],
          ],
          '#value' => $progress . '%',
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lp_progress_bar'],
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => ['lp_progress_bar_completed'],
              'style' => "width: $progress%",
            ],
            '#value' => '',
          ],
        ],
      ],
      isset($summary) ? $summary : [],
      '#attached' => [
        'library' => [
          'opigno_learning_path/training_content',
          'core/drupal.dialog.ajax',
        ],
      ],
    ];

    $continue_route = 'opigno_learning_path.steps.start';
    $edit_route = 'entity.group.edit_form';
    $members_route = 'opigno_learning_path.membership.overview';

    $route_args = ['group' => $group->id()];
    $continue_url = Url::fromRoute($continue_route, $route_args);
    $edit_url = Url::fromRoute($edit_route, $route_args);
    $members_url = Url::fromRoute($members_route, $route_args);

    $admin_continue_button = Link::fromTextAndUrl(
      Markup::create('<i class="icon-chevron-right1"></i><span class="sr-only">' . $this->t('Continue training') . '</span>'),
      $continue_url)->toRenderable();
    $admin_continue_button['#attributes']['class'][] = 'lp_progress_admin_continue';
    $admin_continue_button['#attributes']['class'][] = 'use-ajax';
    $admin_continue_button['#attributes']['class'][] = 'lp_progress_control';
    $edit_button = Link::fromTextAndUrl(
      Markup::create('<i class="icon-pencil"></i><span class="sr-only">' . $this->t('Edit training') . '</span>'),
      $edit_url)->toRenderable();
    $edit_button['#attributes']['class'][] = 'lp_progress_admin_edit';
    $edit_button['#attributes']['class'][] = 'lp_progress_control';
    $members_button = Link::fromTextAndUrl(Markup::create('<i class="icon-pencil"></i><span class="sr-only">' . $this->t('Member of training') . '</span>'), $members_url)->toRenderable();
    $members_button['#attributes']['class'][] = 'lp_progress_admin_edit';
    $members_button['#attributes']['class'][] = 'lp_progress_control';

    $continue_button_text = $this->t('Continue Training');
    $continue_button = Link::fromTextAndUrl($continue_button_text, $continue_url)->toRenderable();
    $continue_button['#attributes']['class'][] = 'lp_progress_continue';
    $continue_button['#attributes']['class'][] = 'use-ajax';

    $buttons = [];
    if ($group->access('update', $account)) {
      $buttons[] = $admin_continue_button;
      $buttons[] = $edit_button;
    }
    elseif ($group->access('administer members', $account)) {
      $buttons[] = $admin_continue_button;
      $buttons[] = $members_button;
    }
    else {
      $buttons[] = $continue_button;
    }

    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-sm-3', 'mb-3', 'd-flex'],
      ],
      $buttons,
    ];

    return $content;
  }

  /**
   * Get get progress for module page.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Latest certification date.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressBuildModulePage($group_id, $account_id, $latest_cert_date) {
    $home_link = Link::createFromRoute(Markup::create($this->t('home') . '<i class="icon-home-2"></i>'), 'entity.group.canonical', ['group' => $group_id], ['attributes' => ['class' => ['w-100']]])->toRenderable();
    $home_link = render($home_link);

    $progress = $this->getProgressRound($group_id, $account_id);

    $build = [
      '#theme' => 'block__opigno_module_learning_path_progress_block',
      'content' => [
        'home_link' => $home_link,
        'progress' => $progress,
       ],
      '#configuration' => [
        'id' => 'opigno_module_learning_path_progress_block',
        'label' => 'Learning path progress',
        'provider' => 'opigno_module',
        'label_display' => '0'
      ],
      '#plugin_id' => 'opigno_module_learning_path_progress_block',
      '#base_plugin_id' => 'opigno_module_learning_path_progress_block',
      '#derivative_plugin_id' => NULL
    ];

    return $build;
  }

  /**
   * Get get progress for achievements page.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Latest certification date.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressBuildAchievementsPage($group_id, $account_id, $latest_cert_date) {

    $group = Group::load($group_id);
    $account = User::load($account_id);

    $progress = $this->getProgressRound($group_id, $account_id, $latest_cert_date);

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');

    /** @var \Drupal\group\Entity\GroupContent $member */
    $member = $group->getMember($account)->getGroupContent();
    $registration = $member->getCreatedTime();
    $registration = $date_formatter->format($registration, 'custom', 'F d, Y');

    $validation = opigno_learning_path_completed_on($group_id, $account_id, TRUE);
    $validation = $validation > 0
      ? $date_formatter->format($validation, 'custom', 'F d, Y')
      : '';

    $time_spent = opigno_learning_path_get_time_spent($group_id, $account_id);
    $time_spent = $date_formatter->formatInterval($time_spent);

    $result = FALSE;
    $expiration_message = '';
    $expiration_set = LPStatus::isCertificateExpireSet($group);
    $expired = FALSE;
    if ($expiration_set) {
      if ($expiration_timestamp = LPStatus::getCertificateExpireTimestamp($group->id(), $account_id)) {
        if (!LPStatus::isCertificateExpired($group, $account_id)) {
          $expiration_message = $this->t('Valid until');
        }
        else {
          $expired = TRUE;
          $expiration_message = $this->t('Expired on');
        }

        $expiration_message = $expiration_message . ' ' . $date_formatter->format($expiration_timestamp, 'custom', 'F d, Y');
      }
    }
    else {
      $result = $this->database
        ->select('opigno_learning_path_achievements', 'a')
        ->fields('a', ['status'])
        ->condition('uid', $account->id())
        ->condition('gid', $group->id())
        ->execute()
        ->fetchField();
    }

    if ($result !== FALSE) {
      // Use cached result.
      $is_attempted = TRUE;
      $is_passed = $result === 'completed';
    }
    else {
      // Check the actual data.
      $is_attempted = opigno_learning_path_is_attempted($group, $account_id);
      $is_passed = opigno_learning_path_is_passed($group, $account_id);
    }

    if ($is_passed) {
      $state_class = 'lp_summary_step_state_passed';
    }
    elseif ($progress == 100 && !opigno_learning_path_is_passed($group, $account_id)) {
      $state_class = 'lp_summary_step_state_failed';
    }
    elseif ($is_attempted) {
      $state_class = 'lp_summary_step_state_in_progress';
    }
    elseif ($expired) {
      $state_class = 'lp_summary_step_state_expired';
    }
    else {
      $state_class = 'lp_summary_step_state_not_started';
    }

    $validation_message = !empty($validation) ? t('Validation date: @date<br />', ['@date' => $validation]) : '';

    $has_certificate = !$group->get('field_certificate')->isEmpty();

    return [
      '#theme' => 'opigno_learning_path_training_summary',
      '#progress' => $progress,
      '#score' => round(opigno_learning_path_get_score($group_id, $account_id, FALSE, $latest_cert_date)),
      '#group_id' => $group_id,
      '#has_certificate' => $has_certificate,
      '#is_passed' => $is_passed,
      '#state_class' => $state_class,
      '#registration_date' => $registration,
      '#validation_message' => $validation_message . $expiration_message,
      '#time_spend' => $time_spent,
      '#certificate_url' => $has_certificate && $is_passed ?
        Url::fromUri('internal:/certificate/group/' . $group_id . '/pdf') : FALSE,
    ];
  }
}
