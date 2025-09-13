<?php

namespace Drupal\neo_icon\Element;

use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\neo_icon\IconElement;

/**
 * Provides a render element for a Neo icon.
 */
#[RenderElement('neo_icon')]
class Icon extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#title' => NULL,
      '#attributes' => [],
      '#icon' => NULL,
      '#icon_library' => NULL,
      '#icon_prefix' => [],
      '#icon_attributes' => [],
      '#icon_only' => FALSE,
      '#tooltip' => NULL,
      '#pre_render' => [
        [$class, 'preRenderIcon'],
      ],
    ];
  }

  /**
   * Pre render neo icon.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   container.
   *
   * @return array
   *   The modified element.
   */
  public static function preRenderIcon(array $element) {
    $icon = new IconElement($element['#title'], $element['#icon'], $element['#icon_library'], $element['#icon_prefix']);
    $icon->setLabelAttributes($element['#attributes']);
    $icon->setIconAttributes($element['#icon_attributes']);
    if (!empty($element['#icon_only'])) {
      $icon->iconOnly();
    }
    if (!empty($element['#tooltip'])) {
      $icon->asTooltip(TRUE, $element['#tooltip']);
    }
    return [$icon->getRenderable()];
  }

}
