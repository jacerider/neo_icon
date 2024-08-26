<?php

namespace Drupal\neo_icon;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;

/**
 * Interface for an icon library storage.
 */
interface IconLibraryStorageInterface extends ConfigEntityStorageInterface {

  /**
   * Return available icon libraries.
   *
   * @param array $include
   *   The libraries to include.
   * @param array $exclude
   *   The libraries to exclude.
   * @param bool $ignore_status
   *   If TRUE, the status will be ignored.
   *
   * @return \Drupal\neo_icon\IconLibraryInterface[]
   *   An array of available icon libraries.
   */
  public function loadAvailable(array $include = [], array $exclude = [], $ignore_status = FALSE);

  /**
   * Return global libraries.
   *
   * @return \Drupal\neo_icon\IconLibraryInterface[]
   *   An array of global icon libraries.
   */
  public function loadGlobals();

}
