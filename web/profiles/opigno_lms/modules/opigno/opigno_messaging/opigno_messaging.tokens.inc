<?php

/**
 * @file
 * Builds placeholder replacement tokens opigno-wide data.
 */

use Drupal\Core\Url;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function opigno_messaging_token_info() {
  $types['opigno'] = [
    'name' => t("Opigno global"),
    'description' => t("Tokens for opigno-wide settings and other global information."),
  ];

  $opigno['url-with-slash'] = [
    'name' => t("Global site URL with slash"),
    'description' => t("The site URL with slash in the end."),
  ];

  return [
    'types' => $types,
    'tokens' => [
      'opigno' => $opigno,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function opigno_messaging_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $token_service = \Drupal::token();

  $url_options = ['absolute' => TRUE];
  if (isset($options['langcode'])) {
    $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
    $langcode = $options['langcode'];
  }
  else {
    $langcode = NULL;
  }
  $replacements = [];

  if ($type == 'opigno') {
    foreach ($tokens as $name => $original) {
      switch ($name) {

        case 'url-with-slash':
          /** @var \Drupal\Core\GeneratedUrl $result */
          $result = Url::fromRoute('<front>', [], $url_options)->toString(TRUE);
          $bubbleable_metadata->addCacheableDependency($result);
          $replacements[$original] = $result->getGeneratedUrl();

          // When multilingual functional enabled site url returns without slash.
          if (substr($replacements[$original], -1) != '/') {
            $replacements[$original] .= '/'; 
          }
          break;
      }
    }
  }

  return $replacements;
}
