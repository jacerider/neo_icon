<?php

namespace Drupal\neo_icon\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\neo_icon\IconTranslationTrait;

/**
 * Plugin implementation of the 'boolean' formatter.
 */
#[FieldFormatter(
  id: 'boolean_icon',
  label: new TranslatableMarkup('Icon'),
  field_types: [
    'boolean',
  ],
)]
class BooleanIconFormatter extends FormatterBase {

  use IconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = [];
    $settings['format'] = 'default';
    $settings['icon_only'] = TRUE;
    $settings['as_tooltip'] = FALSE;
    return $settings;
  }

  /**
   * Gets the available format options.
   *
   * @return array|string
   *   A list of output formats. Each entry is keyed by the machine name of the
   *   format. The value is an array, of which the first item is the result for
   *   boolean TRUE, the second is for boolean FALSE. The value can be also an
   *   array, but this is just the case for the custom format.
   */
  protected function getOutputFormats() {
    $formats = [
      'default' => [
        'check-circle',
        'circle',
      ],
      'toggle' => [
        'toggle-on',
        'toggle-off',
      ],
      'check' => [
        'check',
        'times',
      ],
      'switch' => [
        'light-switch-on',
        'light-switch-off',
      ],
      'bulb' => [
        'lightbulb-on',
        'lightbulb',
      ],
    ];

    return $formats;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $formats = [];
    foreach ($this->getOutputFormats() as $format_name => $format) {
      $formats[$format_name] = $this->t('@true_label / @false_label', [
        '@true_label' => $this->icon('', $format[0]),
        '@false_label' => $this->icon('', $format[1]),
      ]);
    }

    $form['format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Output format'),
      '#default_value' => $this->getSetting('format'),
      '#options' => $formats,
    ];

    $form['icon_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Icon only'),
      '#default_value' => $this->getSetting('icon_only') ?? TRUE,
      '#description' => $this->t('Display only the icon without any text.'),
    ];
    $form['as_tooltip'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('As tooltip'),
      '#default_value' => $this->getSetting('as_tooltip') ?? FALSE,
      '#description' => $this->t('Display the icon as a tooltip.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $setting = $this->getSetting('format');
    $formats = $this->getOutputFormats();
    $summary[] = $this->t('Display: @true_label / @false_label', [
      '@true_label' => $this->icon('', $formats[$setting][0]),
      '@false_label' => $this->icon('', $formats[$setting][1]),
    ]);
    $summary[] = $this->t('Icon only: @icon_only', [
      '@icon_only' => $this->getSetting('icon_only') ? $this->t('Yes') : $this->t('No'),
    ]);
    $summary[] = $this->t('As tooltip: @as_tooltip', [
      '@as_tooltip' => $this->getSetting('as_tooltip') ? $this->t('Yes') : $this->t('No'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $formats = $this->getOutputFormats();
    $settings = $this->fieldDefinition->getSettings();

    foreach ($items as $delta => $item) {
      $format = $this->getSetting('format');
      $iconOnly = $this->getSetting('icon_only') ?? TRUE;
      $asTooltip = $this->getSetting('as_tooltip') ?? FALSE;
      $elements[$delta] = [
        '#markup' => $item->value ?
        $this->icon($settings['on_label'] ?? '', $formats[$format][0])->asTooltip($asTooltip)->iconOnly($iconOnly) :
        $this->icon($settings['off_label'] ?? '', $formats[$format][0])->asTooltip($asTooltip)->iconOnly($iconOnly),
      ];
    }

    return $elements;
  }

}
