<?php

namespace Drupal\opigno_module\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Drupal\opigno_module\Entity\UserModuleStatus;
use Drupal\opigno_module\OpignoModuleBadges;
use Drupal\opigno_learning_path\LearningPathContent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OpignoModuleController.
 *
 * @package Drupal\opigno_module
 */
class OpignoModuleController extends ControllerBase {

  /**
   * Get activities related to specific module.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $opigno_module
   *   Opigno module entity object.
   *
   * @return array
   *   Array of module's activities.
   */
  public function moduleActivities(OpignoModuleInterface $opigno_module) {
    /* @todo join table with activity revisions */
    $activities = [];
    /* @var $db_connection \Drupal\Core\Database\Connection */
    $db_connection = \Drupal::service('database');
    $query = $db_connection->select('opigno_activity', 'oa');
    $query->fields('oafd', ['id', 'type', 'name']);
    $query->fields('omr', [
      'activity_status',
      'weight', 'max_score',
      'auto_update_max_score',
      'omr_id',
      'omr_pid',
      'child_id',
      'child_vid',
    ]);
    $query->addJoin('inner', 'opigno_activity_field_data', 'oafd', 'oa.id = oafd.id');
    $query->addJoin('inner', 'opigno_module_relationship', 'omr', 'oa.id = omr.child_vid');
    $query->condition('oafd.status', 1);
    $query->condition('omr.parent_id', $opigno_module->id());
    if ($opigno_module->getRevisionId()) {
      $query->condition('omr.parent_vid', $opigno_module->getRevisionId());
    }
    $query->condition('omr_pid', NULL, 'IS');
    $query->orderBy('omr.weight');
    $result = $query->execute();
    foreach ($result as $activity) {
      $activities[] = $activity;
    }

    return $activities;
  }

