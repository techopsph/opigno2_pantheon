<?php

namespace Drupal\ckeditor_bgimage\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Component\Utility\Bytes;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a link dialog for text editors.
 */
class EditorFileDialog extends FormBase implements BaseFormIdInterface {

  /**
   * The file storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * Constructs a form object for image dialog.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $file_storage
   *   The file storage service.
   */
  public function __construct(EntityStorageInterface $file_storage) {
    $this->fileStorage = $file_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('file')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'editor_bgimage_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    // Use the EditorLinkDialog form id to ease alteration.
    return 'editor_bgimage_link_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FilterFormat $filter_format = NULL) {

    $file_element = $form_state->get('file_element') ?: [];
    if (isset($form_state->getUserInput()['editor_object'])) {
      $file_element = $form_state->getUserInput()['editor_object'];
      $form_state->set('file_element', $file_element);
      $form_state->setCached(TRUE);
    }

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#prefix'] = '<div id="editor-bgimage-dialog-form">';
    $form['#suffix'] = '</div>';

    $editor = editor_load($filter_format->id());
    $file_upload = $editor->getThirdPartySettings('ckeditor_bgimage');
    $max_filesize = min(Bytes::toInt($file_upload['max_size']), Environment::getUploadMaxSize());

    $upload_directory = $file_upload['scheme'] . '://' . $file_upload['directory'];

    if (!empty($file_element["file"])) {
      $url = $file_element["file"];
      $pos = strrpos($url, '/');
      if ($pos !== FALSE) {
        $filename = substr($url, $pos + 1);

        $uri = $upload_directory . '/' . $filename;
        $file = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['uri' => $uri]);

        if ($file) {
          $file = array_shift($file);
          $fid = $file->id();
        }
      }
    }

    $ext = (!empty($file_upload['extensions'])) ?
      [$file_upload['extensions']] : ['jpg', 'jpeg', 'png'];

    $form['fid'] = [
      '#title' => $this->t('Background Image'),
      '#type' => 'managed_file',
      '#upload_location' => $upload_directory,
      '#default_value' => !empty($fid) ? [$fid] : NULL,
      '#upload_validators' => [
        'file_validate_extensions' => $ext,
        'file_validate_size' => [$max_filesize],
      ],
      '#access' => TRUE,
    ];

    $form['attributes']['href'] = [
      '#title' => $this->t('URL'),
      '#type' => 'textfield',
      '#default_value' => isset($file_element['href']) ? $file_element['href'] : '',
      '#maxlength' => 2048,
      '#access' => TRUE,
    ];

    $width = '';
    $height = '';
    if (!empty($file_element["style"])) {
      $size = explode(';', trim($file_element["style"]));
      if (!empty($size[0])) {
        $width = $size[0];
        $width = str_replace('width:', '', $width);
        $width_digits = preg_replace('/\D/', '', $width);
        $units = str_replace($width_digits, '', $width);
        $width = $width_digits;
      }

      if (!empty($size[1])) {
        $height = $size[1];
        $height = str_replace('height:', '', $height);
        $height_digits = preg_replace('/\D/', '', $height);
        if (empty($units)) {
          $units = str_replace($height_digits, '', $height);
        }
        $height = $height_digits;
      }
    }

    $color = !empty($file_element["color"]) ? trim($file_element["color"]) : '#ffffff';
    $form['background_color'] = [
      '#title' => $this->t('Background Color'),
      '#type' => 'color',
      '#default_value' => $color,
      '#description' => $this->t('Select a Color'),
      '#maxlength' => 10,
    ];

    $form['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Set width digital value. Units - below in the "Width/Height units" field.'),
      '#default_value' => $width,
      '#attributes' => array(
        ' type' => 'number',
      ),
      '#size' => 8,
      '#required' => FALSE,
    ];

    $form['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Set height digital value. Units - below in the "Width/Height units" field.'),
      '#default_value' => $height,
      '#attributes' => array(
        ' type' => 'number',
      ),
      '#size' => 8,
      '#required' => FALSE,
    ];

    $form['units'] = [
      '#type' => 'select',
      '#title' => $this->t('Width/Height units'),
      '#options' => [
        '%' => '%',
        'px' => 'px',
        'em' => 'em',
        'in' => 'in',
      ],
      '#default_value' => !empty($units) ? $units : '%',
      '#required' => FALSE,
    ];

    $position = !empty($file_element["position"]) ? $file_element["position"] : 'left';

    $form['background_aling'] = [
      '#type' => 'select',
      '#title' => $this->t('Image align'),
      '#default_value' => $position,
      '#options' => [
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
    ];

    if ($file_upload['status']) {
      $form['attributes']['href']['#access'] = FALSE;
      $form['attributes']['href']['#required'] = FALSE;
    }
    else {
      $form['fid']['#access'] = FALSE;
      $form['fid']['#required'] = FALSE;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $width = $form_state->getValue('width', '');
    $height = $form_state->getValue('height', '');

    if (!empty($width) && !is_numeric($width)) {
      $form_state->setErrorByName('width', $this->t("Width field wrong format."));
    }

    if (!empty($height) && !is_numeric($height)) {
      $form_state->setErrorByName('height', $this->t("Height field wrong format."));
    }

    $form_units = $form_state->getValue('units', '%');

    if (!empty($form_units) && !in_array($form_units, [
      '%' => '%',
      'px' => 'px',
      'em' => 'em',
      'in' => 'in',
    ])) {

      $form_state->setErrorByName('units', $this->t("Units field wrong format."));
    }

    $units = $width ? $form_units : '';
    $form_state->setValue('width', $width . $units);

    $units = $height ? $form_units : '';
    $form_state->setValue('height', $height . $units);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    list($portal_id, $form_id) = explode("::", $form_state->getValue(['banner', 'formid']));

    $response = new AjaxResponse();
    $form_state->setValue(['attributes', 'idModal'], rand(1000000, 99999999));
    $fid = $form_state->getValue(['fid', 0]);

    if (!empty($fid)) {
      $file = $this->fileStorage->load($fid);
      $file_url = file_create_url($file->getFileUri());
      $file_url = file_url_transform_relative($file_url);
      $form_state->setValue(['attributes', 'image'], $file_url);
    }

    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#editor-bgimage-dialog-form', $form));
      return $response;
    }

    $response->addCommand(new EditorDialogSave($form_state->getValues()));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

}
