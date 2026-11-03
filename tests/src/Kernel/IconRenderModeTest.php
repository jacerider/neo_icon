<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Render\RenderContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_icon\Controller\IconBrowserController;
use Drupal\neo_icon\IconLibraryInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * An icon library renders as a font or as inline SVG, per library.
 *
 * Font is what every library did before the setting existed and is what an
 * existing library keeps; inline SVG puts each icon's paths in the page from
 * a per-icon store written at import, and swaps the library's full stylesheet
 * for an @font-face-only sheet so CSS-only icons keep their font.
 */
#[Group('neo_icon')]
final class IconRenderModeTest extends KernelTestBase {

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
   * A font-mode library renders exactly the markup it always has.
   */
  public function testFontModeRendersTheFontGlyph(): void {
    $library = $this->importClassicLibrary('test');
    $this->assertSame(IconLibraryInterface::RENDER_FONT, $library->getRender(), 'A library is font unless told otherwise.');

    [$markup, $build] = $this->renderIcon($library, 'chevron');
    $this->assertSame('<i class="neo-icon neo-icon-font icon-test-chevron" aria-hidden="true"></i>', $markup);
    $this->assertSame(['neo_icon/library.test'], $build['#attached']['library']);

    // A multi-code glyph still layers its path spans.
    [$markup] = $this->renderIcon($library, 'duo');
    $this->assertSame('<i class="neo-icon neo-icon-font icon-test-duo" aria-hidden="true"><span class="path1"></span><span class="path2"></span></i>', $markup);

    // The full stylesheet is the library, and its font swaps in.
    $libraries = $this->libraries();
    $this->assertStringEndsWith('/neo-icon/test/style.css', array_key_first($libraries['library.test']['css']['theme']));
    $this->assertArrayNotHasKey('library.test.font', $libraries);
    $css = (string) file_get_contents($library->getUri() . '/style.css');
    $this->assertStringContainsString('font-display: swap', $css);
    $this->assertStringNotContainsString('font-display: block', $css);
  }

  /**
   * An SVG-mode library renders its icons' paths inside the same wrapper.
   */
  public function testSvgModeRendersInlineSvg(): void {
    $library = $this->importClassicLibrary('test', ['render' => 'svg']);

    // Narrow glyph: as wide as it advanced in the font.
    [$markup, $build] = $this->renderIcon($library, 'chevron');
    $this->assertSame('<i class="neo-icon neo-icon-svg icon-test-chevron" aria-hidden="true"><svg viewBox="0 0 640 1024" width="0.625em" height="1em" fill="currentColor"><path d="M0 0h640v1024h-640z"/></svg></i>', $markup);
    $this->assertSame(['neo_icon/library.test'], $build['#attached']['library']);
    $this->assertContains('config:neo_icon.neo_icon_library.test', $build['#cache']['tags']);

    // Two paths, the first translucent, both in the package's fixed fill.
    [$markup] = $this->renderIcon($library, 'duo');
    $this->assertStringContainsString('<path d="M0 0h512v512h-512z" fill="rgb(0, 0, 0)" opacity="0.4"/><path d="M512 512h512v512h-512z" fill="rgb(0, 0, 0)"/>', $markup);

    // A wide glyph in a fixed colour.
    [$markup] = $this->renderIcon($library, 'grid');
    $this->assertStringContainsString('<svg viewBox="0 0 1297 1024" width="1.2666em" height="1em" fill="currentColor">', $markup);
    $this->assertSame(2, substr_count($markup, 'fill="rgb(238, 49, 36)"'));
  }

