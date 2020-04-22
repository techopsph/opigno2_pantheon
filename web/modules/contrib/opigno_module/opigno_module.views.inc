<?php

/**
 * @file
 * Opigno module views custom implementation.
 */

/**
 * Implements hook_views_data_alter().
 */
function opigno_module_views_data_alter(array &$data) {
  $data['user_module_status']['opigno_module_evaluation'] = [
    'title' => t('Modules required manual evaluation'),
    'filter' => [
      'title' => t('Modules required manual evaluation'),
      'help' => t('Modules which require manual evaluation filter'),
      'field' => 'module',
      'id' => 'opigno_module_manual_evaluation',
    ],
  ];
}
