<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\Hook\NeoIconThemeHooks;
use Drupal\neo_icon\IconElement;
use Drupal\neo_icon\IconEntityTypeManager;
use Drupal\neo_icon\IconRepositoryInterface;
use Drupal\node\NodeTypeInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * What the four preprocess hook implementations decide, without a bootstrap.
 *
 * These four hang icons on markup four other extensions produce: the entity
 * list-builder table, the node add list, the entity add list and the accordion
 * item. As functions every one of them reached the container — twice for the
 * route match, three times for the entity icon manager — so none of these
 * branches could be driven without a booted site. On a class both arrive as
 * constructor arguments, which is the whole of what this ticket bought and the
 * reason every assertion below runs from doubles.
 *
 * The table preprocessor's no-route branch is the clearest case. It is what
 * makes the hook safe when a list builder renders from Drush or a queue worker,
 * it is invisible on any page, and a double is the only way to ask for it.
 *
 * Discovery is not asked here. Whether the hook system found the four methods
 * at all is `Drupal\Tests\neo_icon\Kernel\PreprocessHooksTest`, which spends a
 * bootstrap on the one question a bootstrap can answer, and what the markup
 * looks like afterwards is this plan's visual ticket.
 */
#[Group('neo_icon')]
final class PreprocessHookDecisionsTest extends UnitTestCase {

  /**
   * Links the link generator was handed, most recent last.
   *
   * @var array<int, array{0: mixed, 1: \Drupal\Core\Url}>
   */
  private array $generated = [];

  /**
   * The table preprocessor wraps two cells, and only for an icon entity list.
   *
   * Acceptance criterion: *the table preprocessor wraps the title and name
   * cells of an entity list whose entity type supports icons, and leaves the
   * rows untouched when there is no route object or the entity type is
   * unsupported.*
   *
   * Three inputs, one of which no page can produce. The no-route branch is what
   * makes the hook safe under Drush and a queue worker — a table rendered
   * outside a request has no route object to ask — and the comment above it in
   * the moved body says so. The rows are compared whole in both negative cases,
   * because "no icon was added" and "nothing else moved either" are different
   * promises.
   */
  public function testTablePreprocessorWrapsTitleAndNameCellsOfSupportedEntityListAndLeavesRowsOtherwise(): void {
    $variables = ['rows' => $this->rows()];
    $this->hooks($this->route('node_type'), ['node_type'])->preprocessTable($variables);

    $title = $variables['rows'][0]['cells']['title']['content'];
    $name = $variables['rows'][0]['cells']['name']['content'];
    $this->assertInstanceOf(IconElement::class, $title);
    $this->assertInstanceOf(IconElement::class, $name);
    $this->assertSame('Article', $this->textOf($title));
    $this->assertSame('article', $this->textOf($name));
    $this->assertSame(['entity.node_type'], $this->prefixOf($title));
    $this->assertSame(['entity.node_type'], $this->prefixOf($name));

    // Only those two keys, and only where the cell carries content.
    $this->assertSame('Edit', $variables['rows'][0]['cells']['operations']['content']);
    $this->assertSame(['data' => 'Basic page'], $variables['rows'][1]['cells']['title']);
    $this->assertSame([], $variables['rows'][2]['cells']);

    // An entity type with no icons is left exactly as it arrived.
    $unsupported = ['rows' => $this->rows()];
    $this->hooks($this->route('node_type'), [])->preprocessTable($unsupported);
    $this->assertEquals($this->rows(), $unsupported['rows']);

    // And so is a table rendered where there is no route object at all.
    $routeless = ['rows' => $this->rows()];
    $this->hooks(NULL, ['node_type'])->preprocessTable($routeless);
    $this->assertEquals($this->rows(), $routeless['rows']);

    // A route that lists nothing takes the same branch.
    $notAList = ['rows' => $this->rows()];
    $this->hooks($this->route(NULL), ['node_type'])->preprocessTable($notAList);
    $this->assertEquals($this->rows(), $notAList['rows']);
  }

