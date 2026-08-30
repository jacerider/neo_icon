<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\IcoMoon\ProjectNormalizer;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies which library a package becomes and what is left of it after.
 *
 * Layout normalization decides two things by looking at what the package
 * happened to ship: which library type it resolves to, and which files survive
 * into the library directory. The type picks the stylesheet that is written
 * and, downstream, whether icons render from a font face or from a sprite
 * symbol. The survivors are what ships: a library directory still holding the
 * font/ directory, the loose SVGs and the project file is megabytes of dead
 * weight on every site that installs it.
 *
 * Two of the module's README rules are pinned here. A package carrying both a
 * font and an SVG sprite resolves to the font — only the newer layout can ship
 * both, and the tie is broken in one direction on purpose. A package carrying
 * neither is refused, with the message that says which format to re-export
 * with, because loose SVG files alone cannot be turned into a library.
 *
 * The file system double really moves and deletes, so the directory the pass
 * leaves behind is the record every assertion here reads.
 */
#[Group('neo_icon')]
final class TypeResolutionTest extends UnitTestCase {

  use FileSystemMockTrait;
  use IcoMoonPackageBuilderTrait;

  /**
   * The icon id every package in this class is normalized under.
   */
  private const ICON_ID = 'icon-example';

  /**
   * It resolves a package carrying a font to the font type.
   *
   * Acceptance criterion: *it resolves a package carrying a font to the font
   * type.*
   *
   * The type is not a label the caller can ignore: it is what the icon library
   * entity stores, and it decides which of the two stylesheets is written. So
   * the returned type is asserted together with the stylesheet that proves the
   * font branch is the one that ran — a font library with no @font-face rule
   * renders every glyph as an empty box.
   */
  public function testItResolvesPackagesCarryingFontsToTheFontType(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf']);

    $type = $this->normalizePackage($package);

    $this->assertSame('font', $type);
    $this->assertStringContainsString('@font-face', $this->stylesheet($package), 'A font library is written the font stylesheet.');
  }

  /**
   * It resolves a package carrying only a sprite to the image type.
   *
   * Acceptance criterion: *it resolves a package carrying only a sprite to the
   * image type.*
   *
   * A sprite package has no font to declare, and the stylesheet it is written
   * carries sizing rules instead of a face. It is written all the same, because
   * a library with no stylesheet is never registered with Drupal and the SVG
   * use polyfill would then never be attached — which is why what is asserted
   * is the absence of an @font-face rather than the absence of a stylesheet.
   */
  public function testItResolvesSpriteOnlyPackagesToTheImageType(): void {
    $package = $this->buildIcoMoonPackage(['sprite']);

    $type = $this->normalizePackage($package);

    $this->assertSame('image', $type);
    $this->assertFileExists($package . '/symbol-defs.svg', 'The sprite is moved up to the library root.');
    $this->assertStringNotContainsString('@font-face', $this->stylesheet($package), 'An image library declares no font face.');
    $this->assertDirectoryDoesNotExist($package . '/fonts', 'A package with no font leaves no fonts directory.');
  }

  /**
   * It prefers the font when a package carries both a font and a sprite.
   *
   * Acceptance criterion: *it prefers the font when a package carries both a
   * font and a sprite.*
   *
   * This is a README rule, and it only became expressible with the newer
   * layout: the classic export never shipped both at once, so the tie is a
   * decision somebody made rather than a case that cannot arise. The sprite is
   * not thrown away by the tie — it is still relocated to the library root —
   * so the assertion is about which artefact the library is built from, not
   * about which one survived.
   */
  public function testItPrefersTheFontWhenPackagesCarryBoth(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf', 'sprite']);

    $type = $this->normalizePackage($package);

    $this->assertSame('font', $type, 'The font wins the tie against a sprite.');
    $this->assertStringContainsString('@font-face', $this->stylesheet($package), 'The library is built from the font, not the sprite.');
    $this->assertFileExists($package . '/symbol-defs.svg', 'The sprite is kept, it just does not win.');
  }

  /**
   * It moves every surviving font file to fonts/ renamed to the icon id.
   *
   * Acceptance criterion: *it moves every surviving font file to fonts/
   * renamed to the icon id.*
   *
   * The stylesheet points at `fonts/<icon id>.<ext>` for each of woff2, woff
   * and ttf, so a file left under font/fonts, or kept under the name IcoMoon
   * gave an unnamed project, is a 404 on every page the icons appear on. The
   * placeholders are read back rather than merely counted, because a rename is
   * the only thing that carries the bytes across — a file the pass created
   * would be at the right path with the wrong content. The .otf IcoMoon ships
   * beside them is deliberately not in the set, and goes with font/.
   */
  public function testItMovesSurvivingFontFilesToFontsRenamedToTheIconId(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf']);

    $this->normalizePackage($package);

    foreach (['woff2', 'woff', 'ttf'] as $extension) {
      $font = $package . '/fonts/' . self::ICON_ID . '.' . $extension;
      $this->assertFileExists($font, $extension . ' is moved to fonts/ under the icon id.');
      $this->assertSame('placeholder-' . $extension, file_get_contents($font), 'The file was moved rather than regenerated.');
    }
    $this->assertSame(
      [self::ICON_ID . '.ttf', self::ICON_ID . '.woff', self::ICON_ID . '.woff2'],
      $this->entries($package . '/fonts'),
      'Only the three font files the class keeps are left, the .otf among the discarded.'
    );

    // A package shipping a subset keeps that subset, under the same names.
    $ttfOnly = $this->buildIcoMoonPackage(['ttf']);

    $this->normalizePackage($ttfOnly);

    $this->assertSame([self::ICON_ID . '.ttf'], $this->entries($ttfOnly . '/fonts'));
  }

