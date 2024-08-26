<?php

namespace Drupal\neo_icon;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\neo_config_file\ConfigFileEntityEventInterface;

/**
 * Provides an interface for defining Neo Icon library entities.
 */
interface IconLibraryInterface extends ConfigEntityInterface, ConfigFileEntityEventInterface, EntityPublishedInterface {

  /**
   * The public URI where the styles are stored.
   */
  const LIBRARY_URI = 'public://neo-icon';

  /**
   * Get the icon id.
   *
   * The icon id differes from the entity id as the machine name of the entity
   * may be too generic for use as a selector and may cause CSS conflicts.
   *
   * @return string
   *   The icon id.
   */
  public function getIconId();

  /**
   * Get path to the icon library.
   *
   * @return string
   *   The path to the icon library.
   */
  public function getUri();

  /**
   * Get absolute path to icon library.
   *
   * @return string
   *   The path to the icon library.
   */
  public function getAbsolutePath();

  /**
   * Get relative path to icon library.
   *
   * @return string
   *   The path to the icon library.
   */
  public function getRelativePath();

  /**
   * Get the icon library type.
   *
   * @return string
   *   The icon type. Either 'image' or 'icon'.
   */
  public function getType();

  /**
   * Should this icon package be included globally.
   *
   * @return bool
   *   If TRUE, the icon package will be included on every page.
   */
  public function isGlobal();

  /**
   * Check if this is an SVG icon set.
   *
   * @return bool
   *   Returns TRUE if this is an SVG icon set.
   */
  public function isSvg();

  /**
   * Check if this is a font icon set.
   *
   * @return bool
   *   Returns TRUE if this is a font icon set.
   */
  public function isFont();

  /**
   * Returns the weight of the icon package.
   *
   * @return int
   *   The icon package weight.
   */
  public function getWeight();

  /**
   * Return the icon definitions provided by the icon package.
   *
   * @return array
   *   The icon definitions.
   */
  public function getIcons();

  /**
   * Return the icon definitions provided by the icon package.
   *
   * @param string $name
   *   The name of the icon.
   *
   * @return array
   *   The icon definition.
   */
  public function getIcon($name);

  /**
   * Return the icon item instance.
   *
   * @param string $name
   *   The name of the icon.
   *
   * @return \Drupal\neo_icon\IconInterface
   *   The icon item instance.
   */
  public function getIconInstance($name);

  /**
   * Return all icon item instances.
   *
   * @return \Drupal\neo_icon\IconInterface[]
   *   The icon instances.
   */
  public function getIconInstances();

  /**
   * Returns the library name.
   *
   * @return string
   *   The library name.
   */
  public function getLibraryName();

  /**
   * Return the stylesheet of the icon package if it exists.
   *
   * @return string
   *   The path to the IcoMoon style.css file.
   */
  public function getStylesheet();

  /**
   * Get the file.
   *
   * @return string[]
   *   An array of config file ids.
   */
  public function getFile();

  /**
   * Get icon library information.
   *
   * @param bool $no_cache
   *   If TRUE, the cached version will be ignored.
   *
   * @return array
   *   The information for the IcoMoon library.
   */
  public function getInfo($no_cache = FALSE);

  /**
   * Get unique IcoMoon library name.
   */
  public function getInfoName();

  /**
   * Get unique IcoMoon library prefix.
   */
  public function getInfoPrefix();

}
