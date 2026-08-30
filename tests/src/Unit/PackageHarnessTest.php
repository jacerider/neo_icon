<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies the harness every later layout normalization test composes with.
 *
 * The package builder and the file system double are not scaffolding this
 * ticket happens to leave behind. Four later tickets build their fixtures
 * through them and assert against the directory they leave, so a builder that
 * quietly parked a demo page at the package root, or a double that recorded a
 * deletion without performing it, would fail those tickets against code that
 * is correct. What they can express and what they really do is pinned here,
 * once, before anything consumes them.
 */
#[Group('neo_icon')]
final class PackageHarnessTest extends UnitTestCase {

  use FileSystemMockTrait;
  use IcoMoonPackageBuilderTrait;

  /**
   * It builds any chosen combination of the three fonts and the sprite.
   *
   * Acceptance criterion: *it builds a package carrying any chosen combination
   * of the three font extensions and the sprite, or neither, laid out as the
   * newer layout.*
   *
   * One later ticket needs all three font files at once to pin the src order,
   * another a font and a sprite together for the font-wins tie-break, and
   * another neither of them for the refusal, so a singular "placeholder font
   * or sprite" cannot express the fixtures. The layout is asserted alongside
   * the format set because it is the half a later ticket's pruning assertions
   * rest on: the demo pages sit inside font/ and symbol-defs/, never at the
   * root, and the loose per-glyph SVGs under svg/.
   */
  public function testItBuildsAnyChosenCombinationOfFontsAndSprite(): void {
    $everything = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf', 'sprite']);

    $this->assertCount(1, glob($everything . '/*.icomoon.json') ?: []);
    foreach (['woff2', 'woff', 'ttf'] as $extension) {
      $this->assertCount(1, glob($everything . '/font/fonts/*.' . $extension) ?: [], $extension . ' is a placeholder under font/fonts.');
    }
    $this->assertFileExists($everything . '/symbol-defs/symbol-defs.svg');
    $this->assertStringContainsString('id="home"', (string) file_get_contents($everything . '/symbol-defs/symbol-defs.svg'));
    $this->assertFileExists($everything . '/font/demo.html');
    $this->assertFileExists($everything . '/symbol-defs/demo.html');
    $this->assertSame([], glob($everything . '/*.html') ?: [], 'No demo page sits at the package root.');
    $this->assertFileExists($everything . '/svg/home.svg');

    $ttfOnly = $this->buildIcoMoonPackage(['ttf']);

    $this->assertCount(1, glob($ttfOnly . '/font/fonts/*.ttf') ?: []);
    $this->assertSame([], glob($ttfOnly . '/font/fonts/*.woff2') ?: []);
    $this->assertSame([], glob($ttfOnly . '/font/fonts/*.woff') ?: []);
    $this->assertDirectoryDoesNotExist($ttfOnly . '/symbol-defs');

    $spriteOnly = $this->buildIcoMoonPackage(['sprite']);

    $this->assertDirectoryDoesNotExist($spriteOnly . '/font');
    $this->assertFileExists($spriteOnly . '/symbol-defs/symbol-defs.svg');

    $neither = $this->buildIcoMoonPackage([]);

    $this->assertDirectoryDoesNotExist($neither . '/font');
    $this->assertDirectoryDoesNotExist($neither . '/symbol-defs');
    $this->assertDirectoryExists($neither . '/svg');
  }

  /**
   * It writes the glyphs it is given, or omits the key altogether.
   *
   * Acceptance criterion: *it writes the project file from a caller-supplied
   * glyphs array, or with no glyphs key at all.*
   *
   * Glyph parsing reads exactly two scalars per glyph and drops the rest, so
   * the cases a later ticket needs are all malformations of those two: a glyph
   * with no name at all, a code point that arrived as a string, a name already
   * taken. The builder writes whatever it is handed rather than repairing it,
   * which is what makes those cases expressible. Omitting the key entirely is
   * the separate case: it is what keeps the "no glyphs were found" refusal
   * distinct from the "no usable glyphs" one.
   */
  public function testItWritesSuppliedGlyphsOrOmitsTheKeyAltogether(): void {
    $package = $this->buildIcoMoonPackage([], [
      ['name' => 'alpha', 'code' => 0xE900],
      ['code' => 0xE901],
      ['name' => 'gamma', 'code' => '0xE902'],
      ['name' => 'alpha', 'code' => 0xE903],
    ]);

    $project = Json::decode((string) file_get_contents($package . '/Untitled.icomoon.json'));
    $this->assertCount(4, $project['glyphs']);
    $this->assertSame(['name' => 'alpha', 'codePoint' => 0xE900], $project['glyphs'][0]['extras']);
    $this->assertArrayNotHasKey('name', $project['glyphs'][1]['extras'], 'A glyph may be written with no name at all.');
    $this->assertSame(0xE901, $project['glyphs'][1]['extras']['codePoint']);
    $this->assertSame('0xE902', $project['glyphs'][2]['extras']['codePoint'], 'A code point is written as it was handed over, string and all.');
    $this->assertSame('alpha', $project['glyphs'][3]['extras']['name'], 'A name may be repeated.');

    $keyless = $this->buildIcoMoonPackage([], NULL);

    $project = Json::decode((string) file_get_contents($keyless . '/Untitled.icomoon.json'));
    $this->assertIsArray($project);
    $this->assertArrayNotHasKey('glyphs', $project);
  }

  /**
   * It really performs every file operation it is asked to perform.
   *
   * Acceptance criterion: *it performs the delegated file work for real —
   * move, saveData, delete and deleteRecursive act on disk, prepareDirectory
   * creates its directory, an absent directory deletes as a no-op.*
   *
   * The shape is neo_image's style-flush double — a mock, callbacks, a real
   * temporary directory — and the behaviour deliberately is not: that double
   * records deletions without performing them, and a double that recorded here
   * would leave every later ticket's directory assertion staring at an
   * unchanged tree. prepareDirectory has to create the directory itself,
   * because its by-reference $directory parameter cannot be written back
   * through a callback. An absent tree deleting as a no-op is not politeness
   * either: a package built without a font still gets pruned, and the PHPUnit
   * configuration this runs under fails on warnings.
   */
  public function testItPerformsTheDelegatedFileWorkForReal(): void {
    $package = $this->buildIcoMoonPackage(['ttf']);
    $fileSystem = $this->actingFileSystem();

    $fonts = $package . '/fonts';
    $this->assertDirectoryDoesNotExist($fonts);
    $this->assertTrue($fileSystem->prepareDirectory($fonts, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS));
    $this->assertDirectoryExists($fonts);

    $moved = $fonts . '/icon-example.ttf';
    $this->assertSame($moved, $fileSystem->move($package . '/font/fonts/Untitled.ttf', $moved, FileExists::Replace));
    $this->assertFileExists($moved);
    $this->assertFileDoesNotExist($package . '/font/fonts/Untitled.ttf');

    $this->assertSame($package . '/style.css', $fileSystem->saveData("body {}\n", $package . '/style.css', FileExists::Replace));
    $this->assertStringEqualsFile($package . '/style.css', "body {}\n");

    $this->assertTrue($fileSystem->delete($package . '/style.css'));
    $this->assertFileDoesNotExist($package . '/style.css');

    $this->assertTrue($fileSystem->deleteRecursive($package . '/font'));
    $this->assertDirectoryDoesNotExist($package . '/font');

    $this->assertTrue($fileSystem->deleteRecursive($package . '/symbol-defs'), 'An absent directory deletes as a no-op.');
  }

}