  /**
   * It deletes everything the module has no use for.
   *
   * Acceptance criterion: *it deletes the font, symbol-defs and svg
   * directories, the demo pages inside them, and the project file.*
   *
   * A library directory is committed configuration on every site that installs
   * the set, so what the pass leaves is what ships. The two demo pages sit
   * inside font/ and symbol-defs/, where IcoMoon puts them, so removing those
   * directories whole is what makes their absence assertable — a package that
   * parked a demo page at the root would go unpruned. The whole root is
   * asserted rather than each casualty in turn, because that is the only form
   * of the assertion a newly ignored file cannot slip past.
   */
  public function testItDeletesEverythingTheModuleHasNoUseFor(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf', 'sprite']);

    $this->normalizePackage($package);

    $this->assertDirectoryDoesNotExist($package . '/font', 'The font directory goes, demo page, .otf and all.');
    $this->assertDirectoryDoesNotExist($package . '/symbol-defs', 'The symbol-defs directory goes with its own demo page.');
    $this->assertDirectoryDoesNotExist($package . '/svg', 'The loose per-glyph SVGs go.');
    $this->assertSame([], glob($package . '/*.icomoon.json') ?: [], 'The project file goes once it has been read.');
    $this->assertSame([], $this->findFiles($package, 'html'), 'No demo page survives anywhere in the library.');
    $this->assertSame(
      ['fonts', 'selection.json', 'style.css', 'symbol-defs.svg'],
      $this->entries($package),
      'Only what the module reads is left in the library directory.'
    );
  }

  /**
   * It refuses a package carrying neither a font nor a sprite.
   *
   * Acceptance criterion: *it refuses a package carrying neither a font nor a
   * sprite.*
   *
   * IcoMoon exports the loose per-glyph SVGs whatever else was ticked, so a
   * site builder who left both format boxes unticked uploads a package that
   * looks full and cannot become a library: there is nothing to set a glyph's
   * content from and no symbol to reference. The refusal is worth more than the
   * failure, so it names the two formats to re-export with rather than merely
   * reporting that the package is unusable, and nothing half-built is left
   * behind to be registered.
   */
  public function testItRefusesPackagesCarryingNeitherFontNorSprite(): void {
    $package = $this->buildIcoMoonPackage([]);

    $refusal = $this->refuse($package);

    $this->assertStringContainsString('Font', $refusal, 'The refusal names the Font format to re-export with.');
    $this->assertStringContainsString('Symbol Defs', $refusal, 'The refusal names the Symbol Defs format to re-export with.');
    $this->assertFileDoesNotExist($package . '/selection.json', 'A refused package leaves no library behind.');
    $this->assertFileDoesNotExist($package . '/style.css', 'A refused package is never registered with Drupal.');
  }

  /**
   * Runs layout normalization over a package the builder wrote.
   *
   * @param string $package
   *   The package directory.
   *
   * @return string
   *   The library type the package resolved to.
   */
  private function normalizePackage(string $package): string {
    $normalizer = new ProjectNormalizer($package, self::ICON_ID, $this->actingFileSystem());
    return $normalizer->normalize((string) $normalizer->detect());
  }

  /**
   * Runs layout normalization over a package that cannot become a library.
   *
   * @param string $package
   *   The package directory.
   *
   * @return string
   *   The message the package was refused with.
   */
  private function refuse(string $package): string {
    $normalizer = new ProjectNormalizer($package, self::ICON_ID, $this->actingFileSystem());
    try {
      $normalizer->normalize((string) $normalizer->detect());
    }
    catch (\Exception $e) {
      return $e->getMessage();
    }
    $this->fail('The package was not refused at all.');
  }

  /**
   * The stylesheet layout normalization wrote for a package.
   *
   * @param string $package
   *   The package directory.
   *
   * @return string
   *   The stylesheet contents.
   */
  private function stylesheet(string $package): string {
    $this->assertFileExists($package . '/style.css', 'Every library is written a stylesheet.');
    return (string) file_get_contents($package . '/style.css');
  }

  /**
   * The entries of a directory, sorted, without the dot entries.
   *
   * @param string $directory
   *   The directory to list.
   *
   * @return string[]
   *   The entry names.
   */
  private function entries(string $directory): array {
    $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    sort($entries);
    return $entries;
  }

  /**
   * Every file of a given extension anywhere under a directory.
   *
   * @param string $directory
   *   The directory to walk.
   * @param string $extension
   *   The extension to look for, without a dot.
   *
   * @return string[]
   *   The matching paths.
   */
  private function findFiles(string $directory, string $extension): array {
    $found = [];
    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
      if ($file->isFile() && $file->getExtension() === $extension) {
        $found[] = $file->getPathname();
      }
    }
    sort($found);
    return $found;
  }

}
