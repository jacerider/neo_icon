<?php

declare(strict_types=1);

namespace Drupal\neo_icon\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\neo_build\Event\NeoBuildEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class UserLoginSubscriber.
 *
 * @package Drupal\custom_events\EventSubscriber
 */
class NeoBuildEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new NeoBuildEventSubscriber object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Subscribe to the Neo build event dispatched.
   *
   * @param \Drupal\neo_build\Event\NeoBuildEvent $event
   *   Our custom event object.
   */
  public function onBuild(NeoBuildEvent $event) {
    $collection = $event->getCollection();
    /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('neo_icon_library');
    $libraryIds = [];
    // We reverse the array so higher priority libraries will overwrite
    // lower priority ones.
    foreach (array_reverse($storage->loadGlobals()) as $library) {
      if (!$library->isFont()) {
        continue;
      }
      foreach ($library->getIconInstances() as $icon) {
        $libraryIds[$library->id()] = $library->id();
        $collection->addTailwindThemeItem('--icon-' . $icon->getName(), "'" . $icon->getHex() . "'");
        $collection->addTailwindThemeItem('--icon-library-' . $icon->getName(), "'icon-" . $library->id() . "'");
      }
    }
    if ($libraryIds) {
      foreach ($libraryIds as $libraryId) {
        $collection->addTailwindUtility('icon-' . $libraryId . '-*', [
          '--tw-content' => '--value(--icon-*)',
          'display' => 'var(--icon-display, inline-block)',
          'content' => 'var(--tw-content)',
          'font-family' => '--value(--icon-library-' . $libraryId . ')',
          '-webkit-font-smoothing' => 'antialiased',
          '-moz-osx-font-smoothing' => 'grayscale',
          'font-style' => 'normal',
          'font-variant' => 'normal',
          'font-weight' => 'normal',
          'line-height' => 1,
        ]);
      }
      $collection->addTailwindUtility('icon-*', [
        '--tw-content' => '--value(--icon-*)',
        'display' => 'var(--icon-display, inline-block)',
        'content' => 'var(--tw-content)',
        'font-family' => '--value(--icon-library-*)',
        '-webkit-font-smoothing' => 'antialiased',
        '-moz-osx-font-smoothing' => 'grayscale',
        'font-style' => 'normal',
        'font-variant' => 'normal',
        'font-weight' => 'normal',
        'line-height' => 1,
      ]);
      $collection->addTailwindVariants([
        'icon' => ['& .neo-icon'],
      ]);
    }
  }

  /**
   * Create a nested CSS variable string.
   */
  protected function createNestedVars($items, $prefix = '') {
    if (empty($items)) {
      return '';
    }
    // Ensure items are indexed numerically.
    $items = array_values($items);

    $result = '';
    for ($i = 0; $i < count($items); $i++) {
      $result .= 'var(--' . $prefix . $items[$i];
      if ($i < count($items) - 1) {
        $result .= ', ';
      }
    }

    // Add closing parentheses.
    $result .= str_repeat(')', count($items));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      NeoBuildEvent::EVENT_NAME => 'onBuild',
    ];
  }

}
