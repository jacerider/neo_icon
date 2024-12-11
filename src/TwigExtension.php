<?php

namespace Drupal\neo_icon;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * A class providing NeoIcon Twig extensions.
 *
 * This provides a Twig extension that registers the {{ icon() }} extension
 * to Twig.
 */
class TwigExtension extends AbstractExtension {

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
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('icon_only', [$this, 'iconOnly']),
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

  /**
   * Add classes to a renderable array.
   */
  public function iconOnly($icon, $iconOnly = TRUE) {
    if ($icon instanceof IconElement) {
      $icon->iconOnly($iconOnly);
    }
    return $icon;
  }

}
