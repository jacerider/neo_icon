<?php

declare(strict_types=1);

namespace Drupal\neo_icon\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\neo_icon\IconElement;
use Drupal\neo_icon\IconEntityTypeManager;
use Drupal\neo_icon\IconInterface;
use Drupal\neo_icon\IconRepositoryInterface;

/**
 * The module's theme registration and its four preprocess callbacks.
 *
 * Split from the behavioural hooks the way core splits its own dozen-odd
 * `…Hooks` / `…ThemeHooks` pairs, and the way `neo` and `neo_toolbar` already
 * split theirs. The registration array below is what stood in
 * `neo_icon.module`: the same four theme hooks, in the same order, with the
 * same variables and the same render-element entry. The template path default
 * is derived from the extension's path rather than from where the
 * implementation lives, so nothing about template resolution moved with it.
 *
 * Four keys are new, and they are the reason this class exists rather than a
 * tidier `.module`. `template_preprocess_HOOK()` is deprecated as of Drupal
 * 11.3 and removed in Drupal 12, and the theme registry raises a deprecation
 * for every one it finds; this module raised four on every registry build. The
 * replacement is an **initial preprocess** entry in each theme hook's own
 * definition, naming this class and the method, which the theme manager
 * resolves through the callable resolver against the container. Position is
 * preserved exactly: an initial preprocess callback runs before every module
 * and theme preprocess function, which is where the deprecated function ran, so
 * nothing that reads the variables they set can observe the change.
 *
 * Two of the four are the hot path of the entire package. Every icon element
 * anywhere on a site renders through `neo_icon_element` or `neo_icon` — that is
 * what the module's `icon()` Twig function builds, what the `neo_icon` render
 * element builds, and what all four of the module's icon façades produce. Both
 * reached the icon repository through `\Drupal::service()` to turn a string
 * icon id into an icon; that is the one constructor argument below, and their
 * handling of an icon the lookup cannot find — render nothing — is what they
 * always did.
 *
 * Four more preprocess implementations sit below those, and they are ordinary
 * `hook_preprocess_HOOK` implementations rather than initial preprocess
 * callbacks: the entity list-builder table, the node add list, the entity add
 * list and the accordion item are theme hooks other extensions register, so
 * this module reaches them the way any module reaches somebody else's theme
 * hook. They are here rather than on the behavioural class because this is
 * where both precedent conversions in this repository put their `preprocess_*`
 * implementations, and where core puts its own. Their bodies are what stood in
 * `neo_icon.module`, with the route match and the entity icon manager arriving
 * as constructor arguments instead of through `\Drupal::`.
 *
 * They build their icon elements with `new IconElement(...)` where the
 * functions called the module's global `neo_icon()` helper. That is not
 * tidiness: a class-based hook calling a global out of a `.module` file makes
 * the class depend on that file having been loaded, which is the defect neo's
 * own conversion found and fixed the same way. The façades are untouched and
 * everybody else still calls them.
 *
 * This is not an API and it is not `final`. The methods are public because
 * core's hook collector only reads public methods; the only ones anything
 * outside the hook system reaches are the four initial preprocess methods,
 * reached through the theme registry by the callable each theme hook names.
 */
class NeoIconThemeHooks {

  use StringTranslationTrait;

