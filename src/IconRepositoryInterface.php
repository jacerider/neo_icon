<?php

namespace Drupal\neo_icon;

/**
 * Interface for the Icon Repository.
 */
interface IconRepositoryInterface {

  /**
   * Get the icon definition.
   *
   * @param string $text
   *   The icon text.
   * @param string $icon
   *   The icon id.
   * @param string $library
   *   The library id.
   * @param array $prefix
   *   An array of prefixes.
   * @param bool $ignore_status
   *   If TRUE, the status will be ignored.
   *
   * @return \Drupal\neo_icon\IconInterface
   *   The icon item.
   */
  public function getIcon($text = NULL, $icon = NULL, $library = NULL, array $prefix = [], $ignore_status = FALSE);

  /**
   * Get the icon item from a selector.
   *
   * @param string $selector
   *   The icon selector.
   * @param bool $ignore_status
   *   If TRUE, the status will be ignored.
   *
   * @return \Drupal\neo_icon\IconInterface|null
   *   The icon item.
   */
  public function getIconBySelector($selector, $ignore_status = FALSE):IconInterface|null;

  /**
   * Get the icon item from the first matching library.
   *
   * @param string $icon
   *   The icon id.
   * @param string $library
   *   The library id.
   * @param bool $ignore_status
   *   If TRUE, the status will be ignored.
   *
   * @return \Drupal\neo_icon\IconInterface
   *   The icon item.
   */
  public function getIconFromLibrary($icon, $library = NULL, $ignore_status = FALSE);

  /**
   * Get the icon from a text match.
   *
   * @param string $text
   *   The icon text.
   * @param array $prefix
   *   An array of prefixes.
   *
   * @return array
   *   The icon definition.
   */
  public function getMatch($text, array $prefix = []);

}
