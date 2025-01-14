<?php

namespace Drupal\neo_icon_local_task\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Neo Icon override object used for LocalTaskPlugins.
 */
class NeoIconLocalTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);
    $icon = $this->pluginDefinition['icon'] ?? NULL;
    if ($icon) {
      $options['icon'] = $icon;
    }
    return $options;
  }

}
