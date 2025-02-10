<?php

namespace Drupal\neo_icon\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\neo_icon\IconTranslationTrait;

/**
 * Plugin implementation of the 'icon' formatter.
 *
 * @FieldFormatter(
 *   id = "neo_icon",
 *   label = @Translation("eXo Icon"),
 *   field_types = {
 *     "neo_icon"
 *   }
 * )
 */
class IconFormatter extends FormatterBase {
  use IconTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta]['#markup'] = $this->icon(NULL, $item->value);
    }

    return $elements;
  }

}