  /**
   * An SVG-mode library attaches the @font-face sheet, not its stylesheet.
   */
  public function testSvgModeAttachesOnlyTheFontFace(): void {
    $library = $this->importClassicLibrary('test', ['render' => 'svg']);
    $libraries = $this->libraries();

    $this->assertSame(['neo_icon/icon-inline'], $libraries['library.test']['dependencies']);
    $this->assertSame(['font-face.css'], array_map('basename', array_keys($libraries['library.test']['css']['theme'])));
    // The full stylesheet stays registered for what still shows font glyphs.
    $this->assertSame(['style.css'], array_map('basename', array_keys($libraries['library.test.font']['css']['theme'])));

    $fontFace = (string) file_get_contents($library->getUri() . '/font-face.css');
    $this->assertStringContainsString("font-family: 'icon-test';", $fontFace);
    $this->assertStringContainsString("url('fonts/icon-test.woff", $fontFace);
    $this->assertStringContainsString('font-display: swap', $fontFace);
    $this->assertStringNotContainsString('.icon-test-', $fontFace, 'No per-icon rules.');
  }

  /**
   * An icon the store has no paths for falls back to its font glyph.
   */
  public function testIconWithoutPathsFallsBackToTheFontGlyph(): void {
    $library = $this->importClassicLibrary('test', ['render' => 'svg']);
    $this->container->get('file_system')->delete($library->getUri() . '/svg/' . substr(md5('chevron'), 0, 2) . '.json');
    $library = $this->reload('test');

    [$markup, $build] = $this->renderIcon($library, 'chevron');
    $this->assertSame('<i class="neo-icon neo-icon-font icon-test-chevron" aria-hidden="true"></i>', $markup);
    $this->assertSame(['neo_icon/library.test.font'], $build['#attached']['library'], 'The glyph brings the stylesheet that draws it.');
  }

  /**
   * Switching a library imported before the store existed builds the store.
   */
  public function testSwitchingAnOlderLibraryToSvgBuildsItsStore(): void {
    $library = $this->importClassicLibrary('test');
    $file_system = $this->container->get('file_system');
    $file_system->deleteRecursive($library->getUri() . '/svg');
    $file_system->delete($library->getUri() . '/font-face.css');

    $library->setRender('svg')->save();
    $this->assertFileExists($library->getUri() . '/svg/manifest.json');
    $this->assertFileExists($library->getUri() . '/font-face.css');

    // And should a files directory lack it all the same, the first render
    // builds it.
    $file_system->deleteRecursive($library->getUri() . '/svg');
    [$markup] = $this->renderIcon($this->reload('test'), 'chevron');
    $this->assertStringContainsString('<svg viewBox="0 0 640 1024"', $markup);
  }

  /**
   * A library switched earlier in the request is answered as switched.
   */
  public function testAvailableLibrariesFollowTheirSave(): void {
    $library = $this->importClassicLibrary('test');
    $storage = $this->container->get('entity_type.manager')->getStorage('neo_icon_library');
    $this->assertFalse($storage->loadAvailable()['test']->isInlineSvg());

    $library->setRender('svg')->save();

    $this->assertTrue($storage->loadAvailable()['test']->isInlineSvg());
  }

  /**
   * Re-uploading the package rewrites the store.
   */
  public function testReuploadRewritesTheStore(): void {
    $library = $this->importClassicLibrary('test', ['render' => 'svg']);
    $this->container->get('file_system')->deleteRecursive($library->getUri() . '/svg');

    $library->neoConfigFileUpdate($this->classicConfigFile($this->buildClassicZip()));

    $this->assertFileExists($library->getUri() . '/svg/manifest.json');
    $this->assertNotNull($this->reload('test')->getSvgData('chevron'));
  }

  /**
   * Re-uploading renames the fresh package, not by the previous one's names.
   *
   * The library has already read its processed selection.json, as the library
   * form does before it saves. Renaming by those names matched nothing in the
   * newly unpacked package and left IcoMoon's font family in place, so every
   * CSS-only icon asking for the library's family lost its glyph.
   */
  public function testReuploadRenamesTheFreshPackage(): void {
    $library = $this->importClassicLibrary('test', ['render' => 'svg']);
    $this->assertSame('icon-test', $library->getInfoName());

    $library->neoConfigFileUpdate($this->classicConfigFile($this->buildClassicZip()));

    $uri = $library->getUri();
    $this->assertFileExists($uri . '/fonts/icon-test.woff');
    $this->assertFileDoesNotExist($uri . '/fonts/icomoon.woff');
    foreach (['style.css', 'font-face.css'] as $stylesheet) {
      $css = (string) file_get_contents($uri . '/' . $stylesheet);
      $this->assertStringContainsString("font-family: 'icon-test'", $css, $stylesheet);
      $this->assertStringNotContainsString('icomoon', $css, $stylesheet);
    }
    $this->assertStringContainsString('.icon-test-chevron:before', (string) file_get_contents($uri . '/style.css'));
  }

