<?php

namespace Drupal\neo_icon\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'icon' field type.
 *
 * @FieldType(
 *   id = "neo_icon",
 *   label = @Translation("Icon"),
 *   description = @Translation("A field containing an icon."),
 *   default_widget = "neo_icon",
 *   default_formatter = "neo_icon"
 * )
 */
class IconItem extends StringItem {

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    return [];
  }

}
