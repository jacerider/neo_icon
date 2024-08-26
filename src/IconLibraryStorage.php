<?php

namespace Drupal\neo_icon;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Defines the storage handler class for neo icon package entities.
 *
 * This extends the base storage class, adding required special handling for
 * neo icon package entities.
 *
 * @ingroup neo_icon
 */
class IconLibraryStorage extends ConfigEntityStorage implements IconLibraryStorageInterface {

  /**
   * The currently available libraries.
   *
   * @var array
   */
  protected $available;

  /**
   * {@inheritdoc}
   */
  public function getQuery($conjunction = 'AND') {
    $query = parent::getQuery($conjunction);
    $query->sort('weight');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAvailable(array $include = [], array $exclude = [], $ignore_status = FALSE) {
    $key = $ignore_status === TRUE ? 'all' : 'published';
    if (!isset($this->available[$key])) {
      // Build a query to fetch the entity IDs.
      $entity_query = $this->getQuery();
      $entity_query->accessCheck(FALSE);
      if ($ignore_status !== TRUE) {
        $entity_query->condition('status', 1);
      }
      $result = $entity_query->execute();
      $this->available[$key] = $result ? $this->loadMultiple($result) : [];
    }
    $available = $this->available[$key];
    if (!empty($include) && array_filter($include)) {
      $available = array_intersect_key($available, array_flip($include));
    }
    if (!empty($exclude) && array_filter($exclude)) {
      $available = array_diff_key($available, array_flip($exclude));
    }
    return $available;
  }

  /**
   * {@inheritdoc}
   */
  public function loadGlobals() {
    $global = [];
    foreach ($this->loadAvailable() as $library) {
      if ($library->isGlobal()) {
        $global[$library->id()] = $library;
      }
    }
    return $global;
  }

}
