<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\Hook\NeoIconThemeHooks;
use Drupal\neo_icon\IconEntityTypeManager;
use Drupal\neo_icon\IconInterface;
use Drupal\neo_icon\IconLibraryInterface;
use Drupal\neo_icon\IconRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * What the four initial preprocess callbacks decide, without a bootstrap.
 *
 * These four were `template_preprocess_*()` functions, so every branch in them
 * used to need a booted site: two of them reached the icon repository through
 * `\Drupal::service()` to turn a string icon id into an icon. On a class that
 * repository is the one constructor argument, which is what makes a stubbed
 * lookup enough to drive all of it.
 *
 * Two of the four are the hot path of the whole package — every icon element
 * anywhere renders through `neo_icon_element` or `neo_icon` — so the absent
 * icon is asserted on both of them together rather than as an afterthought on
 * one: rendering nothing is what they do when a lookup answers with nothing,
 * and it is the branch a mistyped icon id takes on a live page.
 *
 * Discovery is not asked here. Whether the theme registry found the methods at
 * all is `Drupal\Tests\neo_icon\Kernel\ThemeHooksTest`, which spends a
 * bootstrap on the one question a bootstrap can answer.
 */
#[Group('neo_icon')]
final class ThemePreprocessDecisionsTest extends UnitTestCase {

  /**
   * The icon element preprocessor resolves a string id and renders the icon.
   *
   * Acceptance criterion: *the icon element preprocessor resolves a string icon
   * id through the icon repository and renders the icon it finds, with the icon
   * attributes it was given.*
   *
   * The repository is asked with the id in the icon argument and nothing in the
   * text argument, which is the lookup the deleted function made, and the
   * render array it answers with is what lands in the variable — with the icon
   * attributes stamped onto it rather than onto the wrapper.
   */
  public function testIconElementPreprocessorResolvesStringIconIdThroughTheRepository(): void {
    $icon = $this->icon();
    $repository = $this->createMock(IconRepositoryInterface::class);
    $repository->expects($this->once())
      ->method('getIcon')
      ->with(NULL, 'star')
      ->willReturn($icon);
    $hooks = $this->over($repository);

    $variables = [
      'icon' => 'star',
      'attributes_icon' => ['class' => ['text-lg']],
    ];
    $hooks->preprocessNeoIconElement($variables);

    $this->assertSame([
      '#theme' => 'neo_icon__font',
      '#icon' => $icon,
      '#attributes' => ['class' => ['text-lg']],
    ], $variables['icon']);

    // An icon handed over as an object is used as it stands, with no lookup.
    $untouched = $this->createMock(IconRepositoryInterface::class);
    $untouched->expects($this->never())->method('getIcon');
    $variables = ['icon' => $icon, 'attributes_icon' => []];
    $this->over($untouched)->preprocessNeoIconElement($variables);
    $this->assertSame('neo_icon__font', $variables['icon']['#theme']);
  }

  /**
   * Neither hot preprocessor renders anything for an icon that is not there.
   *
   * Acceptance criterion: *the icon element and icon preprocessors both render
   * nothing when the lookup answers an absent icon.*
   *
   * Both are asserted from one test because they are one behaviour in two
   * places, and a mistyped icon id anywhere on a site takes this branch. The
   * icon variable is blanked rather than left as the id, which is what stops
   * the template from printing it.
   */
  public function testBothPreprocessorsRenderNothingWhenTheLookupAnswersAnAbsentIcon(): void {
    $hooks = $this->hooks(NULL);

    $variables = ['icon' => 'no-such-icon', 'attributes_icon' => ['class' => ['x']]];
    $hooks->preprocessNeoIconElement($variables);
    $this->assertSame('', $variables['icon']);

    $variables = ['icon' => 'no-such-icon', 'attributes' => ['class' => []], 'children' => []];
    $hooks->preprocessNeoIcon($variables);
    $this->assertSame('', $variables['icon']);
    $this->assertSame('span', $variables['tag'], 'The default tag survives.');
    $this->assertSame(['neo-icon'], $variables['attributes']['class']);
    $this->assertArrayNotHasKey('type', $variables);
    $this->assertArrayNotHasKey('aria-hidden', $variables['attributes']);
    $this->assertArrayNotHasKey('#attached', $variables);

    // A variable holding neither an id nor an icon takes the same branch,
    // because the lookup is only reached for a string.
    $never = $this->createMock(IconRepositoryInterface::class);
    $never->expects($this->never())->method('getIcon');
    $bare = $this->over($never);
    $variables = ['icon' => NULL, 'attributes_icon' => []];
    $bare->preprocessNeoIconElement($variables);
    $this->assertSame('', $variables['icon']);
    $variables = ['icon' => NULL, 'attributes' => ['class' => []], 'children' => []];
    $bare->preprocessNeoIcon($variables);
    $this->assertSame('', $variables['icon']);
  }

