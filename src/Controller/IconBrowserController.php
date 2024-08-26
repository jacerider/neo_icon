<?php

namespace Drupal\neo_icon\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\neo_icon\Icon;

/**
 * Instance of ValetController.
 */
class IconBrowserController extends ControllerBase {

  /**
   * The icon library storage.
   *
   * @var \Drupal\neo_icon\IconLibraryStorageInterface
   */
  protected $iconLibraryStorage;

  /**
   * The controller constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    private readonly CacheBackendInterface $cache,
    private readonly RendererInterface $renderer,
  ) {
    $this->iconLibraryStorage = $entity_type_manager->getStorage('neo_icon_library');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('cache.default'),
      $container->get('renderer'),
    );
  }

  /**
   * Return the icons.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   A json object.
   */
  public function data($libraries = '') {
    $data = [];
    $libraries = !empty($libraries) ? explode(' ', $libraries) : [];
    $cid = 'neo_icons';
    if ($libraries) {
      $cid .= ':' . implode('.', $libraries);
    }
    if ($cache = $this->cache->get($cid)) {
      return new JsonResponse($cache->data);
    }

    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->addCacheTags([
      'neo_icon_libraries',
    ]);

    foreach ($this->iconLibraryStorage->loadAvailable($libraries, [], TRUE) as $library) {
      $cacheable_metadata->addCacheableDependency($library);
      foreach ($library->getIcons() as $name => $definition) {
        $icon = new Icon($definition, $library);
        $render = $icon->render();
        $data[] = [
          'name' => $icon->getName(),
          'hex' => $icon->getHex(),
          'library' => $library->id(),
          'selector' => $icon->getSelector(),
          'render' => $this->renderer->render($render),
        ];
      }
    }

    // Cache.
    $this->cache->set($cid, $data, Cache::PERMANENT, $cacheable_metadata->getCacheTags());
    return new JsonResponse($data);
  }

  /**
   * Show all icons.
   */
  public function all() {
    $build = [];
    $build['icons'] = [
      '#type' => 'neo_icon_browser',
      '#show_info' => FALSE,
    ];
    return $build;
  }

}
