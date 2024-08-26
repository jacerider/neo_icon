<?php

declare(strict_types = 1);

namespace Drupal\neo_icon\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
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
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Subscribe to the user login event dispatched.
   *
   * @param \Drupal\custom_events\Event\UserLoginEvent $event
   *   Our custom event object.
   */
  public function onBuild(NeoBuildEvent $event) {
    $config = $event->getConfig();
    /** @var \Drupal\neo_icon\IconLibraryStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('neo_icon_library');
    $css = [
      '$icons: (',
    ];
    foreach ($storage->loadGlobals() as $library) {
      if (!$library->isFont()) {
        continue;
      }
      foreach ($library->getIconInstances() as $icon) {
        $css_icon = [];
        $css_icon[] = '  ' . $icon->getName() . ': (';
        $css_icon[] = '    library: \'' . $library->id() . '\',';
        $css_icon[] = '    hex: \'' . $icon->getHex() . '\',';
        $css_icon[] = '  ),';
        $css[$icon->getName()] = implode("\n", $css_icon);
      }
    }
    $css[] = ');' . "\n";
    $directory = 'public://neo-build/neo-icon';
    $destination = $directory . '/neo-icons.scss';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $this->fileSystem->saveData(implode("\n", $css), $destination, FileExists::Rename);
    $url = $event->getDocRoot() . ltrim($this->fileUrlGenerator->generateString($destination), '/');
    foreach ($config['scopes'] as $scopeId => &$scope) {
      $scope['vite']['scssInclude'][] = dirname($url);
    }
    $event->setConfig($config);
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
