<?php

namespace Drupal\opigno_dashboard;

use Drupal\block\Entity\Block;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class BlockService.
 */
class BlockService implements BlockServiceInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new BlockService object.
   */
  public function __construct() {
  }

  /**
   * Returns all blocks.
   */
  public function getAllBlocks() {
    $blockManager = \Drupal::service('plugin.manager.block');

    return $blockManager->getDefinitions();
  }

  /**
   * Returns available blocks.
   */
  public function getAvailableBlocks() {
    $blocks = $this->getAllBlocks();
    $availables = \Drupal::config('opigno_dashboard.settings')->get('blocks');

    foreach ($blocks as $key1 => &$block) {
      if (!isset($availables[$key1])
      || (isset($availables[$key1]) && !$availables[$key1]['available'])
      ) {
        unset($blocks[$key1]);
      }
      else {
        foreach ($block as &$value) {
          if (is_object($value)) {
            $value = $value->render();
          }
        }

        $blocks[$key1]['id'] = $key1;

        unset(
            $blocks[$key1]['config_dependencies'],
            $blocks[$key1]['class'],
            $blocks[$key1]['provider'],
            $blocks[$key1]['category'],
            $blocks[$key1]['deriver'],
            $blocks[$key1]['context']
          );
      }
    }

    return array_values($blocks);
  }

  /**
   * Returns blocks contents.
   */
  public function getDashboardBlocksContents() {
    $ids = [];
    foreach ($this->getAvailableBlocks() as $block) {
      $ids[] = $block['id'];
    }

    $blocks = [];
    foreach ($ids as $id) {
      $block = Block::load($this->sanitizeId($id));
      if (!$block) {
        // Try to load old version of block.
        $block = Block::load($this->sanitizeIdOld($id));
      }

      if (!empty($block)) {
        $account = \Drupal::currentUser();
        $account_roles = $account->getRoles();
        $block_visibility = $block->getVisibility();
        $role_access = TRUE;

        if (isset($block_visibility['user_role']) && !empty($block_visibility['user_role'])) {
          $role_access = FALSE;

          foreach ($block_visibility['user_role']['roles'] as $block_role) {
            if (in_array($block_role, $account_roles)) {
              $role_access = TRUE;
            }
          }
        }

        if ($block && $role_access) {
          $render = \Drupal::entityTypeManager()
            ->getViewBuilder('block')
            ->view($block);
          $blocks[$id] = \Drupal::service('renderer')->renderRoot($render);
        }
      }
    }

    return $blocks;
  }

  /**
   * Creates blocks instances.
   */
  public function createBlocksInstances() {
    $items = $this->getAvailableBlocks();
    $config = \Drupal::configFactory();
    $theme = $config->get('opigno_dashboard.settings')->get('theme');

    foreach ($items as $item) {
      $id = $this->sanitizeId($item['id']);

      if (!Block::load($id)) {
        $settings = [
          'plugin' => $item['id'],
          'region' => 'content',
          'id' => $id,
          'theme' => isset($theme) ? $theme : $config->get('system.theme')->get('default'),
          'label' => $this->t('Dashboard:') . ' ' . $item['admin_label'],
          'visibility' => [
            'request_path' => [
              'id' => 'request_path',
              'pages' => '<front>',
              'negate' => FALSE,
              'context_mapping' => [],
            ],
          ],
          'weight' => 0,
        ];

        $values = [];
        foreach (['region', 'id', 'theme', 'plugin', 'weight', 'visibility'] as $key) {
          $values[$key] = $settings[$key];
          // Remove extra values that do not belong in the settings array.
          unset($settings[$key]);
        }
        foreach ($values['visibility'] as $id => $visibility) {
          $values['visibility'][$id]['id'] = $id;
        }
        $values['settings'] = $settings;
        $block = Block::create($values);

        $block->save();
      }
    }
  }

  /**
   * Sanitizes ID string.
   */
  public function sanitizeId($id) {
    return 'dashboard_' . str_replace([':', '-'], ['_', '_'], $id);
  }

  /**
   * Sanitizes ID string for legacy blocks.
   */
  public function sanitizeIdOld($id) {
    return 'dashboard_' . str_replace(':', '_', $id);
  }

}
