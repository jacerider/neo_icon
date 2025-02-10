<?php

namespace Drupal\neo_icon\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'icon' widget.
 *
 * @FieldWidget(
 *   id = "neo_icon",
 *   label = @Translation("Icon"),
 *   field_types = {
 *     "neo_icon"
 *   }
 * )
 */
class IconWidget extends WidgetBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'include' => [],
      'exclude' => [],
    ] + parent::defaultSettings();
  }

  /**
   * Retrieves the library options for the icon widget.
   *
   * @param array $include
   *   An array of library IDs to include.
   * @param array $exclude
   *   An array of library IDs to exclude.
   * @param bool $ignore_status
   *   (optional) Whether to ignore the status of the libraries. Defaults to
   *   FALSE.
   *
   * @return array
   *   An associative array of library options.
   */
  protected function getLibraryOptions($include = [], $exclude = [], $ignore_status = FALSE) {
    /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('neo_icon_library');
    return $storage->loadAsOptions($include, $exclude, $ignore_status);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $options = $this->getLibraryOptions();

    $element['include'] = [
      '#type' => 'checkboxes',
      '#title' => t('Include'),
      '#default_value' => $this->getSetting('include'),
      '#description' => t('The icon libraries that should be made available in this field. If no libraries are selected, all will be made available.'),
      '#options' => $options,
    ];

    $element['exclude'] = [
      '#type' => 'checkboxes',
      '#title' => t('Exclude'),
      '#default_value' => $this->getSetting('exclude'),
      '#description' => t('The icon libraries that should be made excluded in this field. If no libraries are selected, all will be made available.'),
      '#options' => $options,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = $this->getLibraryOptions();
    $include = array_filter($this->getSetting('include'));
    $exclude = array_filter($this->getSetting('exclude'));
    if ($include) {
      $options = array_intersect_key($options, $include);
    }
    if ($exclude) {
      $options = array_diff_key($options, $exclude);
    }
    $element['value'] = $element + [
      '#type' => 'neo_icon_select',
      '#default_value' => $items[$delta]->value ?? NULL,
      '#libraries' => array_keys($options),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $options = $this->getLibraryOptions();
    $include = array_filter($this->getSetting('include'));
    $exclude = array_filter($this->getSetting('exclude'));
    if ($include) {
      $options = array_intersect_key($options, $include);
    }
    if ($exclude) {
      $options = array_diff_key($options, $exclude);
    }
    if ($options) {
      $summary[] = $this->t('Libraries: @libraries', ['@libraries' => implode(', ', $options)]);
    }
    return $summary;
  }

}
