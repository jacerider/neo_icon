<?php

namespace Drupal\neo_icon\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class create/edit form for icon libraries.
 */
class IconLibraryForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\neo_icon\IconLibraryInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    if ($this->entity->isNew()) {
      $form['#title'] = $this->t('Add Icon Package');
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t("Label for the Neo Icon Package."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Class prefix'),
      '#description' => $this->t('The unique selector prefix of this package. It will be used for rendering the icons within class names and paths. It will replace any class prefix or font names specified within the IcoMoon zip package.'),
      '#default_value' => $this->entity->id(),
      '#field_prefix' => '.',
      '#machine_name' => [
        'exists' => '\Drupal\neo_icon\Entity\IconLibrary::load',
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
        'field_prefix' => '.',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['file'] = [
      '#type' => 'neo_config_file',
      '#title' => $this->entity->isNew() ? $this->t('IcoMoon Font Package') : $this->t('Replace IcoMoon Font Package'),
      '#description' => $this->t('An IcoMoon font package. <a href="https://icomoon.io">Generate & Download</a>'),
      '#extensions' => ['zip'],
      '#required' => $this->entity->isNew(),
      '#dependencies' => [
        $this->entity->getConfigDependencyKey() => [
          $this->entity->getConfigDependencyName(),
        ],
      ],
      '#default_value' => $this->entity->getFile(),
    ];

    $form['global'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Global'),
      '#description' => $this->t('If checked, this icon package will be included on each page. This is useful when using icons via CSS.'),
      '#default_value' => $this->entity->isGlobal(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Published'),
      '#description' => $this->t('If checked, this icon package will be available for selection within Drupal.'),
      '#default_value' => $this->entity->status(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity = $this->entity;
    $status = $this->entity->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Neo Icon Package.', [
          '%label' => $this->entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Neo Icon Package.', [
          '%label' => $this->entity->label(),
        ]));
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }

}
