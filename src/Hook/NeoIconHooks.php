<?php

declare(strict_types=1);

namespace Drupal\neo_icon\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * The module's behavioural hook implementations.
 *
 * The library build, the page attachments and the library alter, split from the
 * theme hooks the way core splits its own `…Hooks` / `…ThemeHooks` pairs. All
 * three do one thing in three places: they ask the icon library storage for
 * libraries and turn them into Drupal library entries or attachments.
 *
 * Every body below is what stood in `neo_icon.module`, with one substitution —
 * the three `\Drupal::service('entity_type.manager')` calls became the single
 * constructor argument. Nothing about what any of them decides moved with them:
 * not which icon libraries get an entry, not how a library is named, not the
 * condition under which the svg helper is pulled in, not the `canvas` case.
 *
 * That substitution is the whole gain. As functions, the stylesheet condition
 * and the `canvas-ui` case could only be exercised with a booted site; as
 * methods on a constructed object they are reachable from a stubbed storage,
 * which is `docs/adr/0007`'s test answering yes.
 *
 * This is not an API and it is not `final`. The methods are public because
 * core's hook collector only reads public methods, and nothing outside the hook
 * system calls them.
 */
class NeoIconHooks {

  /**
   * Constructs a NeoIconHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, which all three hooks below use for the one
   *   thing: reaching the icon library storage.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_library_info_build().
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {
    $libraries = [];
    /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('neo_icon_library');
    foreach ($storage->loadMultiple() as $library) {
      /** @var \Drupal\neo_icon\IconLibraryInterface $library */
      if ($stylesheet = $library->getStylesheet()) {
        $library_name = $library->getLibraryName();
        $libraries[$library_name]['css']['theme'][$stylesheet] = [];
        // Add SVG library if necessary.
        if ($library->isSvg()) {
          $libraries[$library_name]['dependencies'][] = 'neo_icon/icon-svg';
        }
      }
    }
    return $libraries;
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('neo_icon_library');
    foreach ($storage->loadGlobals() as $library) {
      /** @var \Drupal\neo_icon\IconLibraryInterface $library */
      $attachments['#attached']['library'][] = 'neo_icon/' . $library->getLibraryName();
    }
  }

  /**
   * Implements hook_library_info_alter().
   *
   * Process libraries configured with vite.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension === 'canvas') {
      if (isset($libraries['canvas-ui'])) {
        /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('neo_icon_library');
        foreach ($storage->loadGlobals() as $library) {
          /** @var \Drupal\neo_icon\IconLibraryInterface $library */
          $libraries['canvas-ui']['dependencies'][] = 'neo_icon/' . $library->getLibraryName();
        }
      }
    }
  }

}
