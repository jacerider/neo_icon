<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\Hook\NeoIconHooks;
use Drupal\neo_icon\IconLibraryInterface;
use Drupal\neo_icon\IconLibraryStorageInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * What the three behavioural hooks decide, driven without a bootstrap.
 *
 * All three ask the icon library storage for libraries and turn them into
 * Drupal library entries or attachments, and between them they carry four
 * decisions a site can see: which icon libraries get a Drupal library at all,
 * which of those pull the svg helper in with them, which are attached to every
 * page, and which extension the same set is handed to a second time.
 *
 * None of that needs a site. The class takes the entity type manager as its one
 * constructor argument, so a stubbed storage answering with stub libraries
 * drives every branch — which is why this file exists beside
 * `Drupal\Tests\neo_icon\Kernel\LibraryHooksTest` rather than inside it. That
 * one spends a bootstrap on the single question a bootstrap can answer: whether
 * the hook system found the methods.
 */
#[Group('neo_icon')]
final class LibraryHookDecisionsTest extends UnitTestCase {

  /**
   * One Drupal library is built per icon library that has a stylesheet.
   *
   * Acceptance criterion: *it registers one Drupal library per icon library
   * that has a stylesheet, and none for one that has not.*
   *
   * The stylesheet is both the condition and the content, so the entry is
   * asserted whole: an icon library contributes its stylesheet under the theme
   * group with no options beside it, keyed by the library name the entity
   * answers with rather than by its entity id.
   */
  public function testRegistersOneDrupalLibraryPerIconLibraryWithStylesheet(): void {
    $hooks = $this->hooks([
      $this->library('material', 'public://neo-icon/material.css'),
      $this->library('brands', 'public://neo-icon/brands.css'),
      $this->library('unbuilt', ''),
    ]);

    $libraries = $hooks->libraryInfoBuild();

    $this->assertSame(['material', 'brands'], array_keys($libraries));
    $this->assertSame(
      ['css' => ['theme' => ['public://neo-icon/material.css' => []]]],
      $libraries['material']
    );
    $this->assertArrayNotHasKey('unbuilt', $libraries);
  }

  /**
   * Only an svg icon library drags the svg helper library along with it.
   *
   * Acceptance criterion: *it adds the svg helper library as a dependency only
   * for an svg icon library.*
   *
   * Both answers are asserted from one build, because a dependency added to
   * every library and a dependency added to none are both wrong in the same
   * direction and either would pass an assertion that only looked at one.
   */
  public function testAddsSvgHelperDependencyOnlyForSvgIconLibrary(): void {
    $hooks = $this->hooks([
      $this->library('sprites', 'public://neo-icon/sprites.css', TRUE),
      $this->library('material', 'public://neo-icon/material.css'),
    ]);

    $libraries = $hooks->libraryInfoBuild();

    $this->assertSame(['neo_icon/icon-svg'], $libraries['sprites']['dependencies']);
    $this->assertArrayNotHasKey('dependencies', $libraries['material']);
  }

  /**
   * Every global icon library is attached to the page.
   *
   * Acceptance criterion: *it attaches every global icon library to a page's
   * attachments.*
   *
   * The storage decides which libraries are global; the hook attaches what it
   * is handed and namespaces each one under this module. A library with no
   * stylesheet is in the fixture on purpose — the build hook skips it, this one
   * does not ask, and that difference between the two is behaviour rather than
   * an oversight.
   */
  public function testAttachesEveryGlobalIconLibraryToPageAttachments(): void {
    $hooks = $this->hooks([], [
      $this->library('material', 'public://neo-icon/material.css'),
      $this->library('unbuilt', ''),
    ]);
    $attachments = ['#attached' => ['library' => ['neo/neo']]];

    $hooks->pageAttachments($attachments);

    $this->assertSame(
      ['neo/neo', 'neo_icon/material', 'neo_icon/unbuilt'],
      $attachments['#attached']['library']
    );
  }

  /**
   * The same global set is added to canvas-ui, and to nothing else.
   *
   * Acceptance criterion: *it adds the global icon libraries to `canvas-ui` and
   * leaves every other extension untouched.*
   *
   * Three ways of not being the one case, all asserted: another extension
   * entirely, the canvas extension without that library defined, and the
   * neighbouring libraries inside the extension it does alter.
   */
  public function testAddsGlobalIconLibrariesToCanvasUiAndLeavesOtherExtensionsUntouched(): void {
    $hooks = $this->hooks([], [$this->library('material', 'public://neo-icon/material.css')]);

    $canvas = ['canvas-ui' => ['dependencies' => ['core/drupal']], 'canvas-other' => []];
    $hooks->libraryInfoAlter($canvas, 'canvas');
    $this->assertSame(
      ['core/drupal', 'neo_icon/material'],
      $canvas['canvas-ui']['dependencies']
    );
    $this->assertSame([], $canvas['canvas-other']);

    $elsewhere = ['canvas-ui' => ['dependencies' => ['core/drupal']]];
    $hooks->libraryInfoAlter($elsewhere, 'neo');
    $this->assertSame(['core/drupal'], $elsewhere['canvas-ui']['dependencies']);

    $absent = ['canvas-editor' => ['dependencies' => []]];
    $hooks->libraryInfoAlter($absent, 'canvas');
    $this->assertSame(['canvas-editor' => ['dependencies' => []]], $absent);
  }

