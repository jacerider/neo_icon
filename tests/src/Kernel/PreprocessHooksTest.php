<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_icon\Hook\NeoIconThemeHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * The module's four preprocess hook implementations, seen through the hooks.
 *
 * These four are not the module's own theme hooks' preprocessors — those are
 * initial preprocess callbacks named from `hook_theme()` and asserted by
 * `Drupal\Tests\neo_icon\Kernel\ThemeHooksTest`. These are ordinary
 * `hook_preprocess_HOOK` implementations against four theme hooks other
 * extensions register: the list-builder table, the node add list, the entity
 * add list and the accordion item. They carry no deadline and they moved for
 * the reason the rest of the file moved.
 *
 * Which makes registration, not behaviour, the whole of the risk here, and it
 * is why this test asks the module handler rather than the object. A method
 * nobody invokes is not a hook implementation: a misspelled attribute or a
 * hook name that does not match the theme hook produces a class that reads
 * correctly and never runs, and the table would simply come back without its
 * icons. The functions are asserted gone in the same breath, because the module
 * file is included whatever the collector is told — a leftover would be
 * registered a second time beside the method rather than instead of it, and an
 * assertion that the method is there would not notice.
 *
 * What the four *decide* is not asked here. The class takes three injectable
 * collaborators and constructs without a container, so every behavioural
 * criterion is driven from stubs in
 * `Drupal\Tests\neo_icon\Unit\PreprocessHookDecisionsTest`, and this bootstrap
 * is spent only on the question that needs one.
 */
#[Group('neo_icon')]
final class PreprocessHooksTest extends KernelTestBase {

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
   * All four preprocess hooks resolve to methods, and nothing procedural does.
   *
   * Acceptance criterion: *the module handler resolves all four preprocess
   * hooks to class methods, with no procedural implementation of any of them
   * left.*
   *
   * Both halves are asserted per hook rather than in two passes, so that a
   * failure names the one hook that did not move rather than the set.
   */
  public function testResolvesAllFourPreprocessHooksToClassMethodsAndLeavesNothingProcedural(): void {
    $expected = [
      'preprocess_table' => 'preprocessTable',
      'preprocess_node_add_list' => 'preprocessNodeAddList',
      'preprocess_entity_add_list' => 'preprocessEntityAddList',
      'preprocess_accordion_item' => 'preprocessAccordionItem',
    ];

    foreach ($expected as $hook => $method) {
      $found = $this->implementationsOf($hook);
      $this->assertContains(
        NeoIconThemeHooks::class . '::' . $method,
        $found,
        sprintf('neo_icon implements %s on the theme hook class.', $hook)
      );
      $this->assertNotContains(
        'neo_icon_' . $hook,
        $found,
        sprintf('Nothing procedural answers %s for neo_icon.', $hook)
      );
      $this->assertFalse(
        function_exists('neo_icon_' . $hook),
        sprintf('neo_icon_%s() is gone from the module file.', $hook)
      );
    }
  }

  /**
   * The identifiers this module's implementations of a hook resolved to.
   *
   * @param string $hook
   *   The hook name, without the `hook_` prefix.
   *
   * @return string[]
   *   One identifier per implementation registered for `neo_icon`, which is
   *   `Class::method` for a class-based implementation and the bare function
   *   name for a procedural one.
   */
  private function implementationsOf(string $hook): array {
    $found = [];
    $this->container->get('module_handler')->invokeAllWith(
      $hook,
      static function (callable $implementation, string $module) use (&$found): void {
        if ($module !== 'neo_icon') {
          return;
        }
        $found[] = is_array($implementation)
          ? get_class($implementation[0]) . '::' . $implementation[1]
          : (is_string($implementation) ? $implementation : get_debug_type($implementation));
      }
    );
    return $found;
  }

}
