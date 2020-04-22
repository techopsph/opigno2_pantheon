<?php

namespace Drupal\opigno_mobile_app\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Class OpignoMobileController.
 */
class OpignoMobileController extends ControllerBase {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $serializerFormats = [];

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new UsersController object.
   *
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   */
  public function __construct(
    Serializer $serializer,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database) {
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    if ($container->hasParameter('serializer.formats') && $container->has('serializer')) {
      $serializer = $container->get('serializer');
      $formats = $container->getParameter('serializer.formats');
    }
    else {
      $formats = ['json'];
      $encoders = [new JsonEncoder()];
      $serializer = new Serializer([], $encoders);
    }

    return new static(
      $serializer,
      $formats,
      $container->get('logger.factory')->get('opigno_mobile_app'),
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }


  /**
   * Check if Opigno LMS profile is installed.
   *
   * @return JsonResponse $response.
   *   Return JsonResponse object.
   */
  public function checkOpignoInstance() {
    // Array with response.
    $response_data = [];
    // Get installed profile.
    $profile = \Drupal::service('config.factory')->get('core.extension')->get('profile');

    if ($profile !== 'opigno_lms') {
      $response_data['message'] = t('Opigno LMS profile is not installed');
      $response_data['data']['is_valid'] = FALSE;
    }
    else {
      $response_data['data']['is_valid'] = TRUE;
    }

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Enable/disable mobile mode for training steps pages.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function configureMobileMode() {
    // Array with response.
    $response_data = [];
    $request_query = \Drupal::request()->query;
    $mode_status = $request_query->get('mobile_mode');
    if (!is_numeric($mode_status)) {
      return new JsonResponse($response_data, Response::HTTP_BAD_REQUEST);
    }
    // Ensure that we get the number.
    $mode_status = intval($mode_status);
    if ($mode_status === 1) {
      /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
      $store = \Drupal::service('user.private_tempstore')->get('opigno_mobile_app');
      $store->set('mobile_mode', TRUE);
      $response_data['message'] = t('Mobile mode is enabled');
      return new JsonResponse($response_data, Response::HTTP_OK);
    }
    if ($mode_status === 0) {
      /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
      $store = \Drupal::service('user.private_tempstore')->get('opigno_mobile_app');
      $store->delete('mobile_mode');
      $response_data['message'] = t('Mobile mode is disabled');
      return new JsonResponse($response_data, Response::HTTP_OK);
    }


  }

}
