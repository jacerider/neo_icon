<?php

namespace Drupal\neo_icon\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\neo_icon\IconElement;
use Drupal\neo_icon\IconTrait;

/**
 * Plugin implementation of the 'neo_icon_entity_reference' formatter.
 */
#[FieldFormatter(
  id: 'neo_icon_entity_reference',
  label: new TranslatableMarkup('Icon'),
  description: new TranslatableMarkup('Display the referenced entities as their icon, with an optional label.'),
  field_types: [
    'entity_reference',
  ],
)]
class EntityReferenceIconFormatter extends EntityReferenceLabelFormatter {

  use IconTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'icon_only' => TRUE,
      'as_tooltip' => FALSE,
      'icon_position' => 'before',
      // The parent defaults this to TRUE. An icon is rarely wanted as a link.
      'link' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['icon_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Icon only'),
      '#default_value' => $this->getSetting('icon_only'),
      '#description' => $this->t('Hide the label visually. It remains present for screen readers.'),
    ];
    $form['as_tooltip'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Label as tooltip'),
      '#default_value' => $this->getSetting('as_tooltip'),
      '#description' => $this->t('Show the hidden label in a tooltip on hover. Not available when the icon is linked, as the tooltip needs its own trigger element; the label is used as the icon title instead.'),
      '#states' => [
        'visible' => [
          ':input[name$="[settings][icon_only]"]' => ['checked' => TRUE],
          ':input[name$="[settings][link]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['icon_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon position'),
      '#options' => [
        'before' => $this->t('Before'),
        'after' => $this->t('After'),
      ],
      '#default_value' => $this->getSetting('icon_position'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Icon only: @icon_only', [
      '@icon_only' => $this->getSetting('icon_only') ? $this->t('Yes') : $this->t('No'),
    ]);
    if ($this->getSetting('icon_only') && !$this->getSetting('link')) {
      $summary[] = $this->t('Label as tooltip: @as_tooltip', [
        '@as_tooltip' => $this->getSetting('as_tooltip') ? $this->t('Yes') : $this->t('No'),
      ]);
    }
    $summary[] = $this->t('Icon position: @position', [
      '@position' => $this->getSetting('icon_position') === 'after' ? $this->t('After') : $this->t('Before'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output_as_link = $this->getSetting('link');
    $icon_only = (bool) $this->getSetting('icon_only');
    $as_tooltip = (bool) $this->getSetting('as_tooltip');
    $position = $this->getSetting('icon_position') === 'after' ? 'after' : 'before';

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $elements[$delta] = ['#entity' => $entity];
      $cacheability = CacheableMetadata::createFromObject($entity);
      // Bundle icons are stored as third party settings on the bundle config
      // entity and invalidated with this tag when saved.
      // @see neo_icon_form_config_entity_build()
      $cacheability->addCacheTags(['neo_icon']);

      $icon = $this->buildEntityIcon($entity)
        ->iconOnly($icon_only)
        ->iconPosition($position);

      // Resolve the destination first, so the tooltip is only used when the
      // icon ends up unlinked.
      $uri = NULL;
      if ($output_as_link && !$entity->isNew()) {
        try {
          $uri = $entity->toUrl();
        }
        catch (UndefinedLinkTemplateException) {
          // This exception is thrown by
          // \Drupal\Core\Entity\EntityInterface::toUrl() and it means that the
          // entity type doesn't have a link template nor a valid
          // "uri_callback", so fall back to the unlinked output.
          $uri = NULL;
        }
        if ($uri) {
          $uri_access = $uri->access(return_as_object: TRUE);
          $cacheability->addCacheableDependency($uri_access);
          if (!$uri_access->isAllowed()) {
            $uri = NULL;
          }
        }
      }

      if ($uri) {
        // The icon renders its own anchor as the tooltip trigger, which would
        // nest inside this link. The hidden label stays reachable through the
        // title attribute the icon carries by default.
        $elements[$delta] += [
          '#type' => 'link',
          // IconElement is a MarkupInterface, so it can be used as a title.
          '#title' => $icon,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      else {
        if ($icon_only && $as_tooltip) {
          $icon->asTooltip();
        }
        $elements[$delta] += $icon->getRenderable();
      }

      $cacheability->applyTo($elements[$delta]);
    }

    return $elements;
  }

  /**
   * Builds the icon element for a referenced entity.
   *
   * The neo_icon_entity() helper uses the entity's bundle label as both the
   * icon lookup text and the display text. For a bundle entity those are the
   * same, but for a content entity we want the icon of its bundle paired with
   * the entity's own label.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The referenced entity.
   *
   * @return \Drupal\neo_icon\IconElement
   *   The icon element.
   */
  protected function buildEntityIcon(EntityInterface $entity): IconElement {
    $icon = neo_icon_entity($entity);
    return $icon->iconLookup($icon->getText(FALSE))->setText($entity->label());
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view label', NULL, TRUE);
  }

}
