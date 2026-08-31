<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_icon\Hook\NeoIconThemeHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * The module's theme registration and its four initial preprocess callbacks.
 *
 * All of it is now `Drupal\neo_icon\Hook\NeoIconThemeHooks`, split from the
 * behavioural hooks the way core splits its own `…Hooks` / `…ThemeHooks` pairs.
 * Four of the methods are not hooks at all: they were `template_preprocess_*()`
 * functions, deprecated as of Drupal 11.3 and removed in Drupal 12, and they
 * are now **initial preprocess** callbacks named from their own theme hook
 * definitions.
 *
 * That is where this ticket's risk lives, and it is why both assertions below
 * go through the module handler and the theme registry rather than through the
 * object. `neo_icon_element` and `neo_icon` are what every icon element on a
 * site renders through, so a theme hook naming a callback that does not resolve
 * is not a subtle regression — it logs a warning, renders on without the
 * callback, and every icon comes out empty. A method nobody invokes is not a
 * hook implementation.
 *
 * What the four callbacks *decide* is not asked here. The class takes one
 * collaborator and constructs without a container, so all five behavioural
 * criteria are driven from stubs in
 * `Drupal\Tests\neo_icon\Unit\ThemePreprocessDecisionsTest`, and this bootstrap
 * is spent only on the two questions that need one.
 */
