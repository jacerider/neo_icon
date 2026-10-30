<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Icons rendered for script come as a map, one entry per icon.
 *
 * Script that builds `<i class="icon-…">` from a selector draws nothing once a
 * library renders inline SVG, so whatever hands icons to script renders them
 * through this map instead and ships the markup.
 */
#[Group('neo_icon')]
final class IconMarkupMapTest extends KernelTestBase {

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
   * Each icon is rendered once, keyed by selector, with what it depends on.
   */
  public function testOneEntryPerIconWithItsCacheTag(): void {
    $library = $this->importClassicLibrary('test', ['render' => 'svg']);
    $metadata = new BubbleableMetadata();

    $map = $this->container->get('neo_icon.markup_map')->build([
      $library->getIconInstance('chevron'),
      'icon-test-chevron',
      'icon-test-grid',
      $library->getIconInstance('grid'),
      'icon-test-missing',
      '',
    ], $metadata);

    $this->assertSame(['icon-test-chevron', 'icon-test-grid'], array_keys($map));
    $this->assertSame('<i class="neo-icon neo-icon-svg icon-test-chevron" aria-hidden="true"><svg viewBox="0 0 640 1024" width="0.625em" height="1em" fill="currentColor"><path d="M0 0h640v1024h-640z"/></svg></i>', $map['icon-test-chevron']);
    $this->assertStringContainsString('<svg viewBox="0 0 1297 1024"', $map['icon-test-grid']);
    $this->assertContains('config:neo_icon.neo_icon_library.test', $metadata->getCacheTags());
    $this->assertSame(['neo_icon/library.test'], $metadata->getAttachments()['library']);
  }

  /**
   * A font-mode library's entries are its font glyphs, as it renders them.
   */
  public function testFontModeEntriesAreFontGlyphs(): void {
    $this->importClassicLibrary('test');

    $map = $this->container->get('neo_icon.markup_map')->build(['icon-test-chevron']);

    $this->assertSame(['icon-test-chevron' => '<i class="neo-icon neo-icon-font icon-test-chevron" aria-hidden="true"></i>'], $map);
  }

  /**
   * Rendering the map leaks nothing into the page being rendered.
   */
  public function testNothingBubblesIntoTheSurroundingRender(): void {
    $this->importClassicLibrary('test', ['render' => 'svg']);
    $renderer = $this->container->get('renderer');

    $build = [
      '#pre_render' => [
        static function (array $element): array {
          \Drupal::service('neo_icon.markup_map')->build(['icon-test-chevron']);
          $element['#markup'] = 'outer';
          return $element;
        },
      ],
    ];
    $renderer->renderInIsolation($build);

    $this->assertNotContains('config:neo_icon.neo_icon_library.test', $build['#cache']['tags'] ?? []);
    $this->assertEmpty($build['#attached']['library'] ?? []);
  }

}