  /**
   * The node add list is rebuilt, one entry per type, each with an icon link.
   *
   * Acceptance criterion: *the node add list preprocessor builds one entry per
   * node type, each with an icon link and the type's description.*
   *
   * The link is asserted through what the link generator was handed rather than
   * through the string it answered with, because the icon is in the link text
   * and the text is the only part of it this hook decides. The variable is
   * seeded with a stale entry first: the body assigns a fresh array rather than
   * appending, and a site whose add list gained a type would otherwise keep the
   * old one.
   */
  public function testNodeAddListPreprocessorBuildsOneEntryPerNodeTypeWithIconLinkAndDescription(): void {
    $variables = [
      'types' => ['stale' => 'left over from somewhere else'],
      'content' => [
        $this->nodeType('article', 'Article', 'Use articles for time-sensitive content.'),
        $this->nodeType('page', 'Basic page', 'Use basic pages for static content.'),
      ],
    ];

    $this->hooks(NULL, [])->preprocessNodeAddList($variables);

    $this->assertSame(['article', 'page'], array_keys($variables['types']));
    $this->assertSame('article', $variables['types']['article']['type']);
    $this->assertSame(
      ['#markup' => 'Use articles for time-sensitive content.'],
      $variables['types']['article']['description']
    );
    $this->assertSame(
      'link to node.add/article',
      (string) $variables['types']['article']['add_link']
    );

    // Two links, each pointing at the add form for its own type.
    $this->assertCount(2, $this->generated);
    foreach ([0 => 'article', 1 => 'page'] as $i => $typeId) {
      [$text, $url] = $this->generated[$i];
      $this->assertSame('node.add', $url->getRouteName());
      $this->assertSame(['node_type' => $typeId], $url->getRouteParameters());
      $this->assertInstanceOf(IconElement::class, $text);
      $this->assertSame(['entity.node'], $this->prefixOf($text));
    }
    $this->assertSame('Article', $this->textOf($this->generated[0][0]));
    $this->assertSame('Basic page', $this->textOf($this->generated[1][0]));

    // With nothing to list, the variable is emptied rather than left alone.
    $empty = ['types' => ['stale' => 'x'], 'content' => []];
    $this->hooks(NULL, [])->preprocessNodeAddList($empty);
    $this->assertSame([], $empty['types']);
  }

  /**
   * Every bundle link gets an icon, under a type id from one of two places.
   *
   * Acceptance criterion: *the entity add list preprocessor sets an icon on
   * every bundle's link, deriving the entity type id from the route when it has
   * one and from the bundles' links when it has not.*
   *
   * The second branch moves with a latent oddity and is asserted as it behaves
   * rather than as it reads: the loop assigns from `array_key_last()` on every
   * bundle's link in turn instead of stopping at the first, so the *last*
   * bundle decides the id used for all of them, and what it reads is the route
   * parameter's name rather than its value. It has never produced a wrong icon
   * because one add-list page's bundles share an entity type. This plan records
   * it and moves it unchanged; a test that asserted the tidier behaviour would
   * be asserting a fix nobody has made.
   */
  public function testEntityAddListPreprocessorSetsIconOnEveryBundleLinkFromRouteOrFromTheBundleLinks(): void {
    $fromRoute = ['bundles' => $this->bundles()];
    $this->hooks(NULL, [], 'block_content')->preprocessEntityAddList($fromRoute);

    foreach (['basic', 'banner'] as $bundle) {
      $text = $fromRoute['bundles'][$bundle]['add_link']->getText();
      $this->assertInstanceOf(IconElement::class, $text);
      $this->assertSame(['entity.block_content'], $this->prefixOf($text));
    }
    $this->assertSame('Basic block', $this->textOf($fromRoute['bundles']['basic']['add_link']->getText()));
    $this->assertSame('Banner', $this->textOf($fromRoute['bundles']['banner']['add_link']->getText()));

    // With no route parameter the links themselves answer, and the last one
    // asked wins for every bundle — the oddity this move carries forward.
    $fromLinks = ['bundles' => $this->bundles()];
    $this->hooks(NULL, [])->preprocessEntityAddList($fromLinks);
    foreach (['basic', 'banner'] as $bundle) {
      $this->assertSame(
        ['entity.banner_type'],
        $this->prefixOf($fromLinks['bundles'][$bundle]['add_link']->getText())
      );
    }

    // Nothing to hang an icon on is not an error, and neither is a page whose
    // links carry no route parameters to read a type id out of.
    $none = ['bundles' => []];
    $this->hooks(NULL, [])->preprocessEntityAddList($none);
    $this->assertSame([], $none['bundles']);

    $unrouted = [
      'bundles' => [
        'basic' => [
          'label' => 'Basic block',
          'add_link' => Link::fromTextAndUrl('Basic block', Url::fromRoute('block_content.add_page')),
        ],
      ],
    ];
    $this->hooks(NULL, [])->preprocessEntityAddList($unrouted);
    $this->assertSame('Basic block', $unrouted['bundles']['basic']['add_link']->getText());
  }