  /**
   * The icon preprocessor writes everything the icon template reads.
   *
   * Acceptance criterion: *the icon preprocessor sets the tag, the type, the
   * selector and library classes, the children and the library attachment for a
   * resolved icon.*
   *
   * The classes are asserted in order and whole, because `neo-icon` is added
   * before the lookup and the other two after it, and the template's own
   * styling depends on all three being there. The same variables are asserted
   * again from a string id, since a site reaches this hook both ways and the
   * only difference between them is the lookup.
   */
  public function testIconPreprocessorSetsTagTypeSelectorClassesChildrenAndLibraryAttachment(): void {
    $icon = $this->icon('svg', 'brands', 'svg', 'neo-brands-github', ['#markup' => 'a symbol']);
    $hooks = $this->hooks($icon);

    $variables = [
      'icon' => 'github',
      'attributes' => ['class' => ['ml-2']],
      'children' => [],
    ];
    $hooks->preprocessNeoIcon($variables);

    $this->assertSame($icon, $variables['icon'], 'The icon itself reaches the template.');
    $this->assertSame('svg', $variables['tag']);
    $this->assertSame('svg', $variables['type']);
    $this->assertSame(
      ['ml-2', 'neo-icon', 'neo-icon-svg', 'neo-brands-github'],
      $variables['attributes']['class']
    );
    $this->assertSame('true', $variables['attributes']['aria-hidden']);
    $this->assertSame(['#markup' => 'a symbol'], $variables['children']);
    $this->assertSame(['neo_icon/brands'], $variables['#attached']['library']);

    // Handed the icon itself rather than its id, it writes the same variables.
    $never = $this->createMock(IconRepositoryInterface::class);
    $never->expects($this->never())->method('getIcon');
    $direct = ['icon' => $icon, 'attributes' => ['class' => ['ml-2']], 'children' => []];
    $this->over($never)->preprocessNeoIcon($direct);
    $this->assertSame($variables, $direct, 'A string id and an icon end in the same place.');
  }

  /**
   * The browser always searches and only sometimes chooses a library.
   *
   * Acceptance criterion: *the browser preprocessor adds the search input
   * always and the library select only when more than one library is offered.*
   *
   * A single library needs no chooser, and offering one that can only pick what
   * is already showing is the reason the guard is there. All three cases are
   * asserted, because none and one are different inputs taking the same branch.
   */
  public function testBrowserPreprocessorAddsSearchAlwaysAndLibrarySelectOnlyForMoreThanOneLibrary(): void {
    $hooks = $this->hooks(NULL);

    $variables = ['library_options' => ['material' => 'Material', 'brands' => 'Brands']];
    $hooks->preprocessNeoIconBrowser($variables);
    $this->assertSame('search', $variables['search_input']['#type']);
    $this->assertSame(
      ['neo-icon-browser--search'],
      $variables['search_input']['#attributes']['class']
    );
    $this->assertSame('Search...', (string) $variables['search_input']['#attributes']['placeholder']);
    $this->assertSame('select', $variables['library_input']['#type']);
    $this->assertSame(
      ['', 'material', 'brands'],
      array_keys($variables['library_input']['#options']),
      'The all-libraries option leads the libraries offered.'
    );
    $this->assertSame(
      '- All Libraries -',
      (string) $variables['library_input']['#options']['']
    );
    $this->assertSame(
      ['neo-icon-browser--libraries'],
      $variables['library_input']['#attributes']['class']
    );

    // One library is a chooser with nothing to choose, so there is none.
    $variables = ['library_options' => ['material' => 'Material']];
    $hooks->preprocessNeoIconBrowser($variables);
    $this->assertSame('search', $variables['search_input']['#type']);
    $this->assertArrayNotHasKey('library_input', $variables);

    // And neither is none.
    $variables = ['library_options' => []];
    $hooks->preprocessNeoIconBrowser($variables);
    $this->assertSame('search', $variables['search_input']['#type']);
    $this->assertArrayNotHasKey('library_input', $variables);
  }

