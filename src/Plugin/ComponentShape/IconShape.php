<?php

declare(strict_types=1);

namespace Drupal\neo_icon\Plugin\ComponentShape;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\neo_alchemist\Attribute\ComponentShape;
use Drupal\neo_alchemist\Shape\ComponentShapePluginBase;

/**
 * Plugin implementation of the neo_component_shape.
 */
#[ComponentShape(
  prop: 'icon',
  label: new TranslatableMarkup('Icon'),
  default_field_type: 'neo_icon',
  default_field_widget: 'neo_icon',
)]
class IconShape extends ComponentShapePluginBase {

  /**
   * {@inheritDoc}
   */
  public function getMatches(FieldDefinitionInterface $entityFieldDefinition) {
    $matches = parent::getMatches($entityFieldDefinition);
    if ($entityFieldDefinition->getType() === 'link') {
      // Links have an option field which can store attributes and an icon.
      return [
        'options:attributes~data-icon' => $this->t('Icon'),
      ];
    }
    return $matches;
  }

}