#[Group('neo_icon')]
final class ThemeHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Enumerated by hand and completely, because `enableModules()` installs with
   * dependencies off and `neo_icon.info.yml` pulls a graph with a cycle in it —
   * `neo_icon` names `neo_config_file`, which names `neo`, which names
   * `neo_icon` again. `field` is here because `file.info.yml` declares it and
   * nothing resolves that for us; `linkit` because `neo` declares it; and
   * `path_alias` because `neo.linkit_resolver` takes `path_alias.manager` and a
   * service argument is not a declared dependency anywhere an info file can be
   * read — the container refuses to compile without it.
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
   *
   * The theme registry is the seam both of these tests read, and it cannot be
   * built without an active theme, so one is installed and made the default.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * The four theme hooks are registered from the class, unchanged.
   *
   * Acceptance criterion: *the module handler resolves the theme hook to a
   * class method, and it registers the same four theme hooks with the variables
   * they carried before.*
   *
   * Both halves are needed. The variables are asserted whole because a theme
   * hook that loses a declared variable renders `NULL` rather than failing, and
   * the implementation is named because `hook_theme()` would answer exactly the
   * same array from a surviving global.
   */
  public function testRegistersTheFourThemeHooksFromTheClassWithTheirVariables(): void {
    $hooks = $this->themeHooks();

    $this->assertSame([
      'neo_icon_element',
      'neo_icon',
      'neo_icon_library',
      'neo_icon_browser',
    ], array_keys($hooks), 'The same four theme hooks, in the same order.');

    $this->assertSame([
      'title' => NULL,
      'attributes' => [],
      'attributes_icon' => [],
      'position' => 'before',
      'icon' => NULL,
      'icon_only' => FALSE,
    ], $hooks['neo_icon_element']['variables']);
    $this->assertSame([
      'icon' => NULL,
      'attributes' => [],
      'children' => [],
    ], $hooks['neo_icon']['variables']);
    $this->assertSame('element', $hooks['neo_icon_library']['render element']);
    $this->assertArrayNotHasKey('variables', $hooks['neo_icon_library']);
    $this->assertSame([
      'libraries' => [],
      'library_options' => [],
      'attributes' => [],
      'loader' => [],
    ], $hooks['neo_icon_browser']['variables']);

    // Registered, not merely returned: each one reaches the theme registry.
    $registry = $this->container->get('theme.registry')->get();
    foreach (array_keys($hooks) as $hook) {
      $this->assertArrayHasKey($hook, $registry, $hook . ' is in the theme registry.');
    }

    // The array above came from the hook class, not from a function the
    // collector is still reading out of the `.module` file.
    $this->assertContains(
      'neo_icon: ' . NeoIconThemeHooks::class . '::theme',
      $this->hookImplementations('theme')
    );
    $this->assertNotContains(
      'neo_icon: neo_icon_theme',
      $this->hookImplementations('theme'),
      'Nothing procedural answers hook_theme for neo_icon.'
    );
    $this->assertFalse(function_exists('neo_icon_theme'), 'neo_icon_theme() is gone.');
  }

  /**
   * The four deprecated preprocessors are initial preprocess callbacks now.
   *
   * Acceptance criterion: *each of the four theme hooks names an initial
   * preprocess callback that resolves, no `template_preprocess_*` function
   * remains in the module, and the four templates' `@see` lines name the
   * methods instead.*
   *
   * The callback is resolved rather than string-matched, because a theme hook
   * naming a callback that does not resolve logs a warning and renders on
   * without it — silent everywhere except in the markup, which for these two
   * hooks is every icon on the site.
   */
  public function testNamesResolvingInitialPreprocessOnEachHookAndUpdatesTheTemplateSeeLines(): void {
    $expected = [
      'neo_icon_element' => ['preprocessNeoIconElement', 'neo-icon-element.html.twig'],
      'neo_icon' => ['preprocessNeoIcon', 'neo-icon.html.twig'],
      'neo_icon_library' => ['preprocessNeoIconLibrary', 'neo-icon-library.html.twig'],
      'neo_icon_browser' => ['preprocessNeoIconBrowser', 'neo-icon-browser.html.twig'],
    ];
    $hooks = $this->themeHooks();
    $registry = $this->container->get('theme.registry')->get();
    $resolver = $this->container->get('callable_resolver');

    foreach ($expected as $hook => [$method, $template]) {
      $definition = NeoIconThemeHooks::class . ':' . $method;
      $this->assertSame(
        $definition,
        $hooks[$hook]['initial preprocess'] ?? NULL,
        $hook . ' names its initial preprocess callback.'
      );
      // And the registry carries it through to where the theme manager reads
      // it, rather than dropping it on the way.
      $this->assertSame(
        $definition,
        $registry[$hook]['initial preprocess'] ?? NULL,
        $hook . "'s callback survives the registry build."
      );
      // It resolves against the container, which is the whole risk.
      $this->assertIsCallable(
        $resolver->getCallableFromDefinition($definition),
        $definition . ' resolves.'
      );
      // Position is preserved: an initial preprocess callback runs ahead of
      // every module and theme preprocess function, which is where the
      // deprecated function ran.
      $preprocessFunctions = $registry[$hook]['preprocess functions'] ?? [];
      $this->assertNotContains(
        'template_preprocess_' . $hook,
        $preprocessFunctions,
        'The deprecated function is not among ' . $hook . "'s preprocess functions."
      );
      $this->assertFalse(
        function_exists('template_preprocess_' . $hook),
        'template_preprocess_' . $hook . '() no longer exists.'
      );

      // The template's `@see` line names the method rather than a function.
      $source = (string) file_get_contents($this->packageRoot() . '/templates/' . $template);
      $this->assertStringContainsString(
        '@see \\' . NeoIconThemeHooks::class . '::' . $method . '()',
        $source,
        $template . ' points at the method.'
      );
      $this->assertStringNotContainsString(
        'template_preprocess_',
        $source,
        $template . ' names no deprecated function.'
      );
    }

    // Nothing in the module declares one under any name, which is what clears
    // the Drupal 12 removal.
    $module = (string) file_get_contents($this->packageRoot() . '/neo_icon.module');
    $this->assertDoesNotMatchRegularExpression(
      '/function template_preprocess_\w+\s*\(/',
      $module,
      'No template_preprocess_HOOK() is left in neo_icon.module.'
    );
  }

  /**
   * The theme hook definitions, as the hook itself answers them.
   *
   * @return array
   *   The `hook_theme()` return value.
   */
  private function themeHooks(): array {
    return $this->container->get('module_handler')
      ->invoke('neo_icon', 'theme', [[], 'module', 'neo_icon', $this->packageRoot()]);
  }

  /**
   * The package root.
   *
   * @return string
   *   The absolute path.
   */
  private function packageRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * The implementations the hook system resolved for a hook, in order.
   *
   * @param string $hook
   *   The hook name, without the `hook_` prefix.
   *
   * @return string[]
   *   One `module: identifier` string per implementation, where the identifier
   *   is `Class::method` for a class-based implementation and the function name
   *   for a procedural one.
   */
  private function hookImplementations(string $hook): array {
    $implementations = [];
    $this->container->get('module_handler')->invokeAllWith(
      $hook,
      static function (callable $implementation, string $module) use (&$implementations): void {
        if (is_array($implementation)) {
          $identifier = get_class($implementation[0]) . '::' . $implementation[1];
        }
        elseif (is_string($implementation)) {
          $identifier = $implementation;
        }
        else {
          $identifier = get_debug_type($implementation);
        }
        $implementations[] = $module . ': ' . $identifier;
      }
    );
    return $implementations;
  }

}
