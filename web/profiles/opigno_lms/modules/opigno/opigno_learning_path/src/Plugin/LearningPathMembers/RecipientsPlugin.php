<?php

namespace Drupal\opigno_learning_path\Plugin\LearningPathMembers;

use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_learning_path\LearningPathMembersPluginBase;
use Drupal\user\Entity\User;

/**
 * Class RecipientsPlugin.
 *
 * @LearningPathMembers(
 *   id="recipients_plugin",
 * )
 */
class RecipientsPlugin extends LearningPathMembersPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getMembersForm(array &$form, FormStateInterface $form_state, User $current_user) {
    // Get the groups, this allows to filter the available users.
    $classes = opigno_messaging_get_groups('opigno_class');
    $learning_paths = opigno_messaging_get_groups('learning_path');

    $form['filters'] = [
      '#type' => 'container',
      '#weight' => -2,
    ];

    $form['filters']['title'] = [
      '#type' => 'label',
      '#title' => t('Participants'),
    ];

    $form['filters']['class'] = [
      '#type' => 'select',
      '#options' => [
        'All' => t('Filter by class'),
      ],
      '#default_value' => t('All'),
      '#ajax' => [
        'callback' => [$this, 'updateMembersAjax'],
        'event' => 'change',
        'wrapper' => 'users-to-send',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying entry...'),
        ],
      ],
    ];

    $form['filters']['learning_path'] = [
      '#type' => 'select',
      '#wrapper_attributes' => [
        'class' => ['col-6'],
      ],
      '#options' => [
        'All' => t('Filter by training'),
      ],
      '#default_value' => t('All'),
      '#ajax' => [
        'callback' => [$this, 'updateMembersAjax'],
        'event' => 'change',
        'wrapper' => 'users-to-send',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Verifying entry...'),
        ],
      ],
    ];

    $options = [];

    foreach ($classes as $class) {
      $id = $class['entity_id'];
      $options[$id] = $class['title'];
    }

    uasort($options, 'strcasecmp');
    $form['filters']['class']['#options'] += $options;

    $options = [];

    foreach ($learning_paths as $learning_path) {
      $id = $learning_path['entity_id'];
      $options[$id] = $learning_path['title'];
    }

    uasort($options, 'strcasecmp');
    $form['filters']['learning_path']['#options'] += $options;

    // Get the users for the specific group.
    $current_user = \Drupal::currentUser();
    $show_all = $current_user->hasPermission('message anyone regardless of groups');
    $users = opigno_messaging_get_all_recipients($show_all);
    $options = [];

    foreach ($users as $user) {
      $options[$user->id()] = $user->getDisplayName();
    }

    // Remove the current users from the list of users
    // that once can send a message to.
    if (isset($options[$current_user->id()])) {
      unset($options[$current_user->id()]);
    }

    // Sort the users by name.
    uasort($options, 'strcasecmp');

    $form['users_to_send'] = [
      '#title' => t('Select the users you want to send a message to'),
      '#type' => 'multiselect',
      '#options' => $options,
      '#weight' => -1,
      '#prefix' => '<div id="users-to-send">',
      '#suffix' => '</div>',
      // Fixes multiselect issue 2852654.
      '#process' => [
        ['Drupal\multiselect\Element\MultiSelect', 'processSelect'],
      ],
    ];
  }

  /**
   * Custom ajax callback.
   *
   * Updates the list of available users to send message to
   * once the group is changed.
   */
  public static function updateMembersAjax(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    $show_all = $current_user->hasPermission('message anyone regardless of groups');

    // Get the current values that are selected.
    $current_selected_users = $form_state->getValue('users_to_send');

    // Keep the users that were previously selected as selected.
    foreach ($current_selected_users as $uid) {
      $form['users_to_send']['#default_value'][$uid] = $uid;
    }

    // Remove from the list of option users the ones that are not selected.
    foreach ($form['users_to_send']['#options'] as $uid => $name) {
      if (!in_array($uid, $form['users_to_send']['#default_value'])) {
        unset($form['users_to_send']['#options'][$uid]);
      }
    }

    // Add to the users of the new group to the options.
    $class_id = $form_state->getValue('class');
    $learning_path_id = $form_state->getValue('learning_path');

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

    foreach ($users as $user) {
      /** @var \Drupal\user\UserInterface $user */
      $form['users_to_send']['#options'][$user->id()] = $user->getDisplayName();
    }

    // Remove the current users from the list of users
    // that once can send a message to.
    if (isset($form['users_to_send']['#options'][$current_user->id()])) {
      unset($form['users_to_send']['#options'][$current_user->id()]);
    }

    uasort($form['users_to_send']['#options'], 'strcasecmp');
    return $form['users_to_send'];
  }

}