  /**
   * The icon browser's payload carries font glyphs, never path data.
   */
  public function testIconBrowserPayloadStaysFontGlyphs(): void {
    $this->importClassicLibrary('test', ['render' => 'svg']);
    // A controller runs inside a render context, as the request would give it.
    $response = $this->container->get('renderer')->executeInRenderContext(
      new RenderContext(),
      fn () => IconBrowserController::create($this->container)->data(new Request(), 'test'),
    );
    $data = json_decode((string) $response->getContent(), TRUE);

    $this->assertCount(3, $data);
    foreach ($data as $icon) {
      $this->assertStringContainsString('neo-icon-font', $icon['render']);
      $this->assertStringNotContainsString('<svg', $icon['render']);
    }
  }

  /**
   * The update keeps an existing library on font and swaps in its font.
   */
  public function testUpdateKeepsExistingLibrariesOnFont(): void {
    $library = $this->importClassicLibrary('test');
    // As a library saved before the setting existed: no render key, and a
    // stylesheet whose font blocks.
    $this->config('neo_icon.neo_icon_library.test')->clear('render')->save(TRUE);
    $stylesheet = $library->getUri() . '/style.css';
    file_put_contents($stylesheet, str_replace('font-display: swap', 'font-display: block', (string) file_get_contents($stylesheet)));

    $this->container->get('module_handler')->loadInclude('neo_icon', 'php', 'neo_icon.post_update');
    neo_icon_post_update_render_setting();

    $this->assertSame('font', $this->config('neo_icon.neo_icon_library.test')->get('render'));
    $this->assertStringContainsString('font-display: swap', (string) file_get_contents($stylesheet));
    [$markup] = $this->renderIcon($this->reload('test'), 'chevron');
    $this->assertSame('<i class="neo-icon neo-icon-font icon-test-chevron" aria-hidden="true"></i>', $markup);
  }

  /**
   * The libraries the module ships render as inline SVG on a fresh site.
   */
  public function testShippedLibrariesRenderAsSvg(): void {
    $directory = $this->container->get('extension.list.module')->getPath('neo_icon') . '/config/install';
    $files = glob($directory . '/neo_icon.neo_icon_library.*.yml');
    $this->assertCount(5, $files);
    foreach ($files as $file) {
      $this->assertSame('svg', Yaml::decode((string) file_get_contents($file))['render'], basename($file));
    }
  }

  /**
   * Renders one of a library's icons.
   *
   * @param \Drupal\neo_icon\IconLibraryInterface $library
   *   The library.
   * @param string $name
   *   The icon name.
   *
   * @return array
   *   The trimmed markup and the build, carrying what rendering bubbled.
   */
  private function renderIcon(IconLibraryInterface $library, string $name): array {
    $build = $library->getIconInstance($name)->render();
    $markup = trim((string) $this->container->get('renderer')->renderInIsolation($build));
    return [$markup, $build];
  }

  /**
   * Loads a library afresh, as the next request would.
   *
   * @param string $id
   *   The library id.
   *
   * @return \Drupal\neo_icon\IconLibraryInterface
   *   The library.
   */
  private function reload(string $id): IconLibraryInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('neo_icon_library');
    $storage->resetCache([$id]);
    return $storage->load($id);
  }

  /**
   * Builds the module's Drupal libraries.
   *
   * @return array
   *   The libraries hook_library_info_build() answers.
   */
  private function libraries(): array {
    return $this->container->get('module_handler')->invoke('neo_icon', 'library_info_build');
  }

}
