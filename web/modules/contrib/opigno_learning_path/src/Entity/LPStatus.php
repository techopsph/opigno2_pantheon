<?php

namespace Drupal\opigno_learning_path\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\opigno_learning_path\LPStatusInterface;

/**
 * Defines the User Learning Path attempt status entity.
 *
 * @ingroup opigno_learning_path
 *
 * @ContentEntityType(
 *   id = "user_lp_status",
 *   label = @Translation("User Learning Path status"),
 *   base_table = "user_lp_status",
 *   entity_keys = {
 *     "id" = "id",
 *     "gid" = "gid",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "started" = "started",
 *     "finished" = "finished",
 *   },
 * )
 */
class LPStatus extends ContentEntityBase implements LPStatusInterface {

  /**
   * Static cache of user attempts.
   *
   * @var mixed
   */
  protected $userAttempts = [];

  /**
   * Static cache of user active attempt.
   *
   * @var mixed
   */
  protected $userActiveAttempt = [];

  /**
   * {@inheritdoc}
   */
  public function getTrainingId() {
    return $this->get('gid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrainingId($id) {
    $this->set('gid', $id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTraining() {
    return $this->get('gid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setTraining($training) {
    $this->set('gid', $training->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setUser(AccountInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    $value = $this->get('status')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore() {
    return $this->get('score')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setScore($value) {
    $this->set('score', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFinished() {
    $value = $this->get('finished')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setFinished($timestamp) {
    $this->set('finished', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isFinished() {
    return (bool) $this->finished->value != 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getStarted() {
    $value = $this->get('started')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStarted($timestamp) {
    $this->set('started', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isStarted() {
    return (bool) $this->started->value != 0;
  }

  /**
   * Returns user training status.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param string $type
   *   Kind of the query result - best|last.
   *
   * @return string
   *   User training status.
   */
  public static function getTrainingStatus($gid, $uid, $type = 'best') {
    $db_connection = \Drupal::service('database');
    try {
      $result = $db_connection->select('user_lp_status', 'lp')
        ->fields('lps', ['status'])
        ->condition('gid', $gid)
        ->condition('uid', $uid)
        ->orderBy('finished', 'DESC')
        ->execute()->fetchCol();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_learning_path')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }

    if (!empty($result)) {
      if ($type == 'best') {
        if (in_array('passed', $result)) {
          return 'passed';
        }
        elseif (in_array('failed', $result)) {
          return 'failed';
        }
      }
      else {
        return array_shift($stack);
      }
    }

    return '';
  }

  /**
   * Gets training certificate expiration flag.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return bool|null
   *   True if training certificate expiration set, false|null otherwise.
   */
  public static function isCertificateExpireSet(Group $group) {
    $value = $group->get('field_certificate_expire')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * Gets training certificate expiration period.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return int|null
   *   Training certificate expiration period.
   */
  public static function getCertificateExpirationPeriod(Group $group) {
    $value = $group->get('field_certificate_expire_results')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return (int) $value[0]['value'];
  }

  /**
   * Returns training latest certificate timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   *
   * @return int|null
   *   Timestamp if found, null otherwise.
   */
  public static function getLatestCertificateTimestamp($gid, $uid) {
    $db_connection = \Drupal::service('database');
    $result = $db_connection->select('user_lp_status_expire', 'lps')
      ->fields('lps', ['latest_cert_date'])
      ->condition('gid', $gid)
      ->condition('uid', $uid)
      ->execute()->fetchField();

    if ($result) {
      return $result;
    }

    return NULL;
  }

  /**
   * Returns training certificate expire timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   *
   * @return int|null
   *   Timestamp if found, null otherwise.
   */
  public static function getCertificateExpireTimestamp($gid, $uid) {
    $db_connection = \Drupal::service('database');
    $result = $db_connection->select('user_lp_status_expire', 'lps')
      ->fields('lps', ['expire'])
      ->condition('gid', $gid)
      ->condition('uid', $uid)
      ->execute()->fetchField();

    if ($result) {
      return $result;
    }

    return NULL;
  }

  /**
   * Saves training certificate expire timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Training latest certificate timestamp.
   * @param int $expire
   *   Training certificate expire timestamp.
   */
  public static function setCertificateExpireTimestamp($gid, $uid, $latest_cert_date = 0, $expire = 0) {
    $db_connection = \Drupal::service('database');
    try {
      $result = $db_connection->select('user_lp_status_expire', 'lps')
        ->fields('lps', ['id'])
        ->condition('gid', $gid)
        ->condition('uid', $uid)
        ->execute()->fetchField();

      if (!$result) {
        // Certification not exists.
        // Add training certification for the user.
        $db_connection->insert('user_lp_status_expire')
          ->fields([
            'gid' => $gid,
            'uid' => $uid,
            'latest_cert_date' => $latest_cert_date,
            'expire' => $expire,
          ])
          ->execute();
      }

      if ($result) {
        // Certification expired.
        // Update certification.
        $db_connection->merge('user_lp_status_expire')
          ->key([
            'gid' => $gid,
            'uid' => $uid,
          ])
          ->fields([
            'gid' => $gid,
            'uid' => $uid,
            'latest_cert_date' => $latest_cert_date,
            'expire' => $expire,
          ])
          ->execute();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_learning_path')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Returns flag if training certificate expired for the user.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param int $uid
   *   User ID.
   *
   * @return bool
   *   True if training certificate expired for the user, false otherwise.
   */
  public static function isCertificateExpired(Group $group, $uid) {
    if (self::isCertificateExpireSet($group)) {
      $db_connection = \Drupal::service('database');
      try {
        // Try to get user training expired timestamp.
        $result = $db_connection->select('user_lp_status_expire', 'lps')
          ->fields('lps', ['expire'])
          ->condition('gid', $group->id())
          ->condition('uid', $uid)
          ->execute()->fetchField();
      }
      catch (\Exception $e) {
        \Drupal::logger('opigno_learning_path')->error($e->getMessage());
        \Drupal::messenger()->addMessage($e->getMessage(), 'error');
      }

      if (!empty($result) && $result < time()) {
        // Certification expired.
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Removes training certificate expire timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int|null $uid
   *   User ID.
   */
  public static function removeCertificateExpiration($gid, $uid = NULL) {
    $db_connection = \Drupal::service('database');
    try {
      $query = $db_connection->delete('user_lp_status_expire');
      $query->condition('gid', $gid);
      if ($uid) {
        $query->condition('uid', $uid);
      }
      $query->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_learning_path')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Returns training start date for displaying statistics.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param int $uid
   *   User ID.
   *
   * @return int|null
   *   Training start date timestamp if exists, null otherwise.
   */
  public static function getTrainingStartDate(Group $group, $uid) {
    $start_date = NULL;

    $expiration_set = LPStatus::isCertificateExpireSet($group);
    if ($expiration_set) {
      // If certificate expiration set for training.
      // Get certificate expire timestamp.
      $gid = $group->id();
      if ($expire_timestamp = LPStatus::getCertificateExpireTimestamp($gid, $uid)) {
        if (time() >= $expire_timestamp) {
          // Certificate expired.
          $start_date = $expire_timestamp;
        }
        else {
          // Certificate not expired.
          // Get latest certification timestamp.
          if ($existing_cert_date = LPStatus::getLatestCertificateTimestamp($gid, $uid)) {
            $start_date = $existing_cert_date;
          }
        }
      }
    }

    return $start_date;
  }

  /**
   * Sets a user notified.
   *
   * @param int $gid
   *   The training ID.
   * @param int $uid
   *   The user ID.
   * @param int $value
   *   The user notification value.
   */
  public static function setUserNotification($gid, $uid, $value) {
    $db_connection = \Drupal::service('database');

    try {
      $db_connection->merge('user_lp_status_expire')
        ->key([
          'gid' => $gid,
          'uid' => $uid,
        ])
        ->fields([
          'notified' => $value,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_learning_path')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Returns training certificate expiration message.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param string $type
   *   Message text type 'valid'|'expired'|null.
   *
   * @return string
   *   Training certificate expiration message.
   */
  public static function getCertificateExpirationMessage($gid, $uid, $type = NULL) {
    $expire_text = '';
    if (!empty($type)) {
      switch ($type) {
        case 'valid':
          $expire_text = t('Valid until') . ' ';
          break;

        case 'expired':
          $expire_text = t('Expired on') . ' ';
          break;
      }
    }

    $date_formatter = \Drupal::service('date.formatter');
    $expire = LPStatus::getCertificateExpireTimestamp($gid, $uid);

    return $expire_text . $date_formatter->format($expire, 'custom', 'F d, Y');
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Term entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the training status.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of the LP Status entity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['gid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Training'))
      ->setDescription(t('The Training of the LP Status entity.'))
      ->setSettings([
        'target_type' => 'group',
        'default_value' => 0,
      ]);

    $fields['score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Score'))
      ->setDescription(t('The score the user obtained for the training.'));

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The training status - passed/failed.'))
      ->setSettings([
        'max_length' => 15,
      ])
      ->setDefaultValue('');

    $fields['started'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Started'))
      ->setDescription(t('The time that the training has started.'))
      ->setDefaultValue(0);

    $fields['finished'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Finished'))
      ->setDescription(t('The time that the training finished.'))
      ->setDefaultValue(0);

    return $fields;
  }

}
