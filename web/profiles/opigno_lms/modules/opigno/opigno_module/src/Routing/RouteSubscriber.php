<?php

namespace Drupal\opigno_module\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('entity.opigno_module.collection')) {
      $route->setPath('/admin/structure/opigno-modules');
    }

    if ($route = $collection->get('entity.opigno_activity.collection')) {
      $route->setPath('/admin/structure/opigno-activities');
    }

    if ($route = $collection->get('entity.group.collection')) {
      $route->setPath('/admin/structure/groups');
    }

    if ($route = $collection->get('private_message.private_message_page')) {
      $route->setDefault('_title', 'Discussion threads');
    }

    // Rewrite permissions for Drupal\entity_browser\Entity\EntityBrowser::route() to allow group content manager add an image to a module
    if ($route = $collection->get('entity_browser.media_entity_browser_groups')) {
      $route->setRequirements(['_custom_access' => '\Drupal\opigno_module\Controller\OpignoModuleController::accessEntityBrowserGroups']);
    }
  }

}
