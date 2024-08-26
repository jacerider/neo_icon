<?php

namespace Drupal\neo_icon;

/**
 * Icon repository trait.
 */
trait IconRepositoryTrait {

  /**
   * The icon repository.
   *
   * @var \Drupal\neo_icon\IconRepositoryInterface
   */
  protected $iconRepository;

  /**
   * Gets the icon.
   *
   * @param string $text
   *   The icon text.
   * @param string $icon
   *   The icon id.
   * @param string $library
   *   The library id.
   * @param array $prefix
   *   An array of prefixes.
   *
   * @return \Drupal\neo_icon\IconInterface|null
   *   The icon.
   */
  protected function loadIcon($text = NULL, $icon = NULL, $library = NULL, array $prefix = []):?IconInterface {
    return $this->getIconRepository()->getIcon($text, $icon, $library, $prefix);
  }

  /**
   * Gets the icon repository.
   *
   * @return \Drupal\neo_icon\IconRepositoryInterface
   *   The icon repository.
   */
  protected function getIconRepository() {
    if (!isset($this->iconRepository)) {
      /** @var \Drupal\neo_icon\IconRepositoryInterface $repository */
      $this->iconRepository = \Drupal::service('neo_icon.repository');
    }
    return $this->iconRepository;
  }

}
