<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\neo_icon\Hook\NeoIconFormHooks;
use Drupal\neo_icon\IconElement;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * The module's last procedural hook, and the scan skip it makes true.
 *
 * The form alter that puts an icon select on a config entity's form is now
 * `Drupal\neo_icon\Hook\NeoIconFormHooks`, and the entity builder it registers
 * is a public static method on the same class, named by its `Class::method`
 * string in `#entity_builders`.
 *
 * With it moved, the module has no procedural hook implementation left, so it
 * declares the container parameter core provides for exactly that statement.
 * That parameter is why three of these assertions exist: core's collector
 * matches a procedural implementation by the shape `{module}_{hook}`, so
 * `neo_icon_admin()`, `neo_icon_entity()` and `neo_icon_entity_type()` — three
 * of the module's four surviving icon façades — were registered as
 * implementations of a `hook_admin`, a `hook_entity` and a `hook_entity_type`
 * that do not exist anywhere.
 *
 * What the form alter *decides* is not asked here. The class takes one
 * injectable collaborator and constructs without a container, so the two early
 * returns and the media-type branch are driven from stubs in
 * `Drupal\Tests\neo_icon\Unit\FormAlterDecisionsTest`, and this bootstrap is
 * spent on the four questions that need one: that the hook system found the
 * method, that the builder string resolves and still writes what it wrote, that
 * the collector reads nothing procedural out of the file any more, and that all
 * four façades still answer.
 */
#[Group('neo_icon')]
final class FormHooksTest extends KernelTestBase {

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
   * The form alter resolves to a class method and nothing procedural answers.
   *
   * Acceptance criterion: *the module handler resolves the form alter to a
   * class method, with no procedural implementation left.*
   *
   * Both halves are needed. The module file is included whatever the collector
   * is told, so a leftover function would be registered beside the method
   * rather than instead of it, and an assertion that the method is there would
   * not notice.
   */
  public function testResolvesTheFormAlterToClassMethodWithNothingProceduralLeft(): void {
    $found = $this->implementationsOf('form_alter');

    $this->assertContains(
      NeoIconFormHooks::class . '::formAlter',
      $found,
      'neo_icon implements form_alter on the form hook class.'
    );
    $this->assertNotContains(
      'neo_icon_form_alter',
      $found,
      'Nothing procedural answers form_alter for neo_icon.'
    );
    $this->assertFalse(
      function_exists('neo_icon_form_alter'),
      'neo_icon_form_alter() is gone from the module file.'
    );
  }

  /**
   * The entity builder is named by string and still writes what it wrote.
   *
   * Acceptance criterion: *the entity builder is registered as a
   * class-and-method string, still writes an icon, clears an empty one and
   * invalidates the module's cache tag, and the formatter's `@see` names the
   * method.*
   *
   * The string is resolved rather than compared, because `#entity_builders`
   * entries are invoked with `call_user_func_array()` and a name that does not
   * resolve is a fatal error on save rather than a missing icon. It is invoked
   * through that string for the same reason: what core will do with the entry
   * is the thing under test, not what the method does when called directly.
   */
  public function testRegistersTheEntityBuilderByStringThatWritesClearsAndInvalidates(): void {
    $form = ['#form_id' => 'node_type_edit_form'];
    $entity = $this->configEntity('node_type', 'article');
    $this->container->get(NeoIconFormHooks::class)
      ->formAlter($form, $this->formState($entity), 'node_type_edit_form');

    $builder = NeoIconFormHooks::class . '::configEntityBuild';
    $this->assertSame([$builder], $form['#entity_builders']);
    $this->assertIsCallable($builder, 'The entity builder string resolves.');

    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects($this->exactly(2))
      ->method('invalidateTags')
      ->with(['neo_icon']);
    $this->container->set('cache_tags.invalidator', $invalidator);

    // An icon is written under the key the entity icon manager derives.
    $written = $this->configEntity('node_type', 'article');
    $written->expects($this->once())
      ->method('setThirdPartySetting')
      ->with('neo_icon', 'entity:node_type:article', 'star');
    $written->expects($this->never())->method('unsetThirdPartySetting');
    call_user_func_array($builder, ['node_type', $written, &$form, $this->formState($entity, 'star')]);

    // An empty one clears the setting rather than storing nothing.
    $cleared = $this->configEntity('node_type', 'article');
    $cleared->expects($this->never())->method('setThirdPartySetting');
    $cleared->expects($this->once())
      ->method('unsetThirdPartySetting')
      ->with('neo_icon', 'entity:node_type:article');
    call_user_func_array($builder, ['node_type', $cleared, &$form, $this->formState($entity, '')]);

    // The one place outside this module's own hooks that names the callback is
    // a docblock line, and it names the method now.
    $formatter = (string) file_get_contents(
      $this->modulePath() . '/src/Plugin/Field/FieldFormatter/EntityReferenceIconFormatter.php'
    );
    $this->assertStringContainsString('@see \\' . NeoIconFormHooks::class . '::configEntityBuild()', $formatter);
    $this->assertStringNotContainsString('neo_icon_form_config_entity_build', $formatter);
  }

