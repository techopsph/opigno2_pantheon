<?php

namespace Drupal\opigno_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for all the actions of the Learning Path manager app.
 */
class DashboardController extends ControllerBase {

  public function dashboardDefaultBlocks() {
    return [];
  }

  /**
   * Returns positioning.
   */
  public function getPositioning($uid = NULL, $default = FALSE) {
    if (empty($uid)) {
      $current_user = \Drupal::currentUser();
      if (!$current_user) {
        return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
      }
      $uid = $current_user->id();
    }

    $availables = \Drupal::service('opigno_dashboard.block')->getAvailableBlocks();
    $connection = \Drupal::database();

    // Get default configuration.
    $config_default = $this->config('opigno_dashboard.default.settings');
    $default_positions = json_decode($config_default->get('positions'), TRUE);
    $default_columns = $config_default->get('columns');

    if ($default) {
      $positions = $default_positions;
      $columns = $default_columns;
    }
    else {
      $query = $connection->select('opigno_dashboard_positioning', 'p')
        ->fields('p', ['columns', 'positions'])
        ->condition('p.uid', $uid);

      $result = $query->execute()->fetchObject();
      $positions = FALSE;
      if (!empty($result->positions)) {
        $positions = json_decode($result->positions, TRUE);
      }
      $columns = !empty($result->columns) ? $result->columns : 3;
    }

    if (!$positions) {
      if (!empty($default_positions)) {
        $positions = $default_positions;
        $columns = $default_columns;
      }
      else {
        $positions = json_decode(OPIGNO_DASHBOARD_DEFAULT_CONFIG, TRUE);
        $columns = 3;
      }
    }

    // Get mandatory blocks.
    $config = $this->config('opigno_dashboard.settings');
    $mandatory_blocks = $config->get('blocks');
    if (!empty($mandatory_blocks)) {
      $mandatory_blocks = array_filter($mandatory_blocks, function ($block) {
        return $block['available'] && $block['mandatory'];
      });
    }
    // Keep all mandatory blocks.
    $mandatory = !empty($mandatory_blocks) ? $mandatory_blocks : [];

    // Remove blocks not availables.
    $availables_keys = [];
    foreach ($availables as $available) {
      $availables_keys[$available['id']] = $available['id'];
    }
    foreach ($positions as $key1 => $column) {
      foreach ($column as $key2 => $row) {
        if (!in_array($row['id'], $availables_keys)) {
          unset($positions[$key1][$key2]);
        }
        // Filter unused mandatory blocks.
        if (!empty($mandatory_blocks) && isset($mandatory_blocks[$row['id']])) {
          unset($mandatory_blocks[$row['id']]);
        }
        // Add mandatory property to positions blocks.
        $positions[$key1][$key2]['mandatory'] = ($mandatory && array_key_exists($row['id'], $mandatory)) ? TRUE : FALSE;
      }
    }

    // Remove block already used.
    foreach ($availables as $key => $value) {
      foreach ($positions as $column) {
        foreach ($column as $row) {
          if ($row['id'] == $value['id']) {
            unset($availables[$key]);
          }
        }
      }
      // Save mandatory blocks key from "availables" array.
      if (!empty($mandatory_blocks) && array_key_exists($value['id'], $mandatory_blocks)) {
        $mandatory_blocks[$value['id']]['availables_key'] = $key;
      }
    }

    $entities = array_merge([array_values($availables)], $positions);

    $positions = ($entities) ? $entities : array_merge([array_values($availables)], [[], [], []]);

    // Add unused mandatory blocks.
    if (!empty($mandatory_blocks)) {
      foreach ($mandatory_blocks as $id => $mandatory_block) {
        array_unshift($positions[1], [
          'admin_label' => $availables[$mandatory_block['availables_key']]['admin_label'],
          'id' => $id,
          'mandatory' => TRUE,
        ]);
      }
    }

    $columns = !empty($columns) ? $columns : 3;

    if ($default) {
      return [
        'positions' => $positions,
        'columns' => $columns,
      ];
    }
    else {
      return new JsonResponse([
        'positions' => $positions,
        'columns' => $columns,
      ], Response::HTTP_OK);
    }
  }

  /**
   * Sets positioning.
   */
  public function setPositioning(Request $request) {
    $datas = json_decode($request->getContent());
    $connection = \Drupal::database();

    // Remove first column.
    unset($datas->positions[0]);

    $connection->merge('opigno_dashboard_positioning')
      ->key(['uid' => \Drupal::currentUser()->id()])
      ->fields(['columns' => (int) $datas->columns])
      ->fields(['positions' => json_encode($datas->positions)])
      ->execute();

    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Returns blocks contents.
   */
  public function getBlocksContents() {
    $blocks = \Drupal::service('opigno_dashboard.block')->getDashboardBlocksContents();

    return new JsonResponse([
      'blocks' => $blocks,
    ], Response::HTTP_OK);
  }

  /**
   * Returns default positioning.
   */
  public function getDefaultPositioning() {
    $positioning = $this->getPositioning(NULL, TRUE);

    return new JsonResponse([
      'positions' => $positioning['positions'],
      'columns' => $positioning['columns'],
    ], Response::HTTP_OK);
  }

  /**
   * Sets default positioning.
   */
  public function setDefaultPositioning(Request $request) {
    $datas = json_decode($request->getContent());
    unset($datas->positions[0]);

    // Fix critical symbols.
    if (!empty($datas->positions)) {
      foreach ($datas->positions as &$position) {
        if (!empty($position)) {
          foreach ($position as &$block) {
            $block->admin_label = str_replace("'", "`", $block->admin_label);
          }
        }
      }
    }

    try {
      $config = \Drupal::configFactory()->getEditable('opigno_dashboard.default.settings');
      $config->set('positions', json_encode($datas->positions));
      $config->set('columns', (int) $datas->columns);
      $config->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_dashboard')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   *
   */
  public function restoreToDefaultAll() {
    $positioning = $this->getPositioning(NULL, TRUE);
    unset($positioning['positions'][0]);
    $connection = \Drupal::database();

    $uids = \Drupal::entityQuery('user')->execute();
    unset($uids[0]);
    if ($uids) {
      foreach ($uids as $uid) {
        $connection->merge('opigno_dashboard_positioning')
          ->key(['uid' => $uid])
          ->fields([
            'columns' => (int) $positioning['columns'],
            'positions' => json_encode($positioning['positions']),
          ])
          ->execute();
      }
    }

    return new JsonResponse(NULL, Response::HTTP_OK);
  }

}
