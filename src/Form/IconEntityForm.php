<?php

namespace Drupal\neo_icon\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\neo_icon\IconEntityTypeManager;

/**
 * Class IconEntityTypeForm.
 */
class IconEntityForm extends ConfigFormBase {

  /**
   * Constructs a new IconEntityTypeForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected IconEntityTypeManager $iconPluginManager,
    TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('neo_icon.entity_type.manager'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'neo_icon.entity',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'neo_icon_entity_type_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('neo_icon.entity');

    $entity_types = $this->entityTypeManager->getDefinitions();
    $form['types'] = [
      '#tree' => TRUE,
    ];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $icon = neo_icon_entity_type($entity_type)->getIcon();
        $form['types'][$entity_type_id] = [
          '#type' => 'neo_icon_select',
          '#title' => $entity_type->getLabel(),
          '#default_value' => $icon ? $icon->getName() : '',
          '#weight' => 0,
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('neo_icon.entity')
      ->set('types', array_filter($form_state->getValue('types')))
      ->save();
    $this->iconPluginManager->clearCachedDefinitions();
  }

}