  /**
   * The collector reads nothing procedural out of the module any more.
   *
   * Acceptance criterion: *the module has no procedural hook implementation
   * left, declares the hook scan skip, and registers nothing under the three
   * hook names the façades were misread as.*
   *
   * The declaration is read out of the file rather than off the container:
   * core removes every `*.skip_procedural_hook_scan` parameter once the
   * container is built, because it is only needed while building it.
   *
   * @see \Drupal\Core\Hook\HookCollectorPass::process()
   */
  public function testDeclaresTheScanSkipAndLeavesNoProceduralHookOrMisreadFacade(): void {
    $services = Yaml::decode((string) file_get_contents($this->modulePath() . '/neo_icon.services.yml'));
    $this->assertTrue(
      $services['parameters']['neo_icon.skip_procedural_hook_scan'] ?? FALSE,
      'The module declares the container parameter that stops the procedural scan.'
    );

    // What the skip buys, and the reason it is not cosmetic here: the
    // collector matches a procedural implementation by the shape
    // `{module}_{hook}`, so three of the four surviving façades were read as
    // implementations of hooks that do not exist anywhere.
    $moduleHandler = $this->container->get('module_handler');
    foreach (['admin', 'entity', 'entity_type'] as $hook) {
      $this->assertFalse(
        $moduleHandler->hasImplementations($hook, 'neo_icon'),
        sprintf('The %s façade is not registered as an implementation of hook_%s.', $hook, $hook)
      );
    }

    // And every hook the module does implement is answered by a class method
    // rather than by a function name.
    $hooks = [
      'library_info_build',
      'library_info_alter',
      'page_attachments',
      'theme',
      'preprocess_table',
      'preprocess_node_add_list',
      'preprocess_entity_add_list',
      'preprocess_accordion_item',
      'form_alter',
    ];
    foreach ($hooks as $hook) {
      $found = $this->implementationsOf($hook);
      $this->assertNotEmpty($found, 'neo_icon still implements ' . $hook . '.');
      foreach ($found as $implementation) {
        $this->assertStringContainsString(
          '::',
          $implementation,
          $hook . ' is implemented by a class method.'
        );
      }
    }
  }

  /**
   * The four façades are all that is left, and all four still answer.
   *
   * Acceptance criterion: *all four icon façades are still defined and still
   * answer, and the module file holds nothing else.*
   *
   * Extension loading is unaffected by the scan skip — the module handler
   * includes the `.module` whatever the collector is told — and this is the
   * assertion that says so out loud, because roughly forty call sites in other
   * packages, the files taking the module's icon trait and every Twig `icon()`
   * call reach this module through these four names.
   */
  public function testKeepsTheFourIconFacadesAnsweringWithTheModuleFileHoldingNothingElse(): void {
    foreach (['neo_icon', 'neo_icon_admin', 'neo_icon_entity', 'neo_icon_entity_type'] as $facade) {
      $this->assertTrue(function_exists($facade), $facade . '() is still defined.');
    }

    $this->assertSame('Star', (string) neo_icon('Star', 'star')->getText());
    $this->assertSame('Settings', (string) neo_icon_admin('Settings')->getText());

    $role = Role::create(['id' => 'test_role', 'label' => 'Test Role']);
    // The entity façade is the deprecated one; calling it is the point.
    // @phpstan-ignore function.deprecated
    $entityElement = neo_icon_entity($role);
    $this->assertSame('Test Role', (string) $entityElement->getText());
    $this->assertSame(['entity.user_role'], $this->prefixOf($entityElement));

    $definition = $this->container->get('entity_type.manager')->getDefinition('user_role');
    $typeElement = neo_icon_entity_type($definition, 'Chosen');
    $this->assertSame('Chosen', (string) $typeElement->getText());
    $this->assertSame(['entity.user_role'], $this->prefixOf($typeElement));

    // And nothing else: four functions, no hook, no preprocessor.
    $module = (string) file_get_contents($this->modulePath() . '/neo_icon.module');
    preg_match_all('/^\s*function\s+(\w+)/m', $module, $matches);
    $this->assertSame([
      'neo_icon',
      'neo_icon_admin',
      'neo_icon_entity',
      'neo_icon_entity_type',
    ], $matches[1]);
    $this->assertStringNotContainsString('template_preprocess_', $module);
    $this->assertStringNotContainsString('#[Hook', $module);
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

  /**
   * A config entity double of the type and id given.
   *
   * @param string $entityTypeId
   *   The entity type id, which decides whether the alter acts at all.
   * @param string $id
   *   The entity id, which the icon setting is keyed under.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject&\Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The entity, still a mock so that the writes can be asserted on it.
   */
  private function configEntity(string $entityTypeId, string $id): MockObject {
    $entity = $this->createMock(ConfigEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn($entityTypeId);
    $entity->method('id')->willReturn($id);
    $entity->method('getThirdPartySetting')->willReturn('');
    return $entity;
  }

  /**
   * A form state whose form object is an entity form over the entity given.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the form is for.
   * @param string $icon
   *   What the form state answers for the `neo_icon` value.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  private function formState(EntityInterface $entity, string $icon = ''): FormStateInterface {
    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($entity);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);
    $formState->method('getValue')->willReturn($icon);
    return $formState;
  }

  /**
   * The lookup prefix an icon element was built with.
   *
   * @param \Drupal\neo_icon\IconElement $element
   *   The element.
   *
   * @return array
   *   The prefix, which is where each façade's entity or entity type ends up.
   */
  private function prefixOf(IconElement $element): array {
    return (array) (new \ReflectionProperty(IconElement::class, 'prefix'))->getValue($element);
  }

  /**
   * The package root.
   *
   * @return string
   *   The absolute path.
   */
  private function modulePath(): string {
    return dirname(__DIR__, 3);
  }

}
