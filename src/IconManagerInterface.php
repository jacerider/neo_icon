<?php

namespace Drupal\neo_icon;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Defines an interface for neo icon managers.
 */
interface IconManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * Get definitions with prefix.
   *
   * @param array $prefix
   *   An array of prefix ids.
   *
   * @return array
   *   An array of definitions.
   */
  public function getDefinitionsWithPrefix(array $prefix = []);

}