  /**
   * The library page takes its type and its browser off the render element.
   *
   * Acceptance criterion: *the library preprocessor sets the type and the
   * browser element from the icon library on the render element.*
   *
   * The browser is built for that one library and asked to show its info, which
   * is what makes the library page a browser scoped to itself rather than the
   * site-wide one.
   */
  public function testLibraryPreprocessorSetsTypeAndBrowserElementFromTheIconLibrary(): void {
    $library = $this->createMock(IconLibraryInterface::class);
    $library->method('getType')->willReturn('font');
    $library->method('id')->willReturn('material');

    $variables = ['element' => ['#neo_icon_library' => $library]];
    $this->hooks(NULL)->preprocessNeoIconLibrary($variables);

    $this->assertSame('font', $variables['type']);
    $this->assertSame([
      '#type' => 'neo_icon_browser',
      '#libraries' => ['material'],
      '#show_info' => TRUE,
    ], $variables['content']['browser']);
  }

  /**
   * {@inheritdoc}
   *
   * The browser preprocessor's two labels go through `t()`, which the moved
   * body kept, so a container carrying nothing but the translation service
   * stands in for the one a render would have had.
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Builds the hook class over a repository answering one lookup.
   *
   * @param \Drupal\neo_icon\IconInterface|null $found
   *   What the repository answers every lookup with — an icon, or NULL where
   *   nothing matches the id it was given.
   *
   * @return \Drupal\neo_icon\Hook\NeoIconThemeHooks
   *   The hook class under test, constructed rather than fetched.
   */
  private function hooks(?IconInterface $found): NeoIconThemeHooks {
    $repository = $this->createMock(IconRepositoryInterface::class);
    $repository->method('getIcon')->willReturn($found);
    return $this->over($repository);
  }

  /**
   * Builds the hook class over a repository and nothing else that matters.
   *
   * The class also carries the module's four `hook_preprocess_HOOK`
   * implementations, which take a route match and the entity icon manager. None
   * of the five methods asserted here reaches either, so both are supplied as
   * inert doubles in one place rather than at each of this file's five
   * constructions. What those four decide is
   * `Drupal\Tests\neo_icon\Unit\PreprocessHookDecisionsTest`.
   *
   * @param \Drupal\neo_icon\IconRepositoryInterface $repository
   *   The icon repository the two hot preprocessors ask for an icon.
   *
   * @return \Drupal\neo_icon\Hook\NeoIconThemeHooks
   *   The hook class under test, constructed rather than fetched.
   */
  private function over(IconRepositoryInterface $repository): NeoIconThemeHooks {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn((object) ['data' => []]);
    return new NeoIconThemeHooks(
      $repository,
      $this->createMock(RouteMatchInterface::class),
      new IconEntityTypeManager($this->createMock(ModuleHandlerInterface::class), $cache)
    );
  }

  /**
   * Builds an icon double over an icon library double.
   *
   * @param string $type
   *   The library's type, which becomes the type variable and a class.
   * @param string $libraryName
   *   The library name, which the attached Drupal library is named from.
   * @param string $tag
   *   The tag the icon renders as.
   * @param string $selector
   *   The icon's own class.
   * @param array $children
   *   What the icon renders inside its tag.
   *
   * @return \Drupal\neo_icon\IconInterface
   *   The icon.
   */
  private function icon(string $type = 'font', string $libraryName = 'material', string $tag = 'i', string $selector = 'neo-material-star', array $children = []): IconInterface {
    $library = $this->createMock(IconLibraryInterface::class);
    $library->method('getType')->willReturn($type);
    $library->method('getLibraryName')->willReturn($libraryName);

    $icon = $this->createMock(IconInterface::class);
    $icon->method('getLibrary')->willReturn($library);
    $icon->method('getTag')->willReturn($tag);
    $icon->method('getSelector')->willReturn($selector);
    $icon->method('getChildren')->willReturn($children);
    $icon->method('render')->willReturn(['#theme' => 'neo_icon__' . $type, '#icon' => $icon]);
    return $icon;
  }

}
