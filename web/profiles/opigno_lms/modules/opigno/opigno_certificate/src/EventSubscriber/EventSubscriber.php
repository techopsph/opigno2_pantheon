<?php

namespace Drupal\opigno_certificate\EventSubscriber;

use Drupal\entity_print\Event\PrintEvents;
use Drupal\group\Entity\Group;
use Drupal\opigno_certificate\Entity\OpignoCertificate;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Dompdf\Autoloader;

/**
 * Class EventSubscriber.
 */
class EventSubscriber implements EventSubscriberInterface {

  /**
   * Sets paper orientation depending to certificate paper_orientation field.
   */
  public function alterConfiguration(GenericEvent $event) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if ($route_name == 'certificate.entity.pdf') {
      $route_params = \Drupal::routeMatch()->getParameters();
      $type = $route_params->get('entity_type');

      if ($type == 'group') {
        $id = $route_params->get('entity_id');
        $group = Group::load($id);

        if ($group) {
          $certificate_id = $group->get('field_certificate')->target_id;
          $certificate = OpignoCertificate::load($certificate_id);

          if ($certificate && $certificate->hasField('paper_orientation')) {
            $orientation = $certificate->get('paper_orientation')->value;
            $configuration = $event->getArgument('configuration');
            $configuration['default_paper_orientation'] = $configuration['orientation'] = !empty($orientation) ? $orientation : 'portrait';
            $event->setArgument('configuration', $configuration);
          }
        }
      }
    }
  }

  /**
   * Loads dompdf.
   */
  public function requireDompdf(GetResponseEvent $event) {
    $dompdf_autoloaders = [
      'libraries/dompdf/src/Autoloader.php',
      'profiles/opigno_lms/libraries/dompdf/src/Autoloader.php',
    ];

    foreach ($dompdf_autoloaders as $dompdf_autoloader) {
      if (file_exists($dompdf_autoloader)) {
        // Load dompdf for the entity_print.
        require_once $dompdf_autoloader;
        Autoloader::register();
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['requireDompdf'];
    $events[PrintEvents::CONFIGURATION_ALTER] = ['alterConfiguration'];
    return $events;
  }

}
