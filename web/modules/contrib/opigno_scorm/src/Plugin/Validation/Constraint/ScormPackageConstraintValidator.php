<?php

namespace Drupal\opigno_scorm\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ScormPackage constraint.
 */
class ScormPackageConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {

    if (!$item = $items->first()) {
      return;
    }
    $activity = $item->getEntity();

    $scorm_file = $activity->get('opigno_scorm_package')->entity;
    /* @var \Drupal\opigno_scorm\OpignoScorm $scorm_controller */
    $scorm_controller = \Drupal::service('opigno_scorm.scorm');
    $scorm_controller->unzipPackage($scorm_file);
    $extract_dir = 'public://opigno_scorm_extracted/scorm_' . $scorm_file->id();

    // This is a standard: the manifest file will always be here.
    $manifest_file = $extract_dir . '/imsmanifest.xml';
    if (!file_exists($manifest_file)) {
      $this->context->addViolation($constraint->missingManifestFile);
    }
  }

}