  /**
   * Add activities to existing module.
   *
   * @param array $activities
   *   Array of activities that will be added.
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $module
   *   Opigno module entity object.
   * @param null|Group $group
   *   Training ID.
   * @param null|int $max_score
   *
   * @return bool
   *   Activities to module flag.
   *
   * @throws \Exception
   */
  public function activitiesToModule(array $activities, OpignoModuleInterface $module, Group $group = NULL, $max_score = 10) {
    /* @var $connection \Drupal\Core\Database\Connection */
    $connection = \Drupal::service('database');
    $module_activities_fields = [];
    foreach ($activities as $activity) {
      if ($activity instanceof OpignoActivityInterface) {
        /* @todo Use version ID instead of reuse of ID. */
        $module_activity_fields['parent_id'] = $module->id();
        $module_activity_fields['parent_vid'] = $module->getRevisionId();
        $module_activity_fields['child_id'] = $activity->id();
        $module_activity_fields['child_vid'] = $activity->getRevisionId();
        $module_activity_fields['max_score'] = $max_score;
        $module_activity_fields['group_id'] = is_object($group) ? $group->id() : $group;
        $module_activities_fields[] = $module_activity_fields;
      }
    }
    if (!empty($module_activities_fields)) {
      $insert_query = $connection->insert('opigno_module_relationship')->fields([
        'parent_id',
        'parent_vid',
        'child_id',
        'child_vid',
        'max_score',
        'group_id',
      ]);
      foreach ($module_activities_fields as $module_activities_field) {
        $insert_query->values($module_activities_field);
      }

      try {
        $insert_query->execute();
      }
      catch (\Exception $e) {
        \Drupal::messenger()->addMessage($e->getMessage(), 'error');
        return FALSE;
      }

      // Set activities weight.
      if (empty($e)) {
        $activities = $module->getModuleActivities();
        if ($activities) {
          $weight = -1000;
          foreach ($activities as $activity) {
            if (!empty($activity->omr_id)) {
              $connection->merge('opigno_module_relationship')
                ->keys([
                  'omr_id' => $activity->omr_id,
                ])
                ->fields([
                  'weight' => $weight,
                ])
                ->execute();

              $weight++;
            }
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Method for Take module tab route.
   */
  public function takeModule(Request $request, Group $group, OpignoModuleInterface $opigno_module) {
    /* @var $opigno_module \Drupal\opigno_module\Entity\OpignoModule */
    /* @var $query_options \Symfony\Component\HttpFoundation\ParameterBag */
    $query_options = $request->query;

    // Set current ID of group
    OpignoGroupContext::setGroupId($group->id());

    // Check Module availability.
    $availability = $opigno_module->checkModuleAvailability();

    if (!$availability['open']) {
      // Module is not available. Based on availability time.
      \Drupal::messenger()->addWarning($availability['message']);
      return $this->redirect('entity.group.canonical', [
        'group' => $group->id(),
      ]);
    }
    // Check Module attempts.
    if ($this->currentUser()->id() != 1 && !in_array('administrator', $this->currentUser()->getAccount()->getRoles())) {
      $allowed_attempts = $opigno_module->get('takes')->value;
      if ($allowed_attempts > 0) {
        // It means, that module attempts are limited.
        // Need to check User attempts.
        $user_attempts = $opigno_module->getModuleAttempts($this->currentUser());
        if (count($user_attempts) >= $allowed_attempts) {
          // User has more attempts then allowed.
          // Check for not finished attempt.
          $active_attempt = $opigno_module->getModuleActiveAttempt($this->currentUser());
          if ($active_attempt == NULL) {
            // There is no not finished attempt.
            \Drupal::messenger()->addWarning($this->t('Maximum attempts for this module reached.'));
            return $this->redirect('entity.group.canonical', [
              'group' => $group->id(),
            ]);
          }
        }
      }
    }

    // Check if module in skills system.
    $skills_active = $opigno_module->getSkillsActive();

    // Get activities from the Module.
    $activities = $opigno_module->getModuleActivities();
    $target_skill = $opigno_module->getTargetSkill();
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('opigno_skills_system') && $skills_active) {
      $skills_tree = array_reverse($term_storage->loadTree('skills', $target_skill));
      $db_connection = \Drupal::service('database');

      // Get current user's skills.
      $uid = $this->currentUser()->id();
      $query = $db_connection
        ->select('opigno_skills_statistic', 'o_s_s');
      $query->fields('o_s_s', ['tid', 'score', 'progress', 'stage']);
      $query->condition('o_s_s.uid', $uid);
      $user_skills = $query
        ->execute()
        ->fetchAllAssoc('tid');

      // Find skills which needs to level-up.
      $depth_of_skills_tree = $skills_tree[0]->depth;
      $current_skills = [];

      if (!isset($depth_of_skills_tree)) {
        $depth_of_skills_tree = 0;
      }

      // Set default successfully finished this skills tree for user.
      // If the system finds any skill which is not successfully finished then change this variable to FALSE.
      $successfully_finished = TRUE;

      while (($depth_of_skills_tree >= 0)) {
        foreach ($skills_tree as $key => $skill) {
          $tid = $skill->tid;
          $skill_entity = $term_storage->load($tid);
          $minimum_score = $skill_entity->get('field_minimum_score')->getValue()[0]['value'];
          $children = $term_storage->loadChildren($tid);
          $skill_access = TRUE;

          // Check if the skill was successfully finished.
          if (!isset($user_skills[$skill->tid])) {
            $successfully_finished = FALSE;
          } else {
            if (($minimum_score > $user_skills[$skill->tid]->score || $user_skills[$skill->tid]->progress < 100)) {
              $successfully_finished = FALSE;
            }
          }

          if (!empty($children)) {
            $children_ids = array_keys($children);
            foreach ($children_ids as $child_id) {
              if (!isset($user_skills[$child_id])) {
                $skill_access = FALSE;
                break;
              }

              if ($user_skills[$child_id]->progress < 100 || $user_skills[$child_id]->score < $minimum_score) {
                $skill_access = FALSE;
              }
            }
          }

          if ($skill->depth == $depth_of_skills_tree && $skill_access == TRUE
            && (!$successfully_finished)) {
            $current_skills[] =  $skill->tid;
          }
        }
        $depth_of_skills_tree--;
      }

      // Check if Opigno system could load all suitable activities not included in the current module.
      $use_all_activities = $opigno_module->getModuleSkillsGlobal();
      if ($use_all_activities && !empty($current_skills)) {
        $activities += $opigno_module->getSuitableActivities($current_skills);
      }
    }

    // Create new attempt or resume existing one.
    $attempt = $opigno_module->getModuleActiveAttempt($this->currentUser());

    if ($attempt == NULL) {
      // No existing attempt, create new one.
      $attempt = UserModuleStatus::create([]);
      $attempt->setModule($opigno_module);
      $attempt->setFinished(0);
      $attempt->save();
    }

    // Get training unfinished attempt.
    $attempt_lp = $opigno_module->getTrainingActiveAttempt($this->currentUser(), $group);
    if ($attempt_lp == NULL) {
      // No unfinished attempt. Create training new attempt
      // only if current step is mandatory.
      $steps = LearningPathContent::getAllStepsOnlyModules($group->id());
      $step_module_info = array_filter($steps, function ($step) use ($opigno_module) {
        return $step['id'] == $opigno_module->id();
      });

      if (!empty($step_module_info)) {
        $step_module_info = array_shift($step_module_info);
        // Get started time for training attempt.
        $started = $attempt->get('started')->getValue();
        $started = !empty($started[0]['value']) ? $started[0]['value'] : time();

        // Create training new attempt.
        $attempt_lp = LPStatus::create([
          'uid' => $this->currentUser()->id(),
          'gid' => $group->id(),
          'status' => 'in progress',
          'started' => $started,
          'finished' => 0,
        ]);
        $attempt_lp->save();
      }
    }

    // Redirect to the module results page with message 'successfully finished'.
    if (isset($successfully_finished) && $successfully_finished) {
      if ($attempt->isFinished()) {
        $this->messenger()->addWarning($this->t('You already successfully finished this skill\'s tree.'));
      }
      else {
        $sum_score = 0;

        foreach ($user_skills as $skill) {
          $sum_score += $skill->score;
        }

        $avg_score = $sum_score / count($user_skills);

        $attempt->setFinished(\Drupal::time()->getRequestTime());
        $attempt->setScore($avg_score);
        $attempt->setEvaluated(1);
        $attempt->setMaxScore($avg_score);
        $attempt->save();
      }

      $_SESSION['successfully_finished'] = TRUE;

      return $this->redirect('opigno_module.module_result', [
        'opigno_module' => $opigno_module->id(),
        'user_module_status' => $attempt->id(),
      ]);
    }

    if (count($activities) > 0) {
      // Get activity that will be answered.
      $next_activity_id = NULL;
      $last_activity_id = $attempt->getLastActivityId();
      $get_next = FALSE;

      // Check if Skills system is activated for this module.
      if ($moduleHandler->moduleExists('opigno_skills_system') && $skills_active) {
        // Load user's answers for current skills.
        $activities_ids = array_keys($activities);
        $query = $db_connection->select('opigno_answer_field_data', 'o_a_f_d');
        $query->leftJoin('opigno_module_relationship', 'o_m_r', 'o_a_f_d.activity = o_m_r.child_id');
        $query->addExpression('MAX(o_a_f_d.score)', 'score');
        $query->addExpression('MAX(o_a_f_d.changed)', 'changed');
        $query->addExpression('MAX(o_a_f_d.skills_mode)', 'skills_mode');
        $query->addField('o_a_f_d', 'activity');
        $query->condition('o_a_f_d.user_id', $uid)
          ->condition('o_a_f_d.activity', $activities_ids, 'IN')
          ->condition('o_a_f_d.skills_mode', NULL, 'IS NOT NULL');

        $answers = $query
          ->groupBy('o_a_f_d.activity')
          ->orderBy('score')
          ->orderBy('changed')
          ->execute()
          ->fetchAllAssoc('activity');

        $answers_ids = array_keys($answers);
        $available_activities = (count($activities_ids) > count($answers_ids)) ? TRUE : FALSE;

        if (!is_null($last_activity_id)) {
          $last_skill_activity = \Drupal::entityTypeManager()->getStorage('opigno_activity')->load($last_activity_id);
          $last_skill_id = $last_skill_activity->getSkillId();
        }

        foreach ($activities as $activity) {
          // Get initial level. This is equal to the count of levels.
          $initial_level = $term_storage->load($activity->skills_list);
          if ($initial_level) {
            $initial_level = count($initial_level->get('field_level_names')->getValue());
          }

          // Check if the skill level of activity matches the current skill level of the user.
          if ((isset($user_skills[$activity->skills_list]) && $user_skills[$activity->skills_list]->stage != $activity->skill_level)
              || (!isset($user_skills[$activity->skills_list]) && $activity->skill_level != $initial_level)) {
            continue;
          }

          if (in_array($activity->skills_list, $current_skills) && !in_array($activity->id, $answers_ids)) {
            $next_activity_id = $activity->id;

            // If we don't have ID of last activity then set next activity by default.
            if (!isset($last_skill_id)) {
              break;
            } // If we found available activity with current skill then set it as next activity.
            elseif ($last_skill_id == $activity->skills_list) {
              break;
            }
          }
        }

        // If user already answered at all questions of available skills.
        if ($next_activity_id == NULL) {
          foreach ($answers as $answer) {
            if (in_array($answer->skills_mode, $current_skills) && $answer->score != $activities[$answer->activity]->max_score) {
              $next_activity_id = $answer->activity;

              // If we don't have ID of last activity then set next activity by default.
              if (!isset($last_skill_id)) {
                break;
              } // If we found available activity with current skill then set it as next activity.
              elseif ($last_skill_id == $answer->skills_mode) {
                break;
              }
            }
          }
        }

        // Redirect user to the next activity(answer).
        if (isset($next_activity_id)) {
          return $this->redirect('opigno_module.group.answer_form', [
            'group' => $group->id(),
            'opigno_activity' => $next_activity_id,
            'opigno_module' => $opigno_module->id(),
          ]);
        }
        // Redirect to the module results page if there are no activities for the user.
        else {
          $_SESSION['successfully_finished'] = FALSE;
        }
      }

      // Get additional module settings.
      $backwards_param = $query_options->get('backwards');
      // Take into account randomization options.
      $randomization = $opigno_module->getRandomization();
      if ($randomization > 0) {
        // Get random activity and put it in a sequence.
        $random_activity = $opigno_module->getRandomActivity($attempt);
        if ($random_activity) {
          $next_activity_id = $random_activity->id();
        }
      }
      else {
        foreach ($activities as $activity_id => $activity) {
          // Check for backwards navigation submit.
          if ($opigno_module->getBackwardsNavigation() && isset($prev_activity_id) && $last_activity_id == $activity_id && $backwards_param) {
            $next_activity_id = $prev_activity_id;
            break;
          }
          if (is_null($last_activity_id) || $get_next) {
            // Get the first activity.
            $next_activity_id = $activity_id;
            break;
          }
          if ($last_activity_id == $activity_id) {
            // Get the next activity after this one.
            $get_next = TRUE;
          }
          $prev_activity_id = $activity_id;
        }

        // Check if user navigate to previous module with "back" button.
        $from_first_activity = (key($activities) == $last_activity_id)
          || (key($activities) == $attempt->getCurrentActivityId())
          || ($last_activity_id == NULL);
        if ($opigno_module->getBackwardsNavigation() && $from_first_activity && $backwards_param) {
          return $this->redirect('opigno_module.get_previous_module', [
            'opigno_module' => $opigno_module->id(),
          ]);
        }
      }

      // Get group context.
      $cid = OpignoGroupContext::getCurrentGroupContentId();
      $gid = OpignoGroupContext::getCurrentGroupId();
      if ($gid) {
        $steps = LearningPathContent::getAllStepsOnlyModules($gid);
        foreach ($steps as $step) {
          if ($step['id'] == $opigno_module->id() && $step['cid'] != $cid) {
            // Update content cid.
            OpignoGroupContext::setCurrentContentId($step['cid']);
          }
        }
      }

      $activities_storage = static::entityTypeManager()->getStorage('opigno_activity');

      if (!is_null($next_activity_id)) {
        // Means that we have some activity to answer.
        $attempt->setCurrentActivity($activities_storage->load($next_activity_id));
        $attempt->save();
        return $this->redirect('opigno_module.group.answer_form', [
          'group' => $group->id(),
          'opigno_activity' => $next_activity_id,
          'opigno_module' => $opigno_module->id(),
        ]);
      }
      else {
        // If a user clicks "Back" button,
        // show the last question instead of summary page.
        $previous = $query_options->get('previous');
        if ($previous) {
          return $this->redirect('opigno_module.group.answer_form', [
            'group' => $group->id(),
            'opigno_activity' => $last_activity_id,
            'opigno_module' => $opigno_module->id(),
          ]);
        }
        else {
          $attempt->finishAttempt();
          return $this->redirect('opigno_module.module_result', [
            'opigno_module' => $opigno_module->id(),
            'user_module_status' => $attempt->id(),
          ]);
        }
      }
    }

    if ($target_skill) {
      $target_skill_entity = $term_storage->load($target_skill);
      $warning = $this->t('There are not enough activities in the Opigno System to complete "@target_skill" skills tree!', ['@target_skill' => $target_skill_entity->getName()]);
      return [
        '#markup' => "<div class='lp_step_explanation'>$warning</div>",
        '#attached' => [
          'library' => ['opigno_learning_path/creation_steps'],
        ]
      ];
    }

    \Drupal::messenger()->addWarning($this->t('Module @module_label does not contain any activity.', ['@module_label' => $opigno_module->label()]));
    return $this->redirect('entity.group.canonical', [
      'group' => $group->id(),
    ]);
  }

  /**
   * Returns module question answer form title.
   */
  public function moduleQuestionAnswerFormTitle(OpignoModuleInterface $opigno_module, OpignoActivityInterface $opigno_activity) {
    return $opigno_module->getName();
  }

  /**
   * Returns module question answer form.
   */
  public function moduleQuestionAnswerForm(OpignoModuleInterface $opigno_module, OpignoActivityInterface $opigno_activity) {
    $build = [];
    $user = $this->currentUser();
    $uid = $user->id();
    $attempt = $opigno_module->getModuleActiveAttempt($user);

    // Check if user have access on this step of LP.
    $gid = OpignoGroupContext::getCurrentGroupId();
    if ($gid && $group = Group::load($gid)) {
      // Get training latest certification timestamp.
      $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);

      $current_cid = OpignoGroupContext::getCurrentGroupContentId();

      // Load group courses substeps.
      $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid, FALSE, NULL, $latest_cert_date);
    }
    else {
      return [];
    }

    // If not and group have we GuidedNavigation should redirect
    // his current point of group.
    if ($attempt === NULL) {
      if (OpignoGroupManagerController::getGuidedNavigation($group)) {
        $messenger = $this->messenger();
        $this->messenger()->addMessage($this->t("You can't access this step, first you need to finish required steps before."), $messenger::TYPE_WARNING);
        return $this->redirect('opigno_learning_path.steps.start', ['group' => $gid]);
      }
      else {
        return $this->redirect('opigno_module.take_module', ['group' => $gid, 'opigno_module' => $opigno_module->id()]);
      }
    }

    // Check if user try to load activity from another module.
    $module_activities = opigno_learning_path_get_module_activities($opigno_module->id(), $uid, FALSE, $latest_cert_date);
    $activities_ids = array_keys($module_activities);

    // Check if module in skills system.
    $skills_activate = $opigno_module->getSkillsActive();
    $moduleHandler = \Drupal::service('module_handler');

    if ((!in_array($opigno_activity->id(), $activities_ids)
      && (!$skills_activate || !$moduleHandler->moduleExists('opigno_skills_system')))) {
      $query = \Drupal::database()
        ->select('user_module_status', 'u_m_s');
      $query->fields('u_m_s', ['current_activity']);
      $query->condition('u_m_s.user_id', $uid);
      $query->condition('u_m_s.module', $opigno_module->id());
      $query->condition('u_m_s.learning_path', $gid);
      $query->orderBy('u_m_s.id', 'DESC');
      $current_activity = $query
        ->execute()
        ->fetchField();

      // Set first activity as current if we can't get it from module status.
      if (is_null($current_activity)) {
        $current_activity = $module_activities[$activities_ids[0]]['activity_id'];
      }

      return $this->redirect('opigno_module.group.answer_form', [
        'group' => $gid,
        'opigno_module' => $opigno_module->id(),
        'opigno_activity' => $current_activity,
      ]);
    }

    if (!empty($steps)) {
      $group = Group::load($gid);
      // Get training guided navigation option.
      $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);

      foreach ($steps as $key => $step) {
        // Check if user manually entered the url.
        if ($current_cid == $step['cid']) {
          if ($opigno_module->id() != $step['id']) {
            return $this->redirect('opigno_learning_path.steps.next', [
              'group' => $gid,
              'parent_content' => $steps[$key]['cid'],
            ]);
          }
          break;
        }

        // Check if user is trying to skip mandatory activity.
        if (!$freeNavigation && ($step['mandatory'] == 1 &&
            $step['required score'] > $step['best score']) ||
            OpignoGroupManagerController::mustBeVisitedMeeting($step, $group)) {

          return $this->redirect('opigno_learning_path.steps.next', [
            'group' => $gid,
            'parent_content' => $steps[$key]['cid'],
          ]);
        }
      }
    }

    if (!is_null($attempt)) {
      $existing_answer = $opigno_activity->getUserAnswer($opigno_module, $attempt, $user);
      if (!is_null($existing_answer)) {
        $answer = $existing_answer;
      }
    }
    if (!isset($answer)) {
      $answer = static::entityTypeManager()->getStorage('opigno_answer')->create([
        'type' => $opigno_activity->getType(),
        'activity' => $opigno_activity->id(),
        'module' => $opigno_module->id(),
      ]);
    }

    // Output rendered activity of the specified type.
    $build[] = \Drupal::entityTypeManager()->getViewBuilder('opigno_activity')->view($opigno_activity, 'activity');
    // Output answer form of the same activity type.
    $build[] = $this->entityFormBuilder()->getForm($answer);

    return $build;
  }

  /**
   * Returns user results.
   */
  public function userResults(OpignoModuleInterface $opigno_module) {
    $content = [];
    $results_feedback = $opigno_module->getResultsOptions();
    $user_attempts = $opigno_module->getModuleAttempts($this->currentUser());
    foreach ($user_attempts as $user_attempt) {
      /* @var $user_attempt UserModuleStatus */
      $score_percents = $user_attempt->getScore();
      $max_score = $user_attempt->getMaxScore();
      $score = round(($max_score * $score_percents) / 100);
      foreach ($results_feedback as $result_feedback) {
        // Check if result is between low and high percents.
        // Break on first meet.
        if ($score_percents <= $result_feedback->option_end && $score_percents >= $result_feedback->option_start) {
          $feedback = check_markup($result_feedback->option_summary, $result_feedback->option_summary_format);
          break;
        }
      }
      $content[] = [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('You got %score of %max_score possible points.', [
            '%max_score' => $max_score,
            '%score' => $score,
          ]),
          $this->t('Score: %score%', ['%score' => $user_attempt->getScore()]),
          isset($feedback) ? $feedback : '',
        ],
      ];
    }
    return $content;
  }

