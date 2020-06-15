<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_learning_path\LearningPathAccess;
use Drupal\opigno_module\Entity\OpignoModule;

/**
 * Class LearningPathController.
 */
class LearningPathController extends ControllerBase {

  /**
   * Returns step score cell.
   */
  protected function build_step_score_cell($step) {
    if (in_array($step['typology'], ['Module', 'Course', 'Meeting', 'ILT'])) {
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
    else {
      return ['#markup' => '&dash;'];
    }
  }

  /**
   * Returns step state cell.
   */
  protected function build_step_state_cell($step) {
    $user = $this->currentUser();
    $uid = $user->id();

    $status = opigno_learning_path_get_step_status($step, $uid, TRUE);
    switch ($status) {
      case 'pending':
        $markup = '<span class="lp_step_state_pending"></span>' . $this->t('Pending');
        break;

      case 'failed':
        $markup = '<span class="lp_step_state_failed"></span>' . $this->t('Failed');
        break;

      case 'passed':
        $markup = '<span class="lp_step_state_passed"></span>' . $this->t('Passed');
        break;

      default:
        $markup = '&dash;';
        break;
    }

    return ['#markup' => $markup];
  }

  /**
   * Returns course row.
   */
  protected function build_course_row($step) {
    $result = $this->build_step_score_cell($step);
    $state = $this->build_step_state_cell($step);

    return [
      $step['name'],
      [
        'class' => 'lp_step_details_result',
        'data' => $result,
      ],
      [
        'class' => 'lp_step_details_state',
        'data' => $state,
      ],
    ];
  }

  /**
   * Returns progress.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function progress() {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = \Drupal::routeMatch()->getParameter('group');
    $user = \Drupal::currentUser();

    $id = $group->id();
    $uid = $user->id();

    $date_formatter = \Drupal::service('date.formatter');

    $expiration_message = '';
    $expiration_set = LPStatus::isCertificateExpireSet($group);
    if ($expiration_set) {
      if ($expiration_message = LPStatus::getCertificateExpireTimestamp($group->id(), $uid)) {
        $expiration_message = ' ' . $date_formatter->format($expiration_message, 'custom', 'F d, Y');
      }
    }

    $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);

    // If training certification not expired
    // or expiration not set.
    $progress = opigno_learning_path_progress($id, $uid, $latest_cert_date);
    $progress = round(100 * $progress);

    $is_passed = opigno_learning_path_is_passed($group, $uid);
    if ($is_passed || $progress == 100) {
      $score = opigno_learning_path_get_score($id, $uid);

      $completed = opigno_learning_path_completed_on($id, $uid);
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
    elseif ($expiration_set && LPStatus::isCertificateExpired($group, $uid)) {
      $summary = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['lp_progress_summary'],
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

    $admin_continue_button = Link::fromTextAndUrl('', $continue_url)->toRenderable();
    $admin_continue_button['#attributes']['class'][] = 'lp_progress_admin_continue';
    $admin_continue_button['#attributes']['class'][] = 'use-ajax';
    $edit_button = Link::fromTextAndUrl('', $edit_url)->toRenderable();
    $edit_button['#attributes']['class'][] = 'lp_progress_admin_edit';
    $members_button = Link::fromTextAndUrl('', $members_url)->toRenderable();
    $members_button['#attributes']['class'][] = 'lp_progress_admin_edit';

    $continue_button_text = $this->t('Continue Training');
    $continue_button = Link::fromTextAndUrl($continue_button_text, $continue_url)->toRenderable();
    $continue_button['#attributes']['class'][] = 'lp_progress_continue';
    $continue_button['#attributes']['class'][] = 'use-ajax';

    $buttons = [];
    if ($group->access('update', $user)) {
      $buttons[] = $admin_continue_button;
      $buttons[] = $edit_button;
    }
    elseif ($group->access('administer members', $user)) {
      $buttons[] = $admin_continue_button;
      $buttons[] = $members_button;
    }
    else {
      $buttons[] = $continue_button;
    }

    $content[] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-sm-3', 'mb-3'],
      ],
      $buttons,
    ];

    return $content;
  }

  /**
   * Returns training content.
   */
  public function trainingContent() {
    /** @var \Drupal\group\Entity\Group $group */
    $group = \Drupal::routeMatch()->getParameter('group');
    $user = \Drupal::currentUser();

    // Get training certificate expiration flag.
    $latest_cert_date = LPStatus::getTrainingStartDate($group, $user->id());

    $content = [
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
      '#theme' => 'opigno_learning_path_training_content',
    ];

    // If not a member.
    if (!$group->getMember($user)
      || (!$user->isAuthenticated() && $group->field_learning_path_visibility->value === 'semiprivate')) {
      return $content;
    }

    // Check if membership has status 'pending'.
    if (!LearningPathAccess::statusGroupValidation($group, $user)) {
      return $content;
    }

    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);

    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = opigno_learning_path_get_all_steps($group->id(), $user->id(), NULL, $latest_cert_date);
    }
    else {
      // Get guided steps.
      $steps = opigno_learning_path_get_steps($group->id(), $user->id(), NULL, $latest_cert_date);
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

    $steps_array = [];
    if ($steps) {
      foreach ($steps as $key => $step) {
        $sub_title = '';
        $score = $this->build_step_score_cell($step);
        $state = $this->build_step_state_cell($step);
        unset($start_date);
        unset($end_date);

        if ($step['typology'] === 'Course') {
          if ($freeNavigation) {
            // Get all steps for LP.
            $course_steps = opigno_learning_path_get_all_steps($step['id'], $user->id(), NULL, $latest_cert_date);
          }
          else {
            // Get guided steps.
            $course_steps = opigno_learning_path_get_steps($step['id'], $user->id(), NULL, $latest_cert_date);
          }

          foreach ($course_steps as $course_step_key => &$course_step) {
            if ($course_step_key == 0) {
              // Load first step entity.
              $first_step = OpignoGroupManagedContent::load($course_steps[$course_step_key]['cid']);
              /* @var \Drupal\opigno_group_manager\OpignoGroupContentTypesManager $content_types_manager */
              $content_types_manager = \Drupal::service('opigno_group_manager.content_types.manager');
              $content_type = $content_types_manager->createInstance($first_step->getGroupContentTypeId());
              $step_url = $content_type->getStartContentUrl($first_step->getEntityId(), $group->id());
              $link = Link::createFromRoute($course_step['name'], $step_url->getRouteName(), $step_url->getRouteParameters())
                ->toString();
            }
            else {
              // Get link to module.
              $parent_content_id = $course_steps[$course_step_key - 1]['cid'];
              $link = Link::createFromRoute($course_step['name'], 'opigno_learning_path.steps.next', [
                'group' => $group->id(),
                'parent_content' => $parent_content_id,
              ])
                ->toString();
            }

            // Add compiled parameters to step array.
            $course_step['title'] = !empty($link) ? $link : $course_step['name'];

            $course_step['summary_details_table'] = [
              '#type' => 'table',
              '#attributes' => [
                'class' => ['lp_step_summary_details'],
              ],
              '#header' => [
                $this->t('Score'),
                $this->t('State'),
              ],
              '#rows' => [
                [
                  [
                    'class' => 'lp_step_details_result',
                    'data' => $this->build_step_score_cell($course_step),
                  ],
                  [
                    'class' => 'lp_step_details_state',
                    'data' => $this->build_step_state_cell($course_step),
                  ],
                ],
              ],
            ];
          }

          $step['course_steps'] = $course_steps;
        }
        elseif ($step['typology'] === 'Module') {
          $step['module'] = OpignoModule::load($step['id']);
        }

        $title = $step['name'];

        if ($step['typology'] === 'Meeting') {
          /** @var \Drupal\opigno_moxtra\MeetingInterface $meeting */
          $meeting = $this->entityTypeManager()
            ->getStorage('opigno_moxtra_meeting')
            ->load($step['id']);
          $start_date = $meeting->getStartDate();
          $end_date = $meeting->getEndDate();
        }
        elseif ($step['typology'] === 'ILT') {
          /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
          $ilt = $this->entityTypeManager()
            ->getStorage('opigno_ilt')
            ->load($step['id']);
          $start_date = $ilt->getStartDate();
          $end_date = $ilt->getEndDate();
        }

        if (isset($start_date) && isset($end_date)) {
          $start_date = DrupalDateTime::createFromFormat(
            DrupalDateTime::FORMAT,
            $start_date
          );
          $end_date = DrupalDateTime::createFromFormat(
            DrupalDateTime::FORMAT,
            $end_date
          );

          $title .= ' / ' . $this->t('@start to @end', [
            '@start' => $start_date->format('jS F Y - g:i A'),
            '@end' => $end_date->format('g:i A'),
          ]);
        }

        $keys = array_keys($steps);

        // Build link for first step.
        if ($key == $keys[0]) {
          if ($step['typology'] == 'Course') {
            $link = NULL;
          }
          else {
            // Load first step entity.
            $first_step = OpignoGroupManagedContent::load($steps[$key]['cid']);
            /* @var \Drupal\opigno_group_manager\OpignoGroupContentTypesManager $content_types_manager */
            $content_types_manager = \Drupal::service('opigno_group_manager.content_types.manager');
            $content_type = $content_types_manager->createInstance($first_step->getGroupContentTypeId());
            $step_url = $content_type->getStartContentUrl($first_step->getEntityId(), $group->id());
            $link = Link::createFromRoute($title, $step_url->getRouteName(), $step_url->getRouteParameters())
              ->toString();
          }
        }
        else {
          if ($step['typology'] == 'Course') {
            $link = NULL;
          }
          else {
            // Get link to module.
            if (!empty($steps[$key - 1]['cid'])) {
              $parent_content_id = $steps[$key - 1]['cid'];
              $link = Link::createFromRoute($title, 'opigno_learning_path.steps.next', [
                'group' => $group->id(),
                'parent_content' => $parent_content_id,
              ])
                ->toString();
            }
          }
        }

        // Add compiled parameters to step array.
        $step['title'] = !empty($link) ? $link : $title;
        $step['sub_title'] = $sub_title;
        $step['score'] = $score;
        $step['state'] = $state;

        $step['summary_details_table'] = [
          '#type' => 'table',
          '#attributes' => [
            'class' => ['lp_step_summary_details'],
          ],
          '#header' => [
            $this->t('Score'),
            $this->t('State'),
          ],
          '#rows' => [
            [
              [
                'class' => 'lp_step_details_result',
                'data' => $score,
              ],
              [
                'class' => 'lp_step_details_state',
                'data' => $state,
              ],
            ],
          ],
        ];

        $steps_array[] = [
          '#theme' => 'opigno_learning_path_training_content_step',
          '#step' => $step,
          '#group' => $group,
        ];
      }

      if ($steps_array) {
        $steps = $steps_array;
      }
    }

    // $TFTController = new TFTController();
    // $listGroup = $TFTController->listGroup($group->id());
    $tft_url = Url::fromRoute('tft.group', ['group' => $group->id()])->toString();

    $content['tabs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['lp_tabs', 'nav', 'mb-4']],
    ];

    $content['tabs'][] = [
      '#markup' => '<a class="lp_tabs_link active" data-toggle="tab" href="#training-content">' . $this->t('Training Content') . '</a>',
    ];

    $content['tabs'][] = [
      '#markup' => '<a class="lp_tabs_link" data-toggle="tab" href="#documents-library">' . $this->t('Documents Library') . '</a>',
    ];

    $content['tab_content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['tab-content']],
    ];

    $content['tab_content'][] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'training-content',
        'class' => ['tab-pane', 'fade', 'show', 'active'],
      ],
      'steps' => $steps,
    ];

    $content['tab_content'][] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'documents-library',
        'class' => ['tab-pane', 'fade'],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => [
          'src' => $tft_url,
          'frameborder' => 0,
          'width' => '100%',
          'height' => '600px',
        ],
      ],
    ];

    $is_moxtra_enabled = \Drupal::hasService('opigno_moxtra.workspace_controller');
    if ($is_moxtra_enabled) {
      $has_workspace_field = $group->hasField('field_workspace');
      $has_workspace_access = $user->hasPermission('view workspace entities');
      if ($has_workspace_field && $has_workspace_access) {
        if ($group->get('field_workspace')->getValue() &&
          $workspace_id = $group->get('field_workspace')->getValue()[0]['target_id']
        ) {
          $workspace_url = Url::fromRoute('opigno_moxtra.workspace.iframe', ['opigno_moxtra_workspace' => $workspace_id])->toString();

          $content['tabs'][] = [
            '#markup' => '<a class="lp_tabs_link" data-toggle="tab" href="#collaborative-workspace">' . $this->t('Collaborative Workspace') . '</a>',
          ];
        }

        $workspace_tab = [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'collaborative-workspace',
            'class' => ['tab-pane', 'fade'],
          ],
          'content' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['row'],
            ],
            (isset($workspace_url)) ? [
              '#type' => 'html_tag',
              '#tag' => 'iframe',
              '#attributes' => [
                'src' => $workspace_url,
                'frameborder' => 0,
                'width' => '100%',
                'height' => '600px',
              ],
            ] : [],
          ],
        ];

        $content['tab_content'][] = $workspace_tab;
      }
    }

    $has_enable_forum_field = $group->hasField('field_learning_path_enable_forum');
    $has_forum_field = $group->hasField('field_learning_path_forum');
    if ($has_enable_forum_field && $has_forum_field) {
      $enable_forum_field = $group->get('field_learning_path_enable_forum')->getValue();
      $forum_field = $group->get('field_learning_path_forum')->getValue();
      if (!empty($enable_forum_field) && !empty($forum_field)) {
        $enable_forum = $enable_forum_field[0]['value'];
        $forum_tid = $forum_field[0]['target_id'];
        if ($enable_forum && _opigno_forum_access($forum_tid, $user)) {
          $forum_url = Url::fromRoute('forum.page', ['taxonomy_term' => $forum_tid])->toString();
          $content['tabs'][] = [
            '#markup' => '<a class="lp_tabs_link" data-toggle="tab" href="#forum">' . $this->t('Forum') . '</a>',
          ];

          $content['tab_content'][] = [
            '#type' => 'container',
            '#attributes' => [
              'id' => 'forum',
              'class' => ['tab-pane', 'fade'],
            ],
            [
              '#type' => 'html_tag',
              '#tag' => 'iframe',
              '#attributes' => [
                'src' => $forum_url,
                'frameborder' => 0,
                'width' => '100%',
                'height' => '600px',
              ],
            ],
          ];
        }
      }
    }

    $content['#attached']['library'][] = 'opigno_learning_path/training_content';

    return $content;
  }

}