  /**
   * The collaborator arrives through the constructor, never through a static.
   *
   * Acceptance criterion: *it takes the entity type manager as a constructor
   * argument and makes no static container call.*
   *
   * The whole gain of the move is here, and it is why the four criteria above
   * are assertable at all: as three functions this was three
   * `\Drupal::service('entity_type.manager')` calls and every branch needed a
   * booted site. Absence is asserted two ways, because neither alone is enough
   * — the source says no static is written, and driving all three methods with
   * no container present says none is reached by another route.
   */
  public function testTakesEntityTypeManagerAsConstructorArgumentAndMakesNoStaticContainerCall(): void {
    $constructor = (new \ReflectionClass(NeoIconHooks::class))->getConstructor();
    $this->assertNotNull($constructor, 'The hook class has a constructor.');
    $parameters = $constructor->getParameters();
    $this->assertCount(1, $parameters, 'It takes one collaborator and no more.');
    $this->assertSame(
      EntityTypeManagerInterface::class,
      (string) $parameters[0]->getType(),
      'And that collaborator is the entity type manager.'
    );
    $this->assertTrue($parameters[0]->isPromoted(), 'Promoted, as the package writes them.');
    $this->assertTrue(
      (new \ReflectionProperty(NeoIconHooks::class, $parameters[0]->getName()))->isReadOnly(),
      'And readonly, so nothing reassigns it.'
    );

    $this->assertStringNotContainsString(
      '\Drupal::',
      $this->executableSourceOfTheHookClass(),
      'No static reaches the container.'
    );

    $this->assertFalse(\Drupal::hasContainer(), 'The methods run with no container.');
    $hooks = $this->hooks(
      [$this->library('material', 'public://neo-icon/material.css')],
      [$this->library('material', 'public://neo-icon/material.css')]
    );
    $attachments = [];
    $libraries = ['canvas-ui' => ['dependencies' => []]];
    $hooks->libraryInfoBuild();
    $hooks->pageAttachments($attachments);
    $hooks->libraryInfoAlter($libraries, 'canvas');
    $this->assertFalse(\Drupal::hasContainer(), 'And leave none behind.');
  }

  /**
   * The hook class's source with every comment and docblock taken out.
   *
   * @return string
   *   What the file actually executes. The comments have to go before anything
   *   can be said about `\Drupal::` not appearing, because the class docblock
   *   names the three static calls this move replaced — a sentence about the
   *   past that a plain search of the file cannot tell from a live call.
   */
  private function executableSourceOfTheHookClass(): string {
    $file = (string) (new \ReflectionClass(NeoIconHooks::class))->getFileName();
    $executable = '';
    foreach (token_get_all((string) file_get_contents($file)) as $token) {
      if (is_array($token)) {
        if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
          continue;
        }
        $executable .= $token[1];
        continue;
      }
      $executable .= $token;
    }
    return $executable;
  }

  /**
   * Builds the hook class over a stubbed icon library storage.
   *
   * @param \Drupal\neo_icon\IconLibraryInterface[] $all
   *   What the storage answers `loadMultiple()` with.
   * @param \Drupal\neo_icon\IconLibraryInterface[] $globals
   *   What the storage answers `loadGlobals()` with.
   *
   * @return \Drupal\neo_icon\Hook\NeoIconHooks
   *   The hook class under test, constructed rather than fetched.
   */
  private function hooks(array $all = [], array $globals = []): NeoIconHooks {
    $storage = $this->createMock(IconLibraryStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($all);
    $storage->method('loadGlobals')->willReturn($globals);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('neo_icon_library')->willReturn($storage);

    return new NeoIconHooks($entityTypeManager);
  }

  /**
   * Builds an icon library double.
   *
   * @param string $name
   *   The library name the entity answers with, which is what a Drupal library
   *   ends up keyed by and what an attachment is namespaced under.
   * @param string $stylesheet
   *   The stylesheet the library contributes, or an empty string where it has
   *   none built yet.
   * @param bool $svg
   *   Whether this is an svg library.
   *
   * @return \Drupal\neo_icon\IconLibraryInterface
   *   The icon library.
   */
  private function library(string $name, string $stylesheet, bool $svg = FALSE): IconLibraryInterface {
    $library = $this->createMock(IconLibraryInterface::class);
    $library->method('getLibraryName')->willReturn($name);
    $library->method('getStylesheet')->willReturn($stylesheet);
    $library->method('isSvg')->willReturn($svg);
    return $library;
  }

}