  /**
   * Returns user result.
   */
  public function userResult(OpignoModuleInterface $opigno_module, UserModuleStatus $user_module_status = NULL) {
    $content = [];
    $user_answers = $user_module_status->getAnswers();
    $question_number = 0;
    $module_activities = $opigno_module->getModuleActivities();
    $score = $user_module_status->getScore();
    $moduleHandler = \Drupal::service('module_handler');

    $lp_id = $user_module_status->get('learning_path')->target_id;
    OpignoGroupContext::setGroupId($lp_id);

    // Added module name to page.
    $content[] = [
      '#theme' => 'page_title',
      '#title' => $opigno_module->label(),
    ];

    // Load more activities for 'skills module' with switched on option 'use all suitable activities from Opigno system'.
    if ($moduleHandler->moduleExists('opigno_skills_system') && $opigno_module->getSkillsActive()) {
      $target_skill = $opigno_module->getTargetSkill();
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $skills_tree = array_reverse($term_storage->loadTree('skills', $target_skill));
      $current_skills = [];

      if ($opigno_module->getModuleSkillsGlobal()) {
        foreach ($skills_tree as $skill) {
          $current_skills[] = $skill->tid;
        }

        $module_activities += $opigno_module->getSuitableActivities($current_skills);
      }
    }

    foreach ($user_answers as $answer) {
      $question_number++;
      $answer_activity = $answer->getActivity();
      if (!$answer_activity) {
        continue;
      }
      $content[] = [
        '#theme' => 'opigno_user_result_item',
        '#opigno_answer' => $answer,
        '#opigno_answer_activity' => $answer_activity,
        '#question_number' => $question_number,
        '#answer_max_score' => isset($module_activities[$answer_activity->id()]->max_score) ? $module_activities[$answer_activity->id()]->max_score : 10,
      ];
    }

    $user = $this->currentUser();
    $uid = $user->id();
    $gid = OpignoGroupContext::getCurrentGroupId();

    if (!empty($gid)) {
      $group = Group::load($gid);

      $latest_cert_date = LPStatus::getTrainingStartDate($group, $uid);
      $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid, FALSE, NULL, $latest_cert_date);


      if (!empty($_SESSION['step_required_conditions_failed'])) {
        // If guided option set, required conditions exist and didn't met.
        unset($_SESSION['step_required_conditions_failed']);

        $content_type_manager = \Drupal::service('opigno_group_manager.content_types.manager');
        $step_info = opigno_learning_path_get_module_step($gid, $uid, $opigno_module, $latest_cert_date);

        $content = [];
        $content[] = [
          '#type' => 'html_tag',
          '#tag' => 'h1',
          '#value' => $this->t('Next'),
        ];

        if (!empty($step_info)) {
          $course_entity = OpignoGroupManagedContent::load($step_info['cid']);
          $course_content_type = $content_type_manager->createInstance(
            $course_entity->getGroupContentTypeId()
          );
          $current_step_url = $course_content_type->getStartContentUrl(
            $course_entity->getEntityId(),
            $gid
          );

          $content[] = [
            '#type' => 'markup',
            '#markup' => $this->t('<p>You should first met required conditions for the step "%step" before going further. <a href=":link">Try again.</a></p>', [
              ':link' => $current_step_url->toString(),
              '%step' => $opigno_module->getName(),
            ]),
          ];
        }

        return $content;
      }

      // Find current step.
      $current_step_key = array_search($opigno_module->id(), array_column($steps, 'id'));
      $current_step = isset($current_step_key) ? $steps[$current_step_key] : NULL;

      // Remove the live meeting and instructor-led training steps.
      $steps = array_filter($steps, function ($step) {
        return !in_array($step['typology'], ['Meeting', 'ILT']);
      });

      // Get training guided navigation option.
      $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);

