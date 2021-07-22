<?php

namespace Drupal\opigno_learning_path\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\Group;
use Drupal\opigno_group_manager\ContentTypeBase;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\OpignoGroupContentTypesManager;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\Core\Cache\Cache;
use Drupal\opigno_ilt\ILTInterface;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_learning_path\LearningPathContent;
use Drupal\opigno_learning_path\Progress;
use Drupal\opigno_moxtra\MeetingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'article' block.
 *
 * @Block(
 *   id = "lp_steps_block",
 *   admin_label = @Translation("LP Steps block")
 * )
 */
class StepsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var AccountProxyInterface
   */
  protected $account;

  /**
   * @var ResettableStackedRouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var Progress
   */
  protected $progress;

  /**
   * @var OpignoGroupContentTypesManager
   */
  protected $opignoGroupContentTypesManager;

  /**
   * StepsBlock constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param AccountProxyInterface $account
   * @param ResettableStackedRouteMatchInterface $route_match
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param Progress $progress
   * @param OpignoGroupContentTypesManager $opigno_group_content_types_manager
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountProxyInterface $account,
    ResettableStackedRouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager,
    Progress $progress,
    OpignoGroupContentTypesManager $opigno_group_content_types_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->progress = $progress;
    $this->opignoGroupContentTypesManager = $opigno_group_content_types_manager;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return StepsBlock
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('entity.manager'),
      $container->get('opigno_learning_path.progress'),
      $container->get('opigno_group_manager.content_types.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $uid = $this->account->id();
    $route_name = $this->routeMatch->getRouteName();
    if ($route_name == 'opigno_module.group.answer_form') {
      $group = $this->routeMatch->getParameter('group');
      $gid = $group->id();
    }
    else {
      $gid = OpignoGroupContext::getCurrentGroupId();
      $group = Group::load($gid);
    }

    if (empty($group)) {
      return [];
    }

    $title = $group->label();

    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);

    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid, TRUE);
    }
    else {
      // Get guided steps.
      $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid);
    }

    $user = $this->account;
    $steps = array_filter($steps, function ($step) use ($user) {
      if ($step['typology'] === 'Meeting') {
        // If the user have not the collaborative features role.
        if (!$user->hasPermission('view meeting entities')) {
          return FALSE;
        }

        // If the user is not a member of the meeting.
        /** @var MeetingInterface $meeting */
        $meeting = $this->entityTypeManager
          ->getStorage('opigno_moxtra_meeting')
          ->load($step['id']);
        if (!$meeting->isMember($user->id())) {
          return FALSE;
        }
      }
      elseif ($step['typology'] === 'ILT') {
        // If the user is not a member of the ILT.
        /** @var ILTInterface $ilt */
        $ilt = $this->entityTypeManager
          ->getStorage('opigno_ilt')
          ->load($step['id']);
        if (!$ilt->isMember($user->id())) {
          return FALSE;
        }
      }

      return TRUE;
    });

    // Get user training expiration flag.
    $expired = LPStatus::isCertificateExpired($group, $uid);

    $score = opigno_learning_path_get_score($gid, $uid);
    $progress = $this->progress->getProgressRound($gid, $uid);

    $is_passed = opigno_learning_path_is_passed($group, $uid, $expired);

    if ($is_passed) {
      $state_class = 'lp_steps_block_summary_state_passed';
      $state_title = $this->t('Passed');
    }
    else {
      $state_class = 'lp_steps_block_summary_state_pending';
      $state_title = $this->t('In progress');
    }
    // Get group context.
    $cid = OpignoGroupContext::getCurrentGroupContentId();
    if (!$cid) {
      return [];
    }
    $gid = OpignoGroupContext::getCurrentGroupId();
    $step_info = [];
    // Reindex steps array.
    $steps = array_values($steps);
    for ($i = 0; $i < count($steps); $i++) {
      // Build link for first step.
      if ($i == 0) {
        // Load first step entity.
        $first_step = OpignoGroupManagedContent::load($steps[$i]['cid']);
        if ($first_step) {
          /** @var ContentTypeBase $content_type */
          $content_type = $this->opignoGroupContentTypesManager->createInstance($first_step->getGroupContentTypeId());
          $step_url = $content_type->getStartContentUrl($first_step->getEntityId(), $gid);
          $link = Link::createFromRoute($steps[$i]['name'], $step_url->getRouteName(), $step_url->getRouteParameters())
            ->toString();
        }
        else {
          $link = '-';
        }
      }
      else {
        // Get link to module.
        $parent_content_id = $steps[$i - 1]['cid'];
        $link = Link::createFromRoute($steps[$i]['name'], 'opigno_learning_path.steps.next', [
          'group' => $gid,
          'parent_content' => $parent_content_id,
        ])
          ->toString();
      }

      array_push($step_info, [
        'name' => $link,
        'score' => $this->buildScore($steps[$i]),
        'state' => $this->buildState($steps[$i]),
      ]);

    }

    $state_summary = [
      'class' => $state_class,
      'title' => $state_title,
      'score' => $this->t('Average score : @score%', ['@score' => $score]),
      'progress' => $this->t('Progress : @progress%', ['@progress' => $progress]),
    ];

    $table_summary = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Score'),
        $this->t('State'),
      ],
      '#rows' => $step_info,
      '#attributes' => [
        'class' => ['lp_steps_block_table'],
      ],
    ];

    $build = [
      '#theme' => 'opigno_learning_path_step_block',
      '#attributes' => [
        'class' => ['lp_steps_block'],
      ],
      '#attached' => [
        'library' => [
          'opigno_learning_path/steps_block',
        ],
      ],
      '#title' => $title,
      '#state_summary' => $state_summary,
      '#table_summary' => $table_summary,
    ];

    return $build;
  }

  /**
   * Builds the score.
   *
   * @param array $step
   *
   * @return mixed|null
   */
  protected function buildScore(array $step) {
    $is_attempted = $step['attempts'] > 0;

    if ($is_attempted) {
      $score = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $step['best score'],
        '#attributes' => [
          'class' => ['lp_steps_block_score'],
        ],
      ];
    }
    else {
      $score = ['#markup' => '&dash;'];
    }

    return [
      'data' => $score,
    ];
  }

  /**
   * Builds the state.
   *
   * @param array $step
   *
   * @return string|null
   */
  protected function buildState(array $step) {
    $uid = \Drupal::currentUser()->id();
    $status = opigno_learning_path_get_step_status($step, $uid, TRUE);
    $class = [
      'pending' => 'lp_steps_block_step_pending',
      'failed' => 'lp_steps_block_step_failed',
      'passed' => 'lp_steps_block_step_passed',
    ];

    if (isset($class[$status])) {
      return [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => [$class[$status]]],
        ],
      ];
    }
    else {
      return ['data' => ['#markup' => '&dash;']];
    }
  }

}