  /**
   * The accordion item title is wrapped only when the element carries an icon.
   *
   * Acceptance criterion: *the accordion item preprocessor wraps the title only
   * when the element carries an icon.*
   *
   * The negative case is the one that matters on a page: neo's own accordions
   * set no icon, so the title has to arrive at the template as the string it
   * was rather than as an element resolving nothing.
   */
  public function testAccordionItemPreprocessorWrapsTheTitleOnlyWhenTheElementCarriesAnIcon(): void {
    $withIcon = ['title' => 'Shipping', 'element' => ['#icon' => 'truck']];
    $this->hooks(NULL, [])->preprocessAccordionItem($withIcon);
    $this->assertInstanceOf(IconElement::class, $withIcon['title']);
    $this->assertSame('Shipping', $this->textOf($withIcon['title']));
    $this->assertSame('truck', $this->iconOf($withIcon['title']));

    $withoutIcon = ['title' => 'Shipping', 'element' => []];
    $this->hooks(NULL, [])->preprocessAccordionItem($withoutIcon);
    $this->assertSame('Shipping', $withoutIcon['title']);

    $emptyIcon = ['title' => 'Shipping', 'element' => ['#icon' => '']];
    $this->hooks(NULL, [])->preprocessAccordionItem($emptyIcon);
    $this->assertSame('Shipping', $emptyIcon['title']);
  }

