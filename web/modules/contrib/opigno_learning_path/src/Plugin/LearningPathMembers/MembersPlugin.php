<?php

namespace Drupal\opigno_learning_path\Plugin\LearningPathMembers;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\opigno_learning_path\LearningPathMembersPluginBase;
use Drupal\user\Entity\User;

/**
 * Class MembersPlugin.
 *
 * @LearningPathMembers(
 *   id="members_plugin",
 * )
 */
class MembersPlugin extends LearningPathMembersPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getMembersForm(array &$form, FormStateInterface $form_state, User $current_user) {
    $storage = $form_state->getStorage();
    // If user can add any other users or only from his groups.
    $show_all = $current_user->hasPermission('add any members to calendar event') ? TRUE : FALSE;
    $storage['show_all'] = $show_all;

    // Add filters for the members field.
    $form['members'] = [
      '#type' => 'container',
      '#weight' => 100,
    ];

    $form['members']['title'] = [
      '#type' => 'label',
      '#title' => t('Members'),
    ];

    $form['members']['filters'] = [
      '#type' => 'container',
    ];

    /** @var \Drupal\group\Entity\Group[] $classes */
    $classes = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['type' => 'opigno_class']);

    $options = [];
    foreach ($classes as $class) {
      if ($show_all || $class->getMember($current_user) !== FALSE) {
        $options[$class->id()] = $class->label();
      }
    }

    uasort($options, 'strcasecmp');
    $options = ['All' => t('Filter by class')] + $options;

    $form['members']['filters']['class'] = [
      '#type' => 'select',
      '#wrapper_attributes' => [
        'class' => [''],
      ],
      '#options' => $options,
      '#default_value' => t('All'),
      '#ajax' => [
        'callback' => [$this, 'updateMembersAjax'],
        'event' => 'change',
        'wrapper' => 'members',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying entry...'),
        ],
      ],
    ];

    /** @var \Drupal\group\Entity\Group[] $trainings */
    $trainings = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->loadByProperties(['type' => 'learning_path']);

    $options = [];
    foreach ($trainings as $training) {
      if ($show_all || $training->getMember($current_user) !== FALSE) {
        $options[$training->id()] = $training->label();
      }
    }

    uasort($options, 'strcasecmp');
    $options = ['All' => t('Filter by training')] + $options;

    $form['members']['filters']['training'] = [
      '#type' => 'select',
      '#wrapper_attributes' => [
        'class' => [''],
      ],
      '#options' => $options,
      '#default_value' => t('All'),
      '#ajax' => [
        'callback' => [$this, 'updateMembersAjax'],
        'event' => 'change',
        'wrapper' => 'members',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying entry...'),
        ],
      ],
    ];

    // Get the users for the specific group.
    $users = opigno_messaging_get_all_recipients($show_all);
    $allowed_uids = [];

    foreach ($users as $user) {
      $allowed_uids[] = $user->id();
    }

    if ($allowed_uids) {
      $allowed_uids = array_unique($allowed_uids);

      // Save to form storage.
      $storage['allowed_uids'] = $allowed_uids;

      // Filter allowed users.
      if ($options = $form["field_calendar_event_members"]["widget"]["#options"]) {
        foreach ($options as $key => $option) {
          if (!in_array($key, $allowed_uids)) {
            unset($form["field_calendar_event_members"]["widget"]["#options"][$key]);
          }
        }
      }
    }

    $form['members']['field_calendar_event_members'] = $form['field_calendar_event_members'];
    unset($form['field_calendar_event_members']);

    $members = &$form['members']['field_calendar_event_members'];
    $members['#prefix'] = '<div id="members">';
    $members['#suffix'] = '</div>';
    unset($members['widget']['#title']);

    $form_state->setStorage($storage);

    if (!$current_user->hasPermission('add members to calendar event')) {
      // Hide calendar events members field.
      if (!empty($form["field_calendar_event_members"])) {
        $form["field_calendar_event_members"]["#access"] = FALSE;
      }
      if (!empty($form['members'])) {
        $form['members']['#access'] = FALSE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function updateMembersAjax(array $form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $members_wrapper = &$form['members']['field_calendar_event_members'];
    $members = &$members_wrapper['widget'];

    $current_user = \Drupal::currentUser();
    $show_all = $current_user->hasPermission('add any members to calendar event') ? TRUE : FALSE;

    // Get the current values that are selected.
    $current_selected_users = $form_state->getValue('field_calendar_event_members');

    // Keep the users that were previously selected as selected.
    foreach ($current_selected_users as $user) {
      $members['#default_value'][$user['target_id']] = $user['target_id'];
    }

    // Remove from the list of option users the ones that are not selected.
    foreach ($members['#options'] as $uid => $name) {
      if (!in_array($uid, $members['#default_value'])) {
        unset($members['#options'][$uid]);
      }
    }

    // Add to the users of the new group to the options.
    $class_id = $form_state->getValue('class');
    $learning_path_id = $form_state->getValue('training');

    if (!is_numeric($class_id)) {
      $class_id = 0;
    }

    if (!is_numeric($learning_path_id)) {
      $learning_path_id = 0;
    }

    $class_users = opigno_messaging_get_user_for_group($class_id, $show_all);
    $learning_path_users = opigno_messaging_get_user_for_group($learning_path_id, $show_all);

    if (!empty($class_id) && !empty($learning_path_id)) {
      // Chosen both class or training.
      $users = array_uintersect($class_users, $learning_path_users, function ($user1, $user2) {
        /** @var \Drupal\user\UserInterface $user1 */
        /** @var \Drupal\user\UserInterface $user2 */
        return $user2->id() - $user1->id();
      });
    }
    elseif (!empty($class_id) || !empty($learning_path_id)) {
      // Chosen only class or training.
      $users = array_merge($class_id ? $class_users : [], $learning_path_id ? $learning_path_users : []);
    }
    else {
      // No class or training were chosen.
      $users = opigno_messaging_get_all_recipients($show_all);
    }

    $allowed_uids = $storage["allowed_uids"];

    // Filter members options.
    foreach ($users as $user) {
      $uid = $user->id();
      if (!empty($storage['show_all'])) {
        // Add all the members.
        /** @var \Drupal\user\UserInterface $user */
        $members['#options'][$user->id()] = $user->getDisplayName();
      }
      elseif ($allowed_uids && in_array($uid, $allowed_uids)) {
        // Add only members from current user groups.
        /** @var \Drupal\user\UserInterface $user */
        $members['#options'][$user->id()] = $user->getDisplayName();
      }
    }

    $user = \Drupal::currentUser();
    $uid = $user->id();
    if (!empty($members["#options"]) && array_key_exists($uid, $members["#options"])) {
      unset($members["#options"][$uid]);
    }

    uasort($members['#options'], 'strcasecmp');
    return $members_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupMembersIds(Group $group) {
    if ($group_members = $group->getMembers()) {
      $group_members = array_map(function ($member) {
        /** @var \Drupal\group\GroupMembership $member */
        $user = $member->getUser();
        return $user->id();
      }, $group_members);

      if ($group_members) {
        return $group_members;
      }
    }

    return NULL;
  }

}
