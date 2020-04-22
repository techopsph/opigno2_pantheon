<?php

namespace Drupal\opigno_group_manager\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\group\Entity\Group;

/**
 * Defines the Opigno Group Content entity.
 *
 * @ingroup opigno_group_manager
 *
 * @ContentEntityType(
 *   id = "opigno_group_content",
 *   label = @Translation("Opigno Group Content"),
 *   base_table = "opigno_group_content",
 *   entity_keys = {
 *     "id" = "id",
 *     "group_id" = "group_id",
 *     "group_content_type_id" = "group_content_type_id",
 *     "entity_id" = "entity_id",
 *     "success_score_min" = "success_score_min",
 *     "is_mandatory" = "is_mandatory",
 *     "coordinate_x" = "coordinate_x",
 *     "coordinate_y" = "coordinate_y",
 *     "in_skills_system" = "in_skills_system",
 *   }
 * )
 */
class OpignoGroupManagedContent extends ContentEntityBase {

  /**
   * Helper to create a new LPManagedContent with the values passed in param.
   *
   * It's not saved automatically. You need to do $obj->save().
   *
   * @param int $group_id
   *   The learning path group ID.
   * @param string $group_content_type_id
   *   The content type plugin ID.
   * @param int $entity_id
   *   The drupal entity ID.
   * @param int $success_score_min
   *   The minimum success score to pass the learning path.
   * @param int $is_mandatory
   *   Set if the content is mandatory to pass the learning path.
   * @param int $coordinate_x
   *   The X coordinate for this content in the learning path.
   * @param int $coordinate_y
   *   The Y coordinate for this content in the learning path.
   * @param int $in_skills_system
   *   Check if the content in the skills system.
   *
   * @return \Drupal\Core\Entity\EntityInterface|self
   *   LPManagedContent object.
   */
  public static function createWithValues(
    $group_id,
    $group_content_type_id,
    $entity_id,
    $success_score_min = 0,
    $is_mandatory = 0,
    $coordinate_x = 0,
    $coordinate_y = 0,
    $in_skills_system = 0
  ) {
    $values = [
      'group_id' => $group_id,
      'group_content_type_id' => $group_content_type_id,
      'entity_id' => $entity_id,
      'success_score_min' => $success_score_min,
      'is_mandatory' => $is_mandatory,
      'coordinate_x' => $coordinate_x,
      'coordinate_y' => $coordinate_y,
      'in_skills_system' => $in_skills_system,
    ];

    return parent::create($values);
  }

  /**
   * Returns group entity ID.
   *
   * @return int
   *   The group entity ID.
   */
  public function getGroupId() {
    return $this->get('group_id')->target_id;
  }

  /**
   * Sets group entity ID.
   *
   * @param int $group_id
   *   The Group entity ID.
   *
   * @return $this
   */
  public function setGroupId($group_id) {
    $this->set('group_id', $group_id);
    return $this;
  }

  /**
   * Returns group.
   *
   * @return \Drupal\group\Entity\Group
   *   Group.
   */
  public function getGroup() {
    return $this->get('group_id')->entity;
  }

  /**
   * Sets group ID.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group entity.
   *
   * @return $this
   */
  public function setGroup(Group $group) {
    $this->setGroupId($group->id());
    return $this;
  }

  /**
   * Returns group content type ID.
   *
   * @return string
   *   The group content type plugin ID.
   */
  public function getGroupContentTypeId() {
    return $this->get('group_content_type_id')->value;
  }

  /**
   * Sets group content type ID.
   *
   * @param string $group_content_type_id
   *   The group content type plugin ID.
   *
   * @return $this
   */
  public function setGroupContentTypeId($group_content_type_id) {
    $this->set('group_content_type_id', $group_content_type_id);
    return $this;
  }

  /**
   * Returns entity ID.
   *
   * @return int
   *   The drupal entity ID.
   */
  public function getEntityId() {
    return $this->get('entity_id')->value;
  }

  /**
   * Sets entity ID.
   *
   * @param int $entity_id
   *   The drupal entity ID.
   *
   * @return $this
   */
  public function setEntityId($entity_id) {
    $this->set('entity_id', $entity_id);
    return $this;
  }

  /**
   * Returns success score min.
   *
   * @return int
   *   The minimum score to success this learning path.
   */
  public function getSuccessScoreMin() {
    return $this->get('success_score_min')->value;
  }

  /**
   * Sets success score min.
   *
   * @param int $success_score_min
   *   The minimum score to success this learning path,.
   *
   * @return $this
   */
  public function setSuccessScoreMin($success_score_min) {
    $this->set('success_score_min', $success_score_min);
    return $this;
  }

