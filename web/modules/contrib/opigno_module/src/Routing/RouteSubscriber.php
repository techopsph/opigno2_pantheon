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
  }

}
