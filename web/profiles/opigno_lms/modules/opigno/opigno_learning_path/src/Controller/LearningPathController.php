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
use Drupal\opigno_learning_path\LearningPathContent;
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
    $steps = array_values($steps);
    if ($steps) {
      foreach ($steps as $key => $step) {
        $sub_title = '';
        $link = NULL;
        $free_link = NULL;
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
              $course_parent_content_id = $course_steps[$course_step_key - 1]['cid'];
              $link = Link::createFromRoute($course_step['name'], 'opigno_learning_path.steps.next', [
                'group' => $group->id(),
                'parent_content' => $course_parent_content_id,
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
          $steps[$key]['course_steps'] = $course_steps;
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
          if ($freeNavigation) {
            $free_link = Link::createFromRoute($title, 'opigno_moxtra.meeting', [
              'opigno_moxtra_meeting' => $step['id'],
            ])
              ->toString();
          }
        }
        elseif ($step['typology'] === 'ILT') {
          /** @var \Drupal\opigno_ilt\ILTInterface $ilt */
          $ilt = $this->entityTypeManager()
            ->getStorage('opigno_ilt')
            ->load($step['id']);
          $start_date = $ilt->getStartDate();
          $end_date = $ilt->getEndDate();
          if ($freeNavigation) {
            $free_link = Link::createFromRoute($title, 'entity.opigno_ilt.canonical', [
              'opigno_ilt' => $step['id'],
            ])
              ->toString();
          }
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
          $end_date_format = $end_date->format('g:i A');
          if ($start_date->format('jS F Y') != $end_date->format('jS F Y')) {
            $end_date_format = $end_date->format('jS F Y - g:i A');
          }
          $title .= ' / ' . $this->t('@start to @end', [
            '@start' => $start_date->format('jS F Y - g:i A'),
            '@end' => $end_date_format,
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
            if (!empty($free_link)) {
              $link = $free_link;
            }
            elseif (!empty($steps[$key - 1]['cid'])) {
              // Get previous step cid.
              if ($steps[$key - 1]['typology'] == 'Course') {
                // If previous step is course get it's last step.
                if (!empty($steps[$key - 1]['course_steps'])) {
                  $course_last_step = end($steps[$key - 1]['course_steps']);
                  if (!empty($course_last_step['cid'])) {
                    $parent_content_id = $course_last_step['cid'];
                  }
                }
              }
              else {
                // If previous step isn't a course.
                $parent_content_id = $steps[$key - 1]['cid'];
              }

              if (!empty($parent_content_id)) {
                $link = Link::createFromRoute($title, 'opigno_learning_path.steps.next', [
                  'group' => $group->id(),
                  'parent_content' => $parent_content_id,
                ])
                  ->toString();
              }
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