  /**
   * Returns mandatory flag.
   *
   * @return bool
   *   TRUE if this content is mandatory to success this learning path.
   *   FALSE otherwise.
   */
  public function isMandatory() {
    return $this->get('is_mandatory')->value;
  }

  /**
   * Returns skill flag.
   *
   * @return bool
   *   TRUE if this content is in skill system.
   *   FALSE otherwise.
   */
  public function isInSkillSystem() {
    return $this->get('in_skills_system')->value;
  }

  /**
   * Set skill flag.
   *
   * @param bool $is_InSkillSystem
   *   TRUE if this content is in skill system.
   *   FALSE otherwise.
   *
   * @return $this
   */
  public function setSkillSystem($is_InSkillSystem) {
    $this->set('in_skills_system', $is_InSkillSystem)->value;
    return $this;
  }

  /**
   * Sets mandatory flag.
   *
   * @param bool $is_mandatory
   *   TRUE if this content is mandatory to success this learning path.
   *   FALSE otherwise.
   *
   * @return $this
   */
  public function setMandatory($is_mandatory) {
    $this->set('is_mandatory', $is_mandatory);
    return $this;
  }

  /**
   * Returns X coordinate.
   *
   * @return int
   *   The X coordinate.
   */
  public function getCoordinateX() {
    return $this->get('coordinate_x')->value;
  }

  /**
   * Sets X coordinate.
   *
   * @param int $coordinate_x
   *   The X coordinate.
   *
   * @return $this
   */
  public function setCoordinateX($coordinate_x) {
    $this->set('coordinate_x', $coordinate_x);
    return $this;
  }

  /**
   * Returns Y coordinate.
   *
   * @return int
   *   The Y coordinate.
   */
  public function getCoordinateY() {
    return $this->get('coordinate_y')->value;
  }

  /**
   * Sets Y coordinate.
   *
   * @param int $coordinate_y
   *   The Y coordinate.
   *
   * @return $this
   */
  public function setCoordinateY($coordinate_y) {
    $this->set('coordinate_y', $coordinate_y);
    return $this;
  }

  /**
   * Returns parents links.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|OpignoGroupManagedLink[]
   *   Parents links.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getParentsLinks() {
    return OpignoGroupManagedLink::loadByProperties([
      'group_id' => $this->getGroupId(),
      'child_content_id' => $this->id(),
    ]);
  }

  /**
   * Returns children links.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|OpignoGroupManagedLink[]
   *   Children links.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getChildrenLinks() {
    return OpignoGroupManagedLink::loadByProperties([
      'group_id' => $this->getGroupId(),
      'parent_content_id' => $this->id(),
    ]);
  }

  /**
   * Checks if this content has a child.
   *
   * @return bool
   *   TRUE if this content has a child. FALSE otherwise.
   *
   * @throws InvalidPluginDefinitionException
   */
  public function hasChildLink() {
    return !empty($this->getChildrenLinks());
  }

