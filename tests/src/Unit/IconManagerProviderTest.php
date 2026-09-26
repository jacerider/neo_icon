<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\IconManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies which extensions may provide an icon definition.
 *
 * Discovery reads `*.neo.icon.yml` from theme directories as well as module
 * ones, but the plugin manager's parent keeps a definition only when its
 * provider is an enabled module. A theme's definitions were read and then
 * thrown away, with nothing logged, so a theme could not name an icon at all.
 */
#[Group('neo_icon')]
final class IconManagerProviderTest extends UnitTestCase {

  /**
   * It accepts an enabled module as a provider.
   */
  public function testItAcceptsAnEnabledModule(): void {
    $this->assertTrue($this->providerExists('neo_icon', modules: ['neo_icon']));
  }

  /**
   * It accepts an installed theme as a provider.
   *
   * The theme is deliberately not also a module, so the assertion says the
   * theme branch ran rather than agreeing with the parent's module check.
   */
  public function testItAcceptsAnInstalledTheme(): void {
    $this->assertTrue($this->providerExists('neo_back', themes: ['neo_back']));
  }

  /**
   * It rejects a provider that is neither an enabled module nor a theme.
   */
  public function testItRejectsAnUnknownProvider(): void {
    $this->assertFalse($this->providerExists(
      'missing',
      modules: ['neo_icon'],
      themes: ['neo_back'],
    ));
  }

  /**
   * Asks a manager built over the given extensions about a provider.
   *
   * @param string $provider
   *   The provider to ask about.
   * @param string[] $modules
   *   The enabled modules.
   * @param string[] $themes
   *   The installed themes.
   */
  private function providerExists(
    string $provider,
    array $modules = [],
    array $themes = [],
  ): bool {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')
      ->willReturnCallback(fn (string $name) => in_array($name, $modules, TRUE));
    $themeHandler = $this->createMock(ThemeHandlerInterface::class);
    $themeHandler->method('themeExists')
      ->willReturnCallback(fn (string $name) => in_array($name, $themes, TRUE));
    $manager = new IconManager(
      $moduleHandler,
      $themeHandler,
      $this->createMock(CacheBackendInterface::class),
    );
    return (new \ReflectionMethod($manager, 'providerExists'))
      ->invoke($manager, $provider);
  }

}
