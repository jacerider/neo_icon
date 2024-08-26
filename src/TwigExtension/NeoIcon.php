<?php

namespace Drupal\neo_icon\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * A class providing NeoIcon Twig extensions.
 *
 * This provides a Twig extension that registers the {{ icon() }} extension
 * to Twig.
 */
class NeoIcon extends AbstractExtension {

  /**
   * Gets a unique identifier for this Twig extension.
   *
   * @return string
   *   A unique identifier for this Twig extension.
   */
  public function getName() {
    return 'twig.neo_icon';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('icon', [$this, 'renderIcon']),
    ];
  }

  /**
   * Render the icon.
   *
   * @param string $icon
   *   The icon_id of the icon to render.
   *
   * @return mixed[]
   *   A render array.
   */
  public static function renderIcon($icon) {
    $build = [
      '#theme' => 'neo_icon',
      '#icon' => $icon,
    ];
    return $build;
  }

}
