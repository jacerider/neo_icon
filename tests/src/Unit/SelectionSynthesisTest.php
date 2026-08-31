<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\IcoMoon\ProjectNormalizer;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies the selection.json layout normalization synthesizes.
 *
 * The classic layout ships a selection.json and the newer layout does not, so
 * this file is the one that makes the two indistinguishable to everything
 * downstream: the icon library entity reads its name out of it, its class
 * prefix out of it, and one icon definition per entry out of it. A file that
 * is written wrong is not a crash — it is a library that registers cleanly and
 * renders empty boxes.
 *
 * Two of the module's README rules are pinned here. A package with no name of
 * its own takes the library's: the newer layout carries neither a font family
 * nor a class prefix — IcoMoon calls an unnamed project "Untitled" — so both
 * are written from the icon id. And multicolor glyphs are classic layout only:
 * the project file stores a single code point per glyph, so there is nothing
 * to fill the layered glyph array from and it is written empty.
 *
 * The file is read back and inspected for the decisions inside it, never
 * compared against a golden file.
 */
#[Group('neo_icon')]
final class SelectionSynthesisTest extends UnitTestCase {

  use FileSystemMockTrait;
  use IcoMoonPackageBuilderTrait;

  /**
   * The icon id every package in this class is normalized under.
   */
  private const ICON_ID = 'icon-example';

  /**
   * It names the package after the library's icon id.
   *
   * Acceptance criterion: *it names the package after the library's icon id.*
   *
   * The newer project file has no name to offer — IcoMoon calls an unnamed
   * project "Untitled", which is the name the builder writes the package under
   * — so the name is the library's own. Two places carry it and both matter:
   * getInfoName() reads the metadata name and shows it wherever the library is
   * listed, and the font family is what the stylesheet's font face declares and
   * every icon rule resolves against. Nothing IcoMoon called the project is
   * allowed to survive, which is why the whole file is checked for the
   * placeholder rather than only the two keys that should hold the icon id.
   */
  public function testItNamesThePackageAfterTheIconId(): void {
    $package = $this->buildIcoMoonPackage(
      ['woff2', 'woff', 'ttf'],
      [['name' => 'home', 'code' => 0xE900]],
      'Untitled'
    );

    $this->normalizePackage($package);

    $selection = $this->selection($package);

    $this->assertSame(self::ICON_ID, $selection['metadata']['name'] ?? NULL, 'The library is named after its own icon id.');
    $this->assertSame(self::ICON_ID, $selection['preferences']['fontPref']['metadata']['fontFamily'] ?? NULL, 'The font family is the icon id the stylesheet declares.');
    $this->assertStringNotContainsString('Untitled', $this->selectionContents($package), 'The name IcoMoon gave an unnamed project does not survive into the library.');
  }

  /**
   * It writes the icon id prefix into both the font and image preferences.
   *
   * Acceptance criterion: *it writes the icon id prefix into both the font and
   * the image preferences.*
   *
   * getInfoPrefix() picks between the two preference keys by library type, and
   * the type is decided by what the package happened to ship — so a package
   * that resolves to a font reads fontPref and one that resolves to an image
   * reads imagePref, from a file written before either was known. Both keys are
   * therefore asserted, and asserted over a package of each type: checking only
   * the key its own type reads would leave the other free to rot until the day
   * somebody uploads a sprite.
   */
  public function testItWritesTheIconIdPrefixIntoBothPreferences(): void {
    foreach ([['woff2', 'woff', 'ttf'], ['sprite']] as $formats) {
      $shipped = implode(' and ', $formats);
      $package = $this->buildIcoMoonPackage($formats);

      $this->normalizePackage($package);

      $preferences = $this->selection($package)['preferences'] ?? [];

      $this->assertSame(self::ICON_ID . '-', $preferences['fontPref']['prefix'] ?? NULL, 'A package shipping ' . $shipped . ' carries the icon id prefix in its font preferences.');
      $this->assertSame(self::ICON_ID . '-', $preferences['imagePref']['prefix'] ?? NULL, 'A package shipping ' . $shipped . ' carries the icon id prefix in its image preferences.');
    }
  }

