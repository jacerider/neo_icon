<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * A rendered icon depends on its library, and says so.
 *
 * Markup holding an icon is cached all over a site — in blocks, in components,
 * in whole pages — and the icon's look comes from its library: the classes and
 * the attachment, and once a library renders inline SVG, the path data itself.
 * Without the library's cache tag on the icon, re-uploading a package left
 * every cached page showing the old glyphs until a full cache clear.
 */
#[Group('neo_icon')]
final class IconCacheabilityTest extends KernelTestBase {

  use ClassicPackageTrait;

  /**
   * {@inheritdoc}
   *
   * The same hand-enumerated set as the module's other kernel tests, for the
   * reasons given there.
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'path_alias',
    'linkit',
    'neo_build',
    'neo_color',
    'neo',
    'neo_config_file',
    'neo_favicon',
    'neo_tooltip',
    'neo_settings',
    'neo_modal',
    'neo_icon',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * A rendered icon carries its library's cache tag.
   */
  public function testRenderedIconCarriesTheLibraryCacheTag(): void {
    $library = $this->importClassicLibrary('test');
    $build = $library->getIconInstance('chevron')->render();
    $this->container->get('renderer')->renderInIsolation($build);

    $this->assertContains('config:neo_icon.neo_icon_library.test', $build['#cache']['tags']);
  }

  /**
   * Saving the library, as re-uploading its package does, invalidates it.
   *
   * The render cache is stood in for by a cache item tagged with whatever the
   * icon bubbled, which is exactly how the render cache stores its entries.
   */
  public function testSavingTheLibraryInvalidatesCachedIconMarkup(): void {
    $library = $this->importClassicLibrary('test');
    $build = $library->getIconInstance('chevron')->render();
    $markup = (string) $this->container->get('renderer')->renderInIsolation($build);

    $cache = $this->container->get('cache.render');
    $cache->set('icon-markup', $markup, tags: $build['#cache']['tags']);
    $this->assertNotFalse($cache->get('icon-markup'), 'The markup is cached.');

    // Re-uploading the package unpacks it again and saves the library.
    $library->neoConfigFileUpdate($this->classicConfigFile($this->buildClassicZip()));

    $this->assertFalse($cache->get('icon-markup'), 'Re-uploading the package invalidated the cached markup.');
  }

}