  /**
   * All four build the element themselves, none goes out through the façade.
   *
   * Acceptance criterion: *all four bodies construct the icon element directly
   * and none calls the module's global helper.*
   *
   * This is a statement about what is not written, so it is read from the
   * source rather than inferred from a call. The reason it is a criterion at
   * all is that a class-based hook calling a global out of a `.module` file
   * makes the class depend on that file having been loaded — the defect neo's
   * own conversion found and fixed the same way. The façades are untouched and
   * everybody else still calls them; this module stops going out through its
   * own front door.
   */
  public function testAllFourBodiesConstructTheIconElementRatherThanCallingTheModuleGlobalHelper(): void {
    $methods = [
      'preprocessTable',
      'preprocessNodeAddList',
      'preprocessEntityAddList',
      'preprocessAccordionItem',
    ];
    foreach ($methods as $method) {
      $body = $this->bodyOf($method);
      $this->assertStringContainsString(
        'new IconElement(',
        $body,
        $method . '() constructs the icon element itself.'
      );
      $this->assertDoesNotMatchRegularExpression(
        '/\bneo_icon(_admin|_entity|_entity_type)?\s*\(/',
        $body,
        $method . '() calls none of the module\'s global helpers.'
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * The node add list body renders a link, and `Link::toString()` reaches the
   * link generator through the container when it has not been given one. It is
   * the only container service any of the four needs, and what it is handed is
   * recorded rather than thrown away: the icon lives in the link text, so the
   * generator's arguments are where this hook's decision is legible.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->generated = [];
    $generator = $this->createMock(LinkGeneratorInterface::class);
    $generator->method('generate')->willReturnCallback(
      function (mixed $text, Url $url): string {
        $this->generated[] = [$text, $url];
        return 'link to ' . $url->getRouteName() . '/' . implode('/', $url->getRouteParameters());
      }
    );
    $container = new ContainerBuilder();
    $container->set('link_generator', $generator);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Builds the hook class over a route match and an entity icon manager.
   *
   * @param \Symfony\Component\Routing\Route|null $route
   *   The route object the route match answers with, or NULL where there is
   *   none — which is every render outside a request context.
   * @param string[] $supported
   *   The entity type ids the icon entity type manager holds a plugin for.
   * @param string|null $entityTypeId
   *   What the route match answers for the `entity_type_id` parameter.
   *
   * @return \Drupal\neo_icon\Hook\NeoIconThemeHooks
   *   The hook class under test, constructed rather than fetched.
   */
  private function hooks(?Route $route, array $supported, ?string $entityTypeId = NULL): NeoIconThemeHooks {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteObject')->willReturn($route);
    $routeMatch->method('getParameter')->willReturn($entityTypeId);
    return new NeoIconThemeHooks(
      $this->createMock(IconRepositoryInterface::class),
      $routeMatch,
      $this->iconEntityTypeManager($supported)
    );
  }

  /**
   * An entity icon manager holding a plugin for each entity type id given.
   *
   * The manager is `final` and declares no interface, so it is built rather
   * than mocked: its cache backend answers with the definitions it would
   * otherwise have discovered, which is enough for the two support questions
   * these hooks ask and needs no container.
   *
   * @param string[] $supported
   *   The entity type ids to hold a plugin for.
   *
   * @return \Drupal\neo_icon\IconEntityTypeManager
   *   The manager.
   */
  private function iconEntityTypeManager(array $supported): IconEntityTypeManager {
    $definitions = [];
    foreach ($supported as $entityTypeId) {
      $definitions[$entityTypeId] = ['id' => $entityTypeId, 'label' => $entityTypeId];
    }
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn((object) ['data' => $definitions]);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('getModuleDirectories')->willReturn([]);
    return new IconEntityTypeManager($moduleHandler, $cache);
  }

  /**
   * A route listing an entity type, or listing nothing at all.
   *
   * @param string|null $entityTypeId
   *   The entity type the route's list builder shows, or NULL for a route that
   *   is not an entity list.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  private function route(?string $entityTypeId): Route {
    return new Route('/admin/structure/types', $entityTypeId ? ['_entity_list' => $entityTypeId] : []);
  }

  /**
   * The rows an entity list builder hands the table template.
   *
   * @return array
   *   Three rows: one carrying both wrappable cells and one that is not, one
   *   whose title cell holds no content key, and one with no cells at all.
   */
  private function rows(): array {
    return [
      [
        'cells' => [
          'title' => ['content' => 'Article'],
          'name' => ['content' => 'article'],
          'operations' => ['content' => 'Edit'],
        ],
      ],
      ['cells' => ['title' => ['data' => 'Basic page']]],
      ['cells' => []],
    ];
  }

  /**
   * The bundles an entity add list hands its template.
   *
   * Their links carry differently named route parameters on purpose, so that
   * the loop deriving an entity type id from them says which one it kept.
   *
   * @return array
   *   Two bundles, each with a label and an add link.
   */
  private function bundles(): array {
    return [
      'basic' => [
        'label' => 'Basic block',
        'add_link' => Link::fromTextAndUrl(
          'Basic block',
          Url::fromRoute('block_content.add_form', ['basic_type' => 'basic'])
        ),
      ],
      'banner' => [
        'label' => 'Banner',
        'add_link' => Link::fromTextAndUrl(
          'Banner',
          Url::fromRoute('block_content.add_form', ['banner_type' => 'banner'])
        ),
      ],
    ];
  }

  /**
   * A node type double.
   *
   * @param string $id
   *   The type's machine name.
   * @param string $label
   *   The type's label, which becomes the icon element's text.
   * @param string $description
   *   The type's description, which the add list prints under the link.
   *
   * @return \Drupal\node\NodeTypeInterface
   *   The node type.
   */
  private function nodeType(string $id, string $label, string $description): NodeTypeInterface {
    $type = $this->createMock(NodeTypeInterface::class);
    $type->method('id')->willReturn($id);
    $type->method('label')->willReturn($label);
    $type->method('getDescription')->willReturn($description);
    return $type;
  }

  /**
   * The source of one of the class's methods, without its docblock.
   *
   * @param string $method
   *   The method name.
   *
   * @return string
   *   Everything from the declaration to the closing brace.
   */
  private function bodyOf(string $method): string {
    $reflection = new \ReflectionMethod(NeoIconThemeHooks::class, $method);
    $lines = file((string) $reflection->getFileName());
    $this->assertNotFalse($lines, 'The hook class is readable.');
    return implode('', array_slice(
      (array) $lines,
      $reflection->getStartLine() - 1,
      $reflection->getEndLine() - $reflection->getStartLine() + 1
    ));
  }

  /**
   * The text an icon element carries.
   *
   * @param mixed $element
   *   The element one of the four built.
   *
   * @return string
   *   The text, translated through a stub so that reading it needs no more of a
   *   container than building it did.
   */
  private function textOf(mixed $element): string {
    $this->assertInstanceOf(IconElement::class, $element);
    $element->setStringTranslation($this->getStringTranslationStub());
    return (string) $element->getText();
  }

  /**
   * The icon lookup prefix an icon element carries.
   *
   * @param mixed $element
   *   The element one of the four built.
   *
   * @return array
   *   The prefix. It is read off the element rather than through `getIcon()`,
   *   because resolving an icon reaches the icon repository through a static
   *   and this suite has no container to answer with one.
   */
  private function prefixOf(mixed $element): array {
    $this->assertInstanceOf(IconElement::class, $element);
    return (array) (new \ReflectionProperty(IconElement::class, 'prefix'))->getValue($element);
  }

  /**
   * The icon id an icon element was given.
   *
   * @param mixed $element
   *   The element one of the four built.
   *
   * @return string|null
   *   The icon id, read off the element for the same reason as the prefix.
   */
  private function iconOf(mixed $element): ?string {
    $this->assertInstanceOf(IconElement::class, $element);
    $icon = (new \ReflectionProperty(IconElement::class, 'icon'))->getValue($element);
    return is_string($icon) ? $icon : NULL;
  }

}
