<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_icon\Hook\NeoIconHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * The module's three behavioural hooks, seen through the hook system.
 *
 * The library build, the page attachments and the library alter are methods on
 * `Drupal\neo_icon\Hook\NeoIconHooks`. Nothing any of them decides moved with
 * them, so what is at risk here is not a body but a registration: a wrong hook
 * name, a misspelled attribute or a class in a namespace nothing scans produces
 * a class that reads correctly and is never called. A method nobody invokes is
 * not a hook implementation.
 *
 * So this test asks the module handler rather than the object. For each of the
 * three it names the implementation the hook system actually resolved and
 * checks that it is a `Class::method` identifier, because "the hook still
 * answers" and "the class is what answers it" are different statements and only
 * the second one fails when a hook quietly stays procedural. The functions
 * themselves are asserted gone as well, since the module file is loaded either
 * way and a leftover would be registered a second time beside the method.
 *
 * What the methods *decide* is not asked here. The class takes one collaborator
 * and constructs without a container, so all four behavioural criteria are
 * driven from stubs in `Drupal\Tests\neo_icon\Unit\LibraryHookDecisionsTest`,
 * and this bootstrap is spent only on the question that needs one.
 */
#[Group('neo_icon')]
final class LibraryHooksTest extends KernelTestBase {

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
   * Each of the three hooks resolves to a method on the hook class.
   *
   * Acceptance criterion: *the module handler resolves the library build, page
   * attachments and library alter hooks to class methods, with no procedural
   * implementation of any of the three left.*
   *
   * The alter is asked for under `library_info_alter`, which is the name the
   * registry holds it under — `ModuleHandler::alter()` appends `_alter` to the
   * type it is given before it looks anything up.
   */
  public function testResolvesLibraryBuildPageAttachmentsAndLibraryAlterToClassMethods(): void {
    $expected = [
      'library_info_build' => NeoIconHooks::class . '::libraryInfoBuild',
      'page_attachments' => NeoIconHooks::class . '::pageAttachments',
      'library_info_alter' => NeoIconHooks::class . '::libraryInfoAlter',
    ];
    foreach ($expected as $hook => $identifier) {
      $this->assertContains(
        $identifier,
        $this->implementationsOf($hook),
        sprintf('neo_icon implements %s on the hook class.', $hook)
      );
    }
  }

  /**
   * No procedural implementation of the three survives in the module file.
   *
   * Acceptance criterion: *the module handler resolves the library build, page
   * attachments and library alter hooks to class methods, with no procedural
   * implementation of any of the three left.*
   *
   * Two halves, because either alone would pass over a real defect. The module
   * file is included whatever the collector is told, so a function left behind
   * is still defined and still matched by the collector's `{module}_{hook}`
   * shape — it would be registered a second time next to the method rather than
   * instead of it, and the assertion above would not notice.
   */
  public function testLeavesNoProceduralImplementationOfTheThreeHooks(): void {
    foreach (['library_info_build', 'page_attachments', 'library_info_alter'] as $hook) {
      $this->assertNotContains(
        'neo_icon_' . $hook,
        $this->implementationsOf($hook),
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
