<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\h5p\Entity\H5PContent;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedLink;
use Drupal\opigno_group_manager\OpignoGroupContentTypesManager;
use Drupal\opigno_learning_path\LearningPathValidator;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for all the actions of the Learning Path content.
 */
class LearningPathContentController extends ControllerBase {

  private $content_types_manager;

  /**
   * {@inheritdoc}
   */
  public function __construct(OpignoGroupContentTypesManager $content_types_manager) {
    $this->content_types_manager = $content_types_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_group_manager.content_types.manager')
    );
  }

  /**
   * Root page for angular app.
   */
  public function coursesIndex(Group $group, Request $request) {
    // Check if user has uncompleted steps.
    $validation = LearningPathValidator::stepsValidate($group);

    if ($validation instanceof RedirectResponse) {
      return $validation;
    }

    $group_type = $group->get('type')->getString();

    $next_link = $this->getNextLink($group);
    $view_type = ($group_type == 'opigno_course')
      ? 'manager' : 'modules';

    $tempstore = \Drupal::service('user.private_tempstore')->get('opigno_group_manager');

    return [
      '#theme' => 'opigno_learning_path_courses',
      '#attached' => ['library' => ['opigno_group_manager/manage_app']],
      '#base_path' => $request->getBasePath(),
      '#base_href' => $request->getPathInfo(),
      '#learning_path_id' => $group->id(),
      '#group_type' => $group_type,
      '#view_type' => $view_type,
      '#next_link' => isset($next_link) ? render($next_link) : NULL,
      '#user_has_info_card' => $tempstore->get('hide_info_card') ? FALSE : TRUE,
    ];
  }

  /**
   * Root page for angular app.
   */
  public function modulesIndex(Group $group, Request $request) {
    // Check if user has uncompleted steps.
    $validation = LearningPathValidator::stepsValidate($group);

    if ($validation instanceof RedirectResponse) {
      return $validation;
    }

    $tempstore = \Drupal::service('user.private_tempstore')->get('opigno_group_manager');
    $next_link = $this->getNextLink($group);
    return [
      '#theme' => 'opigno_learning_path_modules',
      '#attached' => ['library' => ['opigno_group_manager/manage_app']],
      '#base_path' => $request->getBasePath(),
      '#base_href' => $request->getPathInfo(),
      '#learning_path_id' => $group->id(),
      '#module_context' => 'false',
      '#next_link' => isset($next_link) ? render($next_link) : NULL,
      '#user_has_info_card' => $tempstore->get('hide_info_card') ? FALSE : TRUE,
    ];
  }

  /**
   * Returns next link.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group.
   *
   * @return array|mixed[]|null
   *   Next link.
   */
  public function getNextLink(Group $group) {
    $next_link = NULL;

    if ($group instanceof GroupInterface) {
      $current_step = opigno_learning_path_get_current_step();

      $user = \Drupal::currentUser();
      if ($current_step == 4
        && !$group->access('administer members', $user)) {
        // Hide link if user can't access members overview tab.
        return NULL;
      }

      $group_type_id = $group->getGroupType()->id();
      if ($group_type_id === 'learning_path') {
        $next_step = ($current_step < 5) ? $current_step + 1 : NULL;
        $link_text = !$next_step ? t('Publish') : t('Next');
      }
      elseif ($group_type_id === 'opigno_course' && $current_step < 3) {
        $link_text = t('Next');
      }
      else {
        return $next_link;
      }
      $next_link = Link::createFromRoute($link_text, 'opigno_learning_path.content_steps', [
        'group' => $group->id(),
        'current' => ($current_step) ? $current_step : 0,
      ], [
        'attributes' => [
          'class' => [
            'btn',
            'btn-success',
            'color-white',
          ],
        ],
      ])->toRenderable();
    }

    return $next_link;
  }

  /**
   * This method is called on learning path load.
   *
   * It returns all the LP courses in JSON format.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function getCourses(Group $group) {
    // Init the response and get all the contents from this learning path.
    $courses = [];
    $group_content = $group->getContent('subgroup:opigno_course');
    foreach ($group_content as $content) {
      /* @var $content \Drupal\group\Entity\GroupContent */
      /* @var $content_entity \Drupal\group\Entity\Group */
      $content_entity = $content->getEntity();
      $courses[] = [
        'entity_id' => $content_entity->id(),
        'name' => $content_entity->label(),
      ];
    }

    // Return all the contents in JSON format.
    return new JsonResponse($courses, Response::HTTP_OK);
  }

  /**
   * This method is called on learning path load.
   *
   * It returns all the LP modules in JSON format.
   */
  public function getModules(Group $group) {
    // Init the response and get all the contents from this learning path.
    $modules = [];
    // Get the courses and modules within those.
    if ($group->getGroupType()->id() == 'learning_path') {
      $group_content = $group->getContent('subgroup:opigno_course');
      foreach ($group_content as $content) {
        /* @var $content \Drupal\group\Entity\GroupContent */
        /* @var $content_entity \Drupal\group\Entity\Group */
        $course = $content->getEntity();
        $course_contents = $course->getContent('opigno_module_group');
        foreach ($course_contents as $course_content) {
          /* @var $module_entity \Drupal\opigno_module\Entity\OpignoModule */
          $module_entity = $course_content->getEntity();
          $modules[] = [
            'entity_id' => $module_entity->id(),
            'name' => $module_entity->label(),
            'activity_count' => $this->countActivityInModule($module_entity),
            'editable' => $module_entity->access('update'),
          ];
        }
      }
    }
    // Get the direct modules.
    $group_content = $group->getContent('opigno_module_group');
    foreach ($group_content as $content) {
      /* @var $content \Drupal\group\Entity\GroupContent */
      /* @var $content_entity \Drupal\opigno_module\Entity\OpignoModule */
      $content_entity = $content->getEntity();
      $modules[] = [
        'entity_id' => $content_entity->id(),
        'name' => $content_entity->label(),
        'activity_count' => $this->countActivityInModule($content_entity),
        'editable' => $content_entity->access('update'),
      ];
    }

    // Return all the contents in JSON format.
    return new JsonResponse($modules, Response::HTTP_OK);
  }

  /**
   * Returns module activities count.
   */
  public function countActivityInModule(OpignoModule $opigno_module) {
    $activities = [];
    /* @var $db_connection \Drupal\Core\Database\Connection */
    $db_connection = \Drupal::service('database');
    $query = $db_connection->select('opigno_activity', 'oa');
    $query->fields('oa', ['id']);
    $query->fields('omr', ['omr_pid', 'child_id']);
    $query->addJoin('inner', 'opigno_activity_field_data', 'oafd', 'oa.id = oafd.id');
    $query->addJoin('inner', 'opigno_module_relationship', 'omr', 'oa.id = omr.child_id');
    $query->condition('oafd.status', 1);
    $query->condition('omr.parent_id', $opigno_module->id());
    if ($opigno_module->getRevisionId()) {
      $query->condition('omr.parent_vid', $opigno_module->getRevisionId());
    }
    $query->condition('omr_pid', NULL, 'IS');
    $result = $query->execute();
    $result->allowRowCount = TRUE;

    return $result->rowCount();
  }

  /**
   * This method is called on learning path load.
   *
   * It returns all the activities with the module.
   */
  public function getModuleActivities(OpignoModule $opigno_module) {
    $activities = $this->getModuleActivitiesEntities($opigno_module);

    // Return all the contents in JSON format.
    return new JsonResponse($activities, Response::HTTP_OK);
  }

  /**
   * Returns conditional activities with the module.
   */
  public function getModuleRequiredActivities(OpignoModule $opigno_module) {
    $activities = $this->getModuleActivitiesEntities($opigno_module);
    $conditional_h5p_types = ['H5P.TrueFalse', 'H5P.MultiChoice'];

    $results = [
      'conditional' => [],
      'simple' => FALSE,
    ];

    if ($activities) {
      // Get only H5P.TrueFalse/H5P.MultiChoice activities.
      foreach ($activities as $key => $activity) {
        $exclude = FALSE;
        $activity = OpignoActivity::load($activity->id);

        if ($activity->hasField('opigno_h5p') && $h5p_content_id = $activity->get('opigno_h5p')->getValue()[0]['h5p_content_id']) {
          $h5p_content = H5PContent::load($h5p_content_id);
          $library = $h5p_content->getLibrary();
          if (!in_array($library->name, $conditional_h5p_types)) {
            $exclude = TRUE;
          }

          if ($library->name == 'H5P.TrueFalse') {
            $params = $h5p_content->getParameters();
            $activities[$key]->answers[0] = [
              'id' => $activity->id() . '-0',
              'correct' => $params->correct == 'true' ? TRUE : FALSE,
              'text' => trim(strip_tags(nl2br(str_replace(['\n', '\r'], '', $params->l10n->trueText)))),
            ];
            $activities[$key]->answers[1] = [
              'id' => $activity->id() . '-1',
              'correct' => $params->correct == 'false' ? TRUE : FALSE,
              'text' => trim(strip_tags(nl2br(str_replace(['\n', '\r'], '', $params->l10n->falseText)))),
            ];
          }

          if ($library->name == 'H5P.MultiChoice') {
            $answers = $h5p_content->getParameters()->answers;
            if ($answers) {
              foreach ($answers as $k => $answer) {
                $activities[$key]->answers[$k] = [
                  'id' => $activity->id() . '-' . $k,
                  'correct' => $answer->correct,
                  'text' => trim(strip_tags(nl2br(str_replace(['\n', '\r'], '', $answer->text)))),
                ];
              }
            }
          }
        }
        else {
          $exclude = TRUE;
        }

        if ($exclude) {
          unset($activities[$key]);
          $results['simple'] = TRUE;
        }
        else {
          $results['conditional'][] = $activities[$key];
        }
      }
    }

    // Return all the contents in JSON format.
    return new JsonResponse($results, Response::HTTP_OK);
  }

  /**
   * Returns activities entities with the module.
   */
  public function getModuleActivitiesEntities(OpignoModule $opigno_module) {
    $activities = [];
    /* @var $db_connection \Drupal\Core\Database\Connection */
    $db_connection = \Drupal::service('database');
    $query = $db_connection->select('opigno_activity', 'oa');
    $query->fields('oafd', ['id', 'vid', 'type', 'name']);
    $query->fields('omr', [
      'weight',
      'max_score',
      'auto_update_max_score',
      'omr_id',
      'omr_pid',
      'child_id',
      'child_vid',
    ]);
    $query->addJoin('inner', 'opigno_activity_field_data', 'oafd', 'oa.id = oafd.id');
    $query->addJoin('inner', 'opigno_module_relationship', 'omr', 'oa.id = omr.child_id');
    $query->condition('oafd.status', 1);
    $query->condition('omr.parent_id', $opigno_module->id());
    if ($opigno_module->getRevisionId()) {
      $query->condition('omr.parent_vid', $opigno_module->getRevisionId());
    }
    $query->condition('omr_pid', NULL, 'IS');
    $query->orderBy('omr.weight');
    $result = $query->execute();
    foreach ($result as $activity) {
      $activities[$activity->id] = $activity;
    }

    return $activities;
  }

  /**
   * This method is called on learning path load.
   *
   * It will update an existing activity relation.
   */
  public function updateActivity(OpignoModule $opigno_module, Request $request) {
    // First, check the params.
    $datas = json_decode($request->getContent());
    if (empty($datas->omr_id) || !isset($datas->max_score)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }
    /* @var $db_connection \Drupal\Core\Database\Connection */
    $db_connection = \Drupal::service('database');
    $merge_query = $db_connection->merge('opigno_module_relationship')
      ->keys([
        'omr_id' => $datas->omr_id,
      ])
      ->fields([
        'max_score' => $datas->max_score,
      ])
      ->execute();
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * This method is called on learning path load.
   *
   * It will update an existing activity relation.
   */
  public function deleteActivity(OpignoModule $opigno_module, Request $request) {
    // First, check the params.
    $datas = json_decode($request->getContent());
    if (empty($datas->omr_id)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }
    /* @var $db_connection \Drupal\Core\Database\Connection */
    $db_connection = \Drupal::service('database');

    // Load Activity before deleting relationship.
    $relationship = $db_connection
      ->select('opigno_module_relationship', 'omr')
      ->fields('omr', ['child_id', 'group_id'])
      ->condition('omr_id', $datas->omr_id, '=')
      ->groupBy('child_id')
      ->execute()
      ->fetchObject();
    if (!empty($relationship->child_id)) {
      $opigno_activity = OpignoActivity::load($relationship->child_id);

      // Allow other modules to take actions.
      \Drupal::moduleHandler()->invokeAll(
        'opigno_learning_path_activity_delete',
        [$opigno_module, $opigno_activity]
      );

      // Delete relationship.
      $delete_query = $db_connection->delete('opigno_module_relationship');
      $delete_query->condition('omr_id', $datas->omr_id);
      $delete_query->execute();

      if (!empty($relationship->group_id)) {
        $links = OpignoGroupManagedLink::loadByProperties([
          'group_id' => $relationship->group_id,
          'parent_content_id' => $opigno_module->id(),
        ]);

        $added_activities = $opigno_module->getModuleActivities();
        // Remove conditions if no activities;
        foreach ($links as $link) {
          if (empty($added_activities)) {
            $link->set('required_activities', null);
            $link->set('required_score', 0);
            $link->save();
          } else {
            $activity_params = $link->get('required_activities')->getString();
            $activity_params = unserialize($activity_params);
            foreach ($activity_params as $param) {
              $options = explode('-', $param);
              if ($options[0] == $relationship->child_id) {
                $link->set('required_activities', null)->save();
                break;
              }
            }
          }
        }
      }
    }

    return new JsonResponse(NULL, Response::HTTP_OK);
  }

}