  /**
   * It writes one icon entry per surviving glyph.
   *
   * Acceptance criterion: *it writes one icon entry per surviving glyph,
   * carrying that glyph's name and code point.*
   *
   * prepareDefinitions() builds exactly one icon definition per entry, reading
   * the name and the code point straight out of the entry's properties, so this
   * file is the whole of what a library can render. The two glyphs the parse
   * dropped bring no entry with them — an entry for a glyph with no code point
   * would be a definition with nothing to set its content from — and the
   * surviving three keep their own name and their own code point, in project
   * order. The pairs are asserted together rather than as two lists, because
   * two lists that are each right can still be paired wrongly.
   */
  public function testItWritesOneIconEntryPerSurvivingGlyph(): void {
    $package = $this->buildIcoMoonPackage(['woff2'], [
      ['name' => 'zebra', 'code' => 0xE900],
      ['code' => 0xE901],
      ['name' => 'alpha', 'code' => 0xE902],
      ['name' => 'zebra', 'code' => 0xE903],
      ['name' => 'mango', 'code' => 0xE904],
    ]);

    $this->normalizePackage($package);

    $icons = $this->icons($package);

    $this->assertCount(3, $icons, 'One entry per surviving glyph, and none for the two the parse dropped.');
    $this->assertSame(
      [['zebra', 0xE900], ['alpha', 0xE902], ['mango', 0xE904]],
      array_map(
        static fn (array $icon): array => [$icon['properties']['name'] ?? NULL, $icon['properties']['code'] ?? NULL],
        $icons
      ),
      'Every entry carries its own glyph name and its own code point.'
    );
  }

  /**
   * It leaves every icon's multicolor codes array empty.
   *
   * Acceptance criterion: *it leaves every icon's multicolor codes array
   * empty.*
   *
   * This is the README's third rule, and its emptiness is the only place the
   * rule is observable. IcoMoon layers several glyphs to draw a multicolor one
   * and lists the extra code points in the classic properties.codes array;
   * Icon::getChildren() renders one span per entry in it. The newer project
   * file stores a single code point per glyph, so there is nothing to fill the
   * array from and it is written empty — the key is written all the same, so
   * the entry has the shape the classic path produced. Emptiness is asserted
   * strictly, because a codes array holding one code point would render a
   * stray empty span over every icon on the site.
   */
  public function testItLeavesEveryIconsMulticolorCodesArrayEmpty(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf', 'sprite'], [
      ['name' => 'home', 'code' => 0xE900],
      ['name' => 'user', 'code' => 0xE901],
      ['name' => 'star', 'code' => 0xE902],
    ]);

    $this->normalizePackage($package);

    $icons = $this->icons($package);

    $this->assertCount(3, $icons, 'Every glyph of the set made it into the file.');
    foreach ($icons as $delta => $icon) {
      $this->assertArrayHasKey('codes', $icon['properties'], sprintf('Icon %d declares a codes array at all.', $delta));
      $this->assertSame([], $icon['properties']['codes'], sprintf('Icon %d carries no layered glyph code points.', $delta));
    }
  }

  /**
   * Runs layout normalization over a package the builder wrote.
   *
   * @param string $package
   *   The package directory.
   */
  private function normalizePackage(string $package): void {
    $normalizer = new ProjectNormalizer($package, self::ICON_ID, $this->actingFileSystem());
    $normalizer->normalize((string) $normalizer->detect());
  }

  /**
   * The synthesized selection.json, decoded.
   *
   * @param string $package
   *   The package directory.
   *
   * @return array<string, mixed>
   *   The decoded file.
   */
  private function selection(string $package): array {
    $decoded = Json::decode($this->selectionContents($package));
    $this->assertIsArray($decoded, 'The synthesized selection.json is readable JSON.');
    return $decoded;
  }

  /**
   * The synthesized selection.json, as it was written.
   *
   * @param string $package
   *   The package directory.
   *
   * @return string
   *   The file contents.
   */
  private function selectionContents(string $package): string {
    $file_path = $package . '/selection.json';
    $this->assertFileExists($file_path, 'Layout normalization writes a selection.json.');
    return (string) file_get_contents($file_path);
  }

  /**
   * The icon entries the synthesized selection.json carries, in file order.
   *
   * @param string $package
   *   The package directory.
   *
   * @return array<int, mixed>
   *   One entry per surviving glyph.
   */
  private function icons(string $package): array {
    $selection = $this->selection($package);
    $this->assertArrayHasKey('icons', $selection, 'The file carries an icons list.');
    $this->assertIsArray($selection['icons'], 'The icons list is a list.');
    return $selection['icons'];
  }

}
