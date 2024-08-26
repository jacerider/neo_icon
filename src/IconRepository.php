<?php

namespace Drupal\neo_icon;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The icon repository.
 */
class IconRepository implements IconRepositoryInterface {

  /**
   * The icon library storage.
   *
   * @var \Drupal\neo_icon\IconLibraryStorageInterface
   */
  protected $iconLibraryStorage;

  /**
   * The icon manager.
   *
   * @var \Drupal\neo_icon\IconManagerInterface
   */
  protected $iconManager;

  /**
   * A list of library matches.
   *
   * @var array
   */
  protected $libraryMatches = [];

  /**
   * A list of definition matches.
   *
   * @var array
   */
  protected $definitionMatches = [];

  /**
   * Constructs a new NeoIconRepository object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\neo_icon\IconManagerInterface $icon_manager
   *   The icon manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, IconManagerInterface $icon_manager) {
    $this->iconLibraryStorage = $entity_type_manager->getStorage('neo_icon_library');
    $this->iconManager = $icon_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function getIcon($text = NULL, $icon = NULL, $library = NULL, array $prefix = [], $ignore_status = FALSE) {
    if (!$icon && !is_array($text)) {
      if ($definition = $this->getMatch($text, $prefix)) {
        $icon = $definition['icon'];
        $library = $definition['library'];
      }
    }
    return $icon ? $this->getIconFromLibrary($icon, $library, $ignore_status) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getIconBySelector($selector, $ignore_status = FALSE):IconInterface|null {
    $libraries = $this->iconLibraryStorage->loadAvailable([], [], $ignore_status);
    foreach ($libraries as $library) {
      foreach ($library->getIcons() as $name => $icon) {
        if ($icon['prefix'] . $icon['name'] === $selector) {
          return $library->getIconInstance($name);
        }
      }
    }
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getIconFromLibrary($icon, $library = NULL, $ignore_status = FALSE) {
    $key = strtolower(implode('.', array_filter(array_merge([
      (string) $icon,
    ], [$library]))));
    if (!isset($this->libraryMatches[$key])) {
      $this->libraryMatches[$key] = NULL;
      $libraries = $this->iconLibraryStorage->loadAvailable([], [], $ignore_status);
      if ($library && isset($libraries[$library])) {
        // If a library is provided, favor it.
        $libraries = [$library => $libraries[$library]] + $libraries;
      }
      foreach ($libraries as $library) {
        if ($library_icon = $library->getIconInstance($icon)) {
          $this->libraryMatches[$key] = $library_icon;
          return $this->libraryMatches[$key];
        }
      }
    }
    return $this->libraryMatches[$key];
  }

  /**
   * {@inheritDoc}
   */
  public function getMatch($text, array $prefix = []) {
    $key = strtolower(implode('.', array_filter(array_merge([
      (string) $text,
    ], $prefix))));
    if (!isset($this->definitionMatches[$key])) {
      $this->definitionMatches[$key] = NULL;
      $definitions = $this->iconManager->getDefinitionsWithPrefix($prefix);
      $text = strtolower($text);
      // Check for regex exact string definitionMatches second.
      foreach ($definitions as $definition) {
        if ($definition['regex'] && preg_match('!' . $definition['regex'] . '!', $text)) {
          $this->definitionMatches[$key] = $definition;
          break;
        }
      }
    }
    return $this->definitionMatches[$key];
  }

}