      if ($current_step !== NULL) {
        $last_step = end($steps);
        $is_last = $last_step['cid'] === $current_step['cid'];

        $current_step['current attempt score'] = $score;

        $start_time = (int) $user_module_status->get('started')->getValue()[0]['value'];
        $end_time = (int) $user_module_status->get('finished')->getValue()[0]['value'];
        $time = $end_time > $start_time ? $end_time - $start_time : 0;
        $current_step['current attempt time'] = $time;
        if (isset($current_step['parent'])) {
          $current_step['parent']['current attempt time'] = $time;
        }

        // Send notification about manual evaluation.
        $module_id = $opigno_module->id();
        $is_evaluated = $user_module_status->get('evaluated')->getValue()[0]['value'];
        if (!$is_evaluated) {
          $status_id = $user_module_status->id();
          $message = $this->t('Module "@module" need manual evaluating.', ['@module' => $current_step['name']]);
          $url = Url::fromRoute('opigno_module.module_result_form',
            [
              'opigno_module' => $module_id,
              'user_module_status' => $status_id,
            ]
          )->toString();
          // Get user managers.
          $members = $group->getMembers('learning_path-user_manager');
          $members_entities = array_map(function ($member) {
            return $member->getUser();
          }, $members);
          // Get admins.
          $admins = opigno_module_get_users_by_role('administrator');
          // Get array of all users who must receive notification.
          $users = array_merge($members_entities, $admins);
          foreach ($users as $user) {
            opigno_set_message($user->id(), $message, $url);
          }
        };

        $skip_links = \Drupal::request()->query->get('skip-links');
        if (!$skip_links) {
          if ($score >= $current_step['required score']) {
            $message = $this->t('Successfully completed module "@module" in "@name"', [
              '@module' => $current_step['name'],
              '@name' => $group->label(),
            ]);
            $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()])
              ->toString();
            opigno_set_message($uid, $message, $url);

            if ($is_last) {
              $message = $this->t('Congratulations! You successfully finished the training "@name"', [
                '@name' => $group->label(),
              ]);
              $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()])
                ->toString();
              opigno_set_message($uid, $message, $url);
            }
          }

          // Training attempt update/finish.
          // Get mandatory steps.
          $mandatory_steps = array_filter($steps, function ($step) {
            return $step['mandatory'];
          });

          // Steps queue mapping.
          $steps_cids_mapping = array_map(function ($step) {
            return $step['cid'];
          }, $steps);

          // Check if there are more mandatory steps.
          // Get last mandatory step key.
          $last_mandatory_step = end($mandatory_steps);
          $last_mandatory_step_key = array_search($last_mandatory_step["cid"], $steps_cids_mapping);
          // Get current step key.
          $current_step_key = array_search($current_step["cid"], $steps_cids_mapping);

          if ($current_step_key < $last_mandatory_step_key) {
            // There are more mandatory steps.
            $lp_status = 'in progress';
            $finished = 0;
          }
          else {
            // No more mandatory steps.
            // Calculate training attempt status.
            $training_is_passed = opigno_learning_path_is_passed($group, $uid, TRUE);
            if ($training_is_passed) {
              $lp_status = 'passed';
            }
            else {
              $lp_status = 'failed';
            }

            $finished = !empty($end_time) ? $end_time : time();
          }

          // Get training unfinished attempt.
          $attempt_lp = $opigno_module->getTrainingActiveAttempt($this->currentUser(), $group);
          if ($attempt_lp) {
            // Update training unfinished attempt.
            $attempt_lp->setStatus($lp_status);
            $attempt_lp->setFinished($finished);

            if ($lp_status == 'passed') {
              // Save training current attempt score.
              $training_score = opigno_learning_path_get_score($group->id(), $uid, TRUE);
              $attempt_lp->setScore($training_score);
            }

            $attempt_lp->save();

            // Save passed training certificate expire date if set expiration.
            if ($lp_status == 'passed' && LPStatus::isCertificateExpireSet($group)) {
              $expiration_period = LPStatus::getCertificateExpirationPeriod($group);
              if ($expiration_period) {
                // Latest certificate timestamp - existing or new.
                $started = $attempt_lp->getStarted();
                if ($started) {
                  $latest_cert_date = $started;
                }

                if (!LPStatus::isCertificateExpired($group, $uid)) {
                  if ($existing_cert_date = LPStatus::getLatestCertificateTimestamp($gid, $uid)) {
                    $latest_cert_date = $existing_cert_date;
                  }
                }

                $latest_cert_date = !empty($latest_cert_date) ? $latest_cert_date : $start_time;

                // Calculate expiration timestamp.
                $suffix = $expiration_period > 1 ? 's' : '';
                $offset = '+' . $expiration_period . ' month' . $suffix;
                $expiration_timestamp = strtotime($offset, $finished);
                // Set expiration data.
                LPStatus::setCertificateExpireTimestamp($gid, $uid, $latest_cert_date, $expiration_timestamp);
              }
            }
          }

          // Training achievements.
          if (function_exists('opigno_learning_path_save_step_achievements')) {
            // Save current step parent achievements.
            $parent_id = isset($current_step['parent'])
              ? opigno_learning_path_save_step_achievements($gid, $uid, $current_step['parent'])
              : 0;
            // Save current step achievements.
            opigno_learning_path_save_step_achievements($gid, $uid, $current_step, $parent_id);
          }

          if (function_exists('opigno_learning_path_save_achievements')) {
            // Save training achievements.
            opigno_learning_path_save_achievements($gid, $uid);
          }

          // Modules notifications and badges.
          if ($current_step['typology'] == 'Module' && $opigno_module->badge_active->value) {
            $badge_notification = '';
            $save_badge = FALSE;

            // Badge notification for finished state.
            if ($opigno_module->badge_criteria->value == 'finished') {
              $badge_notification = $opigno_module->badge_name->value;
              $save_badge = TRUE;
            }

            // Badge notification for successful finished state.
            if ($opigno_module->badge_criteria->value == 'success' && $score >= $current_step['required score']) {
              $badge_notification = $opigno_module->badge_name->value;
              $save_badge = TRUE;
            }

            if (!empty($save_badge)) {
              // Save badge.
              try {
                OpignoModuleBadges::opignoModuleSaveBadge($uid, $gid, $current_step['typology'], $current_step['id']);
              } catch (\Exception $e) {
                $this->messenger()->addMessage($e->getMessage(), 'error');
              }
            }

            if ($badge_notification) {
              $message = $this->t('You earned a badge "@badge" in "@name"', [
                '@badge' => $badge_notification,
                '@name' => $group->label(),
              ]);
              $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()])
                ->toString();
              opigno_set_message($uid, $message, $url);
            }
          }

          // Courses notifications and badges.
          if ($current_step['typology'] == 'Module' && !empty($current_step['parent']) && $current_step['parent']['typology'] == 'Course') {
            $course = Group::load($current_step['parent']['id']);
            if ($course->badge_active->value) {
              $badge_notification = '';
              $save_badge = FALSE;

              $course_steps = opigno_learning_path_get_steps($current_step['parent']['id'], $uid);
              $course_last_step = end($course_steps);
              $course_is_last = $course_last_step['cid'] === $current_step['cid'];

              if ($course_is_last) {
                // Badge notification for finished state.
                if ($course->badge_criteria->value == 'finished') {
                  $badge_notification = $course->badge_name->value;
                  $save_badge = TRUE;
                }

                // Badge notification for successful finished state.
                if ($course->badge_criteria->value == 'success' && $score >= $current_step['required score']) {
                  $badge_notification = $course->badge_name->value;
                  $save_badge = TRUE;
                }

                if (!empty($save_badge)) {
                  // Save badge.
                  try {
                    OpignoModuleBadges::opignoModuleSaveBadge($uid, $gid, 'Course', $current_step['parent']['id']);
                  } catch (\Exception $e) {
                    $this->messenger()->addMessage($e->getMessage(), 'error');
                  }
                }

                if ($badge_notification) {
                  $message = $this->t('You earned a badge "@badge" in "@name"', [
                    '@badge' => $badge_notification,
                    '@name' => $group->label(),
                  ]);
                  $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()])
                    ->toString();
                  opigno_set_message($uid, $message, $url);
                }
              }
            }
          }
        }

        // Get module feedback.
        $feedback_options = $opigno_module->getResultsOptions();
        if ($feedback_options) {
          $feedback_option = array_filter($feedback_options, function ($option) use ($score) {
            $min = (int) $option->option_start;
            $max = (int) $option->option_end;
            if (in_array($score, range($min, $max))) {
              return TRUE;
            }
          });
          if ($feedback_option) {
            // Get only first feedback.
            $feedback_option = reset($feedback_option);
            $feedback['feedback'] = [
              '#type' => 'container',
              '#attributes' => [
                'id' => 'module-feedback',
              ],
              [
                '#type' => 'html_tag',
                '#tag' => 'h4',
                '#attributes' => [
                  'class' => ['feedback-summary'],
                ],
                '#value' => $feedback_option->option_summary,
              ],
            ];
            // Put feedback in the beginning of array.
            array_unshift($content, $feedback);
          }
        }

        // Check if user has not results for module but already finished that skills tree.
        if ($moduleHandler->moduleExists('opigno_skills_system') && $opigno_module->getSkillsActive() && $user_module_status->isFinished()) {
          $db_connection = \Drupal::service('database');

          // Get current user's skills.
          $uid = $this->currentUser()->id();
          $query = $db_connection
            ->select('opigno_skills_statistic', 'o_s_s');
          $query->fields('o_s_s', ['tid', 'score', 'progress', 'stage']);
          $query->condition('o_s_s.uid', $uid);
          $user_skills = $query
            ->execute()
            ->fetchAllAssoc('tid');

          // Check if user already finished that skills tree.
          $successfully_finished = TRUE;

          foreach ($skills_tree as $skill) {
            if (!isset($user_skills[$skill->tid]) || $user_skills[$skill->tid]->stage != 0) {
              $successfully_finished = FALSE;
            }
          }

          $target_skill_entity = $term_storage->load($target_skill);
          if ($target_skill_entity) {
            $target_skill_name = $target_skill_entity->getName();
          }
          else {
            $target_skill_name = $this->t('N/A');
          }

          if (isset($_SESSION['successfully_finished'])) {
            if ($successfully_finished) {
              if ($_SESSION['successfully_finished'] == TRUE) {
                $message = $this->t('Congratulations, you master all the skills required by "@target_skill" skill\'s tree.', ['@target_skill' => $target_skill_name]);
              }
              elseif ($user_module_status->getOwnerId() == $uid) {
                $message = $this->t('You already master all the skills required by "@target_skill" skill\'s tree.', ['@target_skill' => $target_skill_name]);
              }
            }
            else {
              $message = $this->t('There are no activities for you right now from the "@target_skill" skill\'s tree. Try again later.', ['@target_skill' => $target_skill_name]);
            }

            $message = [
              '#type' => 'container',
              '#markup' => $message,
              '#attributes' => [
                'class' => ['form-module-results-message'],
              ],
            ];

            unset($_SESSION['successfully_finished']);
            array_unshift($content, $message);
            $content['#attached']['library'][] = 'opigno_skills_system/opigno_skills_entity';
          }
        }

        // Check if all activities has 0 score.
        // If has - immediately redirect to next step.
        $has_min_score = FALSE;
        foreach ($module_activities as $activity) {
          if (isset($activity->max_score) && $activity->max_score > 0) {
            $has_min_score = TRUE;
            break;
          }
        }
        // Redirect if module has all activities with 0 min score
        // and HideResults option enabled.
        if (!$has_min_score && $opigno_module->getHideResults()) {
          if (!$is_last && !in_array($current_step['typology'], ['Meeting', 'ITL'])) {
            // Redirect to next step.
            return $this->redirect('opigno_learning_path.steps.next', ['group' => $gid, 'parent_content' => $current_step['cid']]);
          }
          else {
            // Redirect to homepage.
            return $this->redirect('entity.group.canonical', ['group' => $gid]);
          }
        }
        // Get link.
        $content[] = $this->getLinkOptions(FALSE, $gid, $is_last, $current_step);
      }
      elseif ($freeNavigation) {
        // Get link.
        $content[] = $this->getLinkOptions(TRUE, $gid);
      }
    }

    return $content;
  }

  /**
   * Get bottom options link.
   */
  private function getLinkOptions($free_navigation, $gid, $is_last = FALSE, $current_step = []): array {
    $link = [];
    $skip_links = \Drupal::request()->query->get('skip-links');
    // Do not show buttons if they do not needed.
    if ($skip_links !== NULL) {
      return $link;
    }
    // Create next/back links.
    $typology = [
      'Meeting',
      'ITL',
    ];
    $options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-success',
        ],
        'id' => 'edit-submit',
      ],
    ];
    $link['form-actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-actions'],
        'id' => 'edit-actions',
      ],
    ];
    if ($free_navigation || ($is_last || in_array($current_step['typology'], $typology))) {
      $title = $this->t('Back to training homepage');
      $route = 'entity.group.canonical';
      $route_params = [
        'group' => $gid,
      ];
    }
    else {
      $title = t('Next');
      $route = 'opigno_learning_path.steps.next';
      $route_params = [
        'group' => $gid,
        'parent_content' => $current_step['cid'],
      ];
    }
    $link['form-actions'][] = Link::createFromRoute(
      $title,
      $route,
      $route_params,
      $options
    )->toRenderable();
    return $link;
  }

  /**
   * This method get parent module for current module if exist.
   */
  public function moduleGetPrevious(OpignoModuleInterface $opigno_module) {
    $uid = $this->currentUser()->id();

    $cid = OpignoGroupContext::getCurrentGroupContentId();
    $gid = OpignoGroupContext::getCurrentGroupId();

    // Load group steps.
    $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid);

    // Find current & next step.
    $count = count($steps);
    $previous_step = NULL;
    for ($i = 0; $i < $count; ++$i) {
      if ($steps[$i]['cid'] == $cid) {
        $previous_step = $steps[$i - 1];
        break;
      }
    }

    // Get module.
    $previous_module = OpignoModule::load($previous_step['id']);
    // Get all user module attempts.
    $user_attempts = $previous_module->getModuleAttempts($this->currentUser());
    // Get last active attempt.
    $active_attempt = $previous_module->getModuleActiveAttempt($this->currentUser());
    // Check Module attempts.
    $allowed_attempts = $previous_module->get('takes')->value;
    if ($allowed_attempts > 0) {
      // It means, that module attempts are limited.
      // Need to check User attempts.
      if (count($user_attempts) >= $allowed_attempts) {
        // User has more attempts then allowed.
        // Check for not finished attempt.
        if ($active_attempt == NULL) {
          // There is no not finished attempt.
          \Drupal::messenger()->addWarning($this->t('Maximum attempts for this module reached.'));
          return $this->redirect('opigno_module.take_module', [
            'group' => $gid,
            'opigno_module' => $opigno_module->id(),
          ]);
        }
      }
    }
    // Take into account randomization options.
    $randomization = $previous_module->getRandomization();
    if ($randomization > 0) {
      // @todo: notify user that he will lost his previous module results. Try do this with ajax.
      \Drupal::messenger()->addWarning($this->t("You can't navigate back to the module with random activities order."));
      return $this->redirect('opigno_module.take_module', [
        'group' => $gid,
        'opigno_module' => $opigno_module->id(),
      ]);
    }
    // Check if user allowed to resume.
    $allow_resume = $previous_module->getAllowResume();
    // Continue param will exist only after previous answer form submit.
    if (!$allow_resume) {
      // Module can't be resumed.
      \Drupal::messenger()->addWarning($this->t('Module resume is not allowed.'));
      // After finish existing attempt we will redirect again
      // to take page to start new attempt.
      return $this->redirect('opigno_module.take_module', [
        'group' => $gid,
        'opigno_module' => $opigno_module->id(),
      ]);
    }
    // Get activities from the Module.
    $activities = $previous_module->getModuleActivities();
    if (count($activities) > 0) {
      if ($user_attempts == NULL) {
        // User has not previous module attempts.
        \Drupal::messenger()->addWarning($this->t("You can't navigate back to the module that you don't attempt."));
        return $this->redirect('opigno_module.take_module', [
          'group' => $gid,
          'opigno_module' => $opigno_module->id(),
        ]);
      }
      if ($active_attempt == NULL) {
        // Previous module is finished.
        // Get last finished attempt and make unfinished.
        /* @var \Drupal\opigno_module\Entity\UserModuleStatus $last_attempt */
        $last_attempt = end($user_attempts);
        // Set current activity.
        $current_activity = $last_attempt->getLastActivity();
        $last_attempt->setCurrentActivity($current_activity);
        // Set last activity.
        $last_activity_info = array_slice($activities, -2, 1, TRUE);
        $last_activity = OpignoActivity::load(key($last_activity_info));
        $last_attempt->setLastActivity($last_activity);
        $last_attempt->setFinished(0);
        $last_attempt->save();
      }
      // Update module status for current module.
      $current_module_attempt = $opigno_module->getModuleActiveAttempt($this->currentUser());
      $current_module_attempt->last_activity->target_id = NULL;
      $current_module_attempt->save();
      // Update content id.
      OpignoGroupContext::setCurrentContentId($previous_step['cid']);
      // Redirect to the previous module.
      return $this->redirect('opigno_module.take_module', [
        'group' => $gid,
        'opigno_module' => $previous_module->id(),
      ], ['query' => ['previous' => TRUE]]);
    }

    // Module can't be navigated.
    \Drupal::messenger()->addWarning($this->t('Can not navigate to previous module.'));
    return $this->redirect('opigno_module.take_module', [
      'group' => $gid,
      'opigno_module' => $opigno_module->id(),
    ]);
  }

  public function accessEntityBrowserGroups() {
    $user = \Drupal::currentUser();

    // Load all user's groups and check is he has a group content manager role
    $is_group_content_manager = FALSE;
    foreach (\Drupal::service('group.membership_loader')->loadByUser($user) as $group) {
      $member_roles = $group->getGroup()->getMember($user)->getRoles();
      if (array_key_exists('learning_path-content_manager', $member_roles)) {
        $is_group_content_manager = TRUE;
      }
    }

    if ($is_group_content_manager
      || array_key_exists('administrator', $user->getRoles())
      || $user->hasPermission('access media_entity_browser_groups entity browser pages')) {
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }
}