  /**
   * Get the content type object of this content.
   *
   * @return \Drupal\opigno_group_manager\ContentTypeBase|object
   *   Group content type.
   */
  public function getGroupContentType() {
    $manager = \Drupal::getContainer()->get('opigno_group_manager.content_types.manager');
    return $manager->createInstance($this->getGroupContentTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['group_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Group')
      ->setCardinality(1)
      ->setSetting('target_type', 'group');

    $fields['group_content_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel('Group Content Type ID')
      ->setDescription('The content type ID (should be a plugin ID)');

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel('Entity ID')
      ->setDescription('The entity ID');

    $fields['success_score_min'] = BaseFieldDefinition::create('integer')
      ->setLabel('Success score minimum')
      ->setDescription('The minimum score to success this content')
      ->setDefaultValue(0);

    $fields['is_mandatory'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Is mandatory')
      ->setDescription('Indicate if this content is mandatory to succeed the Group')
      ->setDefaultValue(FALSE);

    $fields['coordinate_x'] = BaseFieldDefinition::create('integer')
      ->setLabel('Coordinate X')
      ->setDescription('The X coordinate in this Group manager')
      ->setDefaultValue(0);

    $fields['coordinate_y'] = BaseFieldDefinition::create('integer')
      ->setLabel('Coordinate Y')
      ->setDescription('The Y coordinate in this Group manager')
      ->setDefaultValue(0);

    $fields['in_skills_system'] = BaseFieldDefinition::create('boolean')
      ->setLabel('In the sills system')
      ->setDescription('Indicate if this module in the skills system')
      ->setDefaultValue(FALSE);

    return $fields;
  }

  /**
   * Load one or more LPManagedContent filtered by the properties.
   *
   * The available properties are the entity_keys specified
   * in the header of this LPManagedContent class.
   *
   * Best is to avoid to use this method
   * and create a specific method for your search,
   * like the method loadByLearningPathId.
   *
   * @param array $properties
   *   The properties to search for.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|self[]
   *   LPManagedContent objects.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see LPManagedContent::loadByLearningPathId()
   */
  public static function loadByProperties(array $properties) {
    return \Drupal::entityTypeManager()->getStorage('opigno_group_content')->loadByProperties($properties);
  }

  /**
   * Load the contents linked to a specific group.
   *
   * @param int $group_id
   *   The Group entity ID.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]|self[]
   *   Group managed content object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function loadByGroupId($group_id) {
    try {
      return self::loadByProperties(['group_id' => $group_id]);
    }
    catch (InvalidPluginDefinitionException $e) {
      return [];
    }
  }

  /**
   * Deletes the content from database.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete() {
    // First, delete all the links associated to this content.
    $as_child_links = OpignoGroupManagedLink::loadByProperties(['group_id' => $this->getGroupId(), 'child_content_id' => $this->id()]);
    $as_parent_links = OpignoGroupManagedLink::loadByProperties(['group_id' => $this->getGroupId(), 'parent_content_id' => $this->id()]);
    /** @var OpignoGroupManagedLink[] $all_links */
    $all_links = array_merge($as_child_links, $as_parent_links);

    // TODO: Maybe use the entityStorage to bulk delete ?
    // Delete the links.
    foreach ($all_links as $link) {
      $link->delete();
    }

    parent::delete();
  }

  /**
   * Returns first step.
   */
  public static function getFirstStep($learning_path_id) {
    // The first step is the content who has no parents.
    // First, get all the contents.
    $contents = self::loadByGroupId($learning_path_id);

    // Then, check which content has no parent link.
    foreach ($contents as $content) {
      $parents = $content->getParentsLinks();
      if (empty($parents)) {
        return $content;
      }
    }

    return FALSE;
  }

  /**
   * Get the next LPManagedContent object according to the user score.
   *
   * @param int $user_score
   *   The user score for this content.
   * @param mixed $attempts
   *   Module attempts.
   * @param mixed $module
   *   Module object.
   * @param null|bool $guidedNavigation
   *   Group guided navigation option.
   * @param null|string $type_id
   *   Step type ID.
   *
   * @return bool|OpignoGroupManagedContent
   *   FALSE if no next content.
   *   The next OpignoGroupManagedContent if there is a next content.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNextStep($user_score, $attempts = NULL, $module = NULL, $guidedNavigation = NULL, $type_id = NULL) {
    if ($type_id != 'ContentTypeCourse') {
      $_SESSION['step_required_conditions_failed'] = FALSE;
    }

    // Get the child link that has the required_score
    // higher than the $score param
    // and that has the higher required_score.
    $db_connection = \Drupal::service('database');
    $query = $db_connection->select('opigno_group_link', 'ogl');
    $query->fields('ogl', ['id', 'required_score']);
    $query->condition('parent_content_id', $this->id());
    $query->condition('required_score', $user_score, '<=');
    $query->orderBy('required_score', 'DESC');

    // To preserve the same order between multiple queries
    // when the required_score is equal.
    $query->orderBy('id');
    $query->execute()->fetchAllAssoc('id');
    $query->range(0, 1);
    $result = $query->execute()->fetchObject();

    $no_conditions_met = TRUE;
    if ($result) {
      $next_step_id = $result->id;
      if ($result->required_score > 0) {
        // Required score achieved.
        $no_conditions_met = FALSE;
      }
    }

    // Get required conditions.
    $required_conditions = $db_connection->select('opigno_group_link', 'ogl')
      ->fields('ogl', ['id', 'required_score', 'required_activities'])
      ->condition('parent_content_id', $this->id())
      ->condition('group_id', $this->getGroupId())
      ->execute()->fetchAllAssoc('id');

    if ($attempts) {
      $last_attempt = $attempts[max(array_keys($attempts))];
      // Handle user score depending to keeping results option.
      if (in_array($module->getKeepResultsOption(), ['newest', 'all'])) {
        $user_score = $last_attempt->getScore();
      }
    }

    // Set branching mode flag.
    $required_flag = FALSE;
    $min_score = $this->getSuccessScoreMin();
    if ($this->isMandatory()) {
      if ($min_score === '0' || $user_score >= $min_score) {
        $required_flag = TRUE;
      }
    }
    else {
      $required_flag = TRUE;
    }

    // Handle LP modules links conditions.
    // Required score and activities/answers.
    if ($required_flag && $required_conditions && $attempts) {
      if (empty($last_attempt)) {
        $last_attempt = $attempts[max(array_keys($attempts))];
      }

      // Check required conditions only for those
      // trainings which have guided navigation option on.
      if (isset($guidedNavigation) && $guidedNavigation) {
        // Check required conditions.
        foreach ($required_conditions as $required_condition) {
          $required_activities = $required_condition->required_activities
            ? unserialize($required_condition->required_activities) : NULL;
          $required_score = $required_condition->required_score;

          // Required score depending to module last attempt.
          $check_required_activities = TRUE;
          if ($required_score && $user_score < $required_score) {
            $no_conditions_met = TRUE;
            $next_step_id = NULL;
            $check_required_activities = FALSE;
          }

          // Check required activities if they were set.
          if ($check_required_activities && $required_activities) {
            $successful_activities = $this->getSuccessfulRequiredActvities($required_activities, $last_attempt->id());
            if ($successful_activities) {
              $success = TRUE;
              foreach ($required_activities as $required_activity) {
                if (!in_array($required_activity, $successful_activities)) {
                  $success = FALSE;
                  break;
                }
              }

              if ($success && count($required_activities) == count($successful_activities)) {
                // Set next step if all required activities were successful.
                $next_step_id = $required_condition->id;
                $no_conditions_met = FALSE;
                break;
              }
            }
          }
        }
      }

      if ($no_conditions_met) {
        // If no conditions met check for
        // activities without required conditions.
        $no_required_free = TRUE;
        foreach ($required_conditions as $required_condition) {
          if ($required_condition->required_score == '0' && empty($required_condition->required_activities)) {
            $next_step_id = $required_condition->id;
            $no_required_free = FALSE;
            break;
          }
        }
        // No activities without required conditions found.
        if ($no_required_free) {
          $next_step_id = NULL;
          $_SESSION['step_required_conditions_failed'] = TRUE;
        }
      }
    }

    if (!empty($next_step_id)) {
      // If a result is found, return the next content object.
      $next_step_link = OpignoGroupManagedLink::load($next_step_id);
      if ($next_step_link) {
        return $next_step_link->getChildContent();
      }
    }

    return FALSE;
  }

  /**
   * Returns successful required activities array.
   *
   * @param mixed $required_activities
   *   Mapped array of required activities.
   * @param int $last_attempt_id
   *   Module last attempt id.
   *
   * @return array
   *   Successful required activities array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSuccessfulRequiredActvities($required_activities, $last_attempt_id) {
    $successful_activities = [];
    $module_id = $this->getEntityId();

    // Create activities hierarchical array.
    $required_activities_array = [];
    foreach ($required_activities as $id) {
      if (strpos($id, '-') !== FALSE) {
        list($activity_id, $answer_id) = explode('-', $id);
      }
      else {
        $activity_id = $id;
      }
      if (!array_key_exists($activity_id, $required_activities_array)) {
        $required_activities_array[$activity_id] = [];
      }
      if (isset($answer_id)) {
        $required_activities_array[$activity_id][$answer_id] = $answer_id;
      }
    }

    // Get successful activities and answers.
    foreach ($required_activities_array as $id => $required_activity) {
      $answer = \Drupal::entityTypeManager()
        ->getStorage('opigno_answer')
        ->loadByProperties([
          'user_module_status' => $last_attempt_id,
          'activity' => $id,
          'module' => $module_id,
        ]);

      if ($answer) {
        $user_responses_array = [];
        $answer = array_pop($answer);
        $xapi_data = json_decode($answer->field_xapidata->value);

        if (empty($required_activity)) {
          // If no required answers in activity.
          // Check activity is successful.
          if (!empty($xapi_data->statement->result) &&
            $xapi_data->statement->result->completion &&
            $xapi_data->statement->result->success) {

            if (!in_array($id, $successful_activities)) {
              // Add successful activity.
              $successful_activities[] = (string) $id;
            }
          }
        }
        else {
          // If there are required answers in activity.
          if (!in_array($id, $successful_activities)) {
            // Add successful activity.
            $successful_activities[] = (string) $id;
          }

          $user_response = explode('[,]', $xapi_data->statement->result->response);
          if ($user_response) {
            if (!in_array($id, $user_responses_array)) {
              $user_responses_array[] = (string) $id;
            }
            foreach ($user_response as $item) {
              if ($item === 'true') {
                $item = '0';
              }
              if ($item === 'false') {
                $item = '1';
              }
              $user_responses_array[] = $id . '-' . $item;
              $successful_activities[] = $id . '-' . $item;
            }
          }
        }
      }
    }

    // Check if user successful answers satisfy required activities.
    $success = TRUE;
    foreach ($successful_activities as $successful_activitiy) {
      if (!in_array($successful_activitiy, $required_activities)) {
        $success = FALSE;
        break;
      }
    }
    if (!($success && count($required_activities) == count($successful_activities))) {
      // Block the step if wrong answer presents.
      $successful_activities = [];
    }

    return $successful_activities;
  }

}