  /**
   * Constructs a NeoIconThemeHooks object.
   *
   * @param \Drupal\neo_icon\IconRepositoryInterface $iconRepository
   *   The icon repository, which the two hot preprocessors use for the one
   *   thing: turning a string icon id into an icon.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match. The table preprocessor asks it for a route
   *   object, and the entity add list preprocessor for an entity type id; both
   *   went through `\Drupal::routeMatch()` as functions.
   * @param \Drupal\neo_icon\IconEntityTypeManager $iconEntityTypeManager
   *   The entity icon manager, which answers whether an entity type has icons
   *   at all. It is the concrete class because it is `final` and declares no
   *   interface, and the two support questions are its own rather than a
   *   plugin manager's.
   */
  public function __construct(
    protected readonly IconRepositoryInterface $iconRepository,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly IconEntityTypeManager $iconEntityTypeManager,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'neo_icon_element' => [
        'variables' => [
          'title' => NULL,
          'attributes' => [],
          'attributes_icon' => [],
          'position' => 'before',
          'icon' => NULL,
          'icon_only' => FALSE,
        ],
        'initial preprocess' => static::class . ':preprocessNeoIconElement',
      ],
      'neo_icon' => [
        'variables' => [
          'icon' => NULL,
          'attributes' => [],
          'children' => [],
        ],
        'initial preprocess' => static::class . ':preprocessNeoIcon',
      ],
      'neo_icon_library' => [
        'render element' => 'element',
        'initial preprocess' => static::class . ':preprocessNeoIconLibrary',
      ],
      'neo_icon_browser' => [
        'variables' => [
          'libraries' => [],
          'library_options' => [],
          'attributes' => [],
          'loader' => [],
        ],
        'initial preprocess' => static::class . ':preprocessNeoIconBrowser',
      ],
    ];
  }

  /**
   * Prepares variables for icon and text display.
   *
   * Default template: neo-icon-element.html.twig.
   *
   * This was `template_preprocess_neo_icon_element()`. It is named as the theme
   * hook's initial preprocess callback rather than being found by name, which
   * is the same position with none of the deprecation.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the icon
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessNeoIconElement(array &$variables): void {
    $icon = $variables['icon'];
    // Allow icon_id to be used as #icon.
    if (is_string($icon)) {
      $icon = $this->iconRepository->getIcon(NULL, $icon);
    }
    if ($icon instanceof IconInterface) {
      $variables['icon'] = $icon->render();
      $variables['icon']['#attributes'] = $variables['attributes_icon'];
    }
    else {
      // No icon found. We don't want to render anything.
      $variables['icon'] = '';
    }
  }

  /**
   * Prepares variables for icon and text display.
   *
   * Default template: neo-icon.html.twig.
   *
   * This was `template_preprocess_neo_icon()`, moved for the same reason and
   * into the same position.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the icon
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessNeoIcon(array &$variables): void {
    $icon = $variables['icon'];
    $variables['tag'] = 'span';
    $variables['attributes']['class'][] = 'neo-icon';
    // Allow icon_id to be used as #icon.
    if (is_string($icon)) {
      $icon = $this->iconRepository->getIcon(NULL, $icon);
    }
    if ($icon instanceof IconInterface) {
      $variables['icon'] = $icon;
      $variables['type'] = $icon->getLibrary()->getType();
      $variables['tag'] = $icon->getTag();
      $variables['attributes']['class'][] = 'neo-icon-' . $icon->getLibrary()->getType();
      $variables['attributes']['class'][] = $icon->getSelector();
      $variables['attributes']['aria-hidden'] = 'true';
      $variables['children'] = $icon->getChildren();
      $variables['#attached']['library'][] = 'neo_icon/' . $icon->getLibrary()->getLibraryName();
    }
    else {
      // No icon found. We don't want to render anything.
      $variables['icon'] = '';
    }
  }

  /**
   * Prepares variables for eXo icon library templates.
   *
   * Default template: neo-icon-library.html.twig.
   *
   * This was `template_preprocess_neo_icon_library()`, moved for the same
   * reason and into the same position. It reads `#neo_icon_library` off the
   * render element unguarded, which every caller sets and nothing checks; that
   * is recorded as a finding by this ticket's plan and travels forward as it
   * stood.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the icon
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessNeoIconLibrary(array &$variables): void {
    /** @var \Drupal\neo_icon\IconLibraryInterface $library */
    $library = $variables['element']['#neo_icon_library'];
    $variables['type'] = $library->getType();
    $variables['content']['browser'] = [
      '#type' => 'neo_icon_browser',
      '#libraries' => [$library->id()],
      '#show_info' => TRUE,
    ];
  }

  /**
   * Prepares variables for icon and text display.
   *
   * Default template: neo-icon-browser.html.twig.
   *
   * This was `template_preprocess_neo_icon_browser()`, moved for the same
   * reason and into the same position. The library select's `count() > 1` guard
   * is kept verbatim: a chooser offering the one library already showing is
   * what it exists to avoid.
   *
   * Its two labels went through the global `t()`, which on a class is the
   * translation trait's `$this->t()` — the same `TranslatableMarkup` from the
   * same literal, and the only edit either body took beyond the repository.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the icon
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessNeoIconBrowser(array &$variables): void {
    $variables['search_input'] = [
      '#type' => 'search',
      '#attributes' => [
        'class' => ['neo-icon-browser--search'],
        'placeholder' => $this->t('Search...'),
      ],
    ];
    if (!empty($variables['library_options']) && count($variables['library_options']) > 1) {
      $variables['library_input'] = [
        '#type' => 'select',
        '#options' => ['' => $this->t('- All Libraries -')] + $variables['library_options'],
        '#attributes' => [
          'class' => ['neo-icon-browser--libraries'],
        ],
      ];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for list builder tables.
   */
  #[Hook('preprocess_table')]
  public function preprocessTable(array &$variables): void {
    // There is no route object outside a request context, such as when a table
    // is rendered from Drush or a queue worker.
    $route = $this->routeMatch->getRouteObject();
    if ($route && ($entityTypeId = $route->getDefault('_entity_list'))) {
      if ($this->iconEntityTypeManager->isSupportedEntityTypeId($entityTypeId)) {
        foreach ($variables['rows'] as $i => $row) {
          if (!empty($row['cells'])) {
            foreach ([
              'title',
              'name',
            ] as $key) {
              if (isset($row['cells'][$key])) {
                if (isset($row['cells'][$key]['content'])) {
                  $variables['rows'][$i]['cells'][$key]['content'] = new IconElement($row['cells'][$key]['content'], NULL, NULL, ['entity.' . $entityTypeId]);
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for the node add list.
   */
  #[Hook('preprocess_node_add_list')]
  public function preprocessNodeAddList(array &$variables): void {
    $variables['types'] = [];
    if (!empty($variables['content'])) {
      foreach ($variables['content'] as $type) {
        $variables['types'][$type->id()] = [
          'type' => $type->id(),
          'add_link' => Link::fromTextAndUrl(new IconElement($type->label(), NULL, NULL, ['entity.node']), Url::fromRoute('node.add', ['node_type' => $type->id()]))->toString(),
          'description' => [
            '#markup' => $type->getDescription(),
          ],
        ];
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for the entity add list.
   *
   * The first loop keeps whichever entity type id it saw last rather than
   * breaking on the first, and reads it off `array_key_last()` on a link's
   * route parameters. It has never produced a wrong icon, because the bundles
   * on one add-list page share an entity type; it moves as it stood, because a
   * move that also changed behaviour could not be reviewed as a move.
   */
  #[Hook('preprocess_entity_add_list')]
  public function preprocessEntityAddList(array &$variables): void {
    if (!empty($variables['bundles'])) {
      $entityTypeId = $this->routeMatch->getParameter('entity_type_id');
      if (!$entityTypeId) {
        foreach ($variables['bundles'] as $data) {
          /** @var \Drupal\Core\Link $link */
          $link = $data['add_link'];
          $params = $link->getUrl()->getRouteParameters();
          if ($params) {
            $entityTypeId = array_key_last($params);
          }
        }
      }
      if ($entityTypeId) {
        foreach ($variables['bundles'] as $data) {
          /** @var \Drupal\Core\Link $link */
          $link = $data['add_link'];
          $link->setText(new IconElement($data['label'], NULL, NULL, ['entity.' . $entityTypeId]));
        }
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for accordion items.
   */
  #[Hook('preprocess_accordion_item')]
  public function preprocessAccordionItem(array &$variables): void {
    if (!empty($variables['element']['#icon'])) {
      $variables['title'] = new IconElement($variables['title'], $variables['element']['#icon']);
    }
  }

}
