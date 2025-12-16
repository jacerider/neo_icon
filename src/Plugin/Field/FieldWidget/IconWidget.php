<?php

namespace Drupal\neo_icon\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
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
      'icons' => [],
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

    $element['icons'] = [
      '#type' => 'textfield',
      '#title' => t('Icons'),
      '#description' => t('A comma separated list of icon names that should be made available in this field. If no icons are specified, all icons from the selected libraries will be available.'),
      '#default_value' => implode(', ', $this->getSetting('icons')),
    ];

    $element['#element_validate'] = [
      [get_class($this), 'validateIconWidget'],
    ];

    return $element;
  }

  /**
   * Validate the include/exclude settings.
   */
  public static function validateIconWidget(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValue($element['#parents']);
    $values['include'] = array_filter($values['include']);
    $values['exclude'] = array_filter($values['exclude']);
    $values['icons'] = array_map('trim', explode(',', $values['icons'] ?? ''));

    $conflicts = array_intersect_key($values['include'], $values['exclude']);
    if (!empty($conflicts)) {
      $form_state->setError($element, t('The same icon library cannot be included and excluded.'));
    }

    $form_state->setValue($element['#parents'], $values);
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
      '#icons' => array_values(array_filter($this->getSetting('icons'))),
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
    $icons = array_filter($this->getSetting('icons'));
    if ($include) {
      $options = array_intersect_key($options, $include);
    }
    if ($exclude) {
      $options = array_diff_key($options, $exclude);
    }
    if ($options) {
      $summary[] = $this->t('Libraries: @libraries', ['@libraries' => implode(', ', $options)]);
    }
    if ($icons) {
      $iconIcons = [];
      foreach ($icons as $icon_name) {
        $iconIcons[] = neo_icon('', $icon_name);
      }
      $summary[] = $this->t('Allowed Icons: @icons', ['@icons' => Markup::create(implode(' ', $iconIcons))]);
    }
    return $summary;
  }

}
