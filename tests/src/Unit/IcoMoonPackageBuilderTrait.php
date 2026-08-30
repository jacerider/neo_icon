<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Component\Serialization\Json;

/**
 * Builds newer layout IcoMoon packages for layout normalization to chew on.
 *
 * Nothing is committed as a fixture. The interesting cases are variations on
 * one project file — a glyph with no name, a code point that is a string, two
 * glyphs sharing a name, a package carrying a font and a sprite, a package
 * carrying neither — which a committed tree cannot express one per case.
 *
 * Every directory it creates is removed in tearDown(), so a test class that
 * declares a tearDown() of its own has to call this one through parent::.
 */
trait IcoMoonPackageBuilderTrait {

  /**
   * The glyphs written when the caller does not choose its own.
   */
  private const DEFAULT_GLYPHS = [
    ['name' => 'home', 'code' => 0xE900],
    ['name' => 'user', 'code' => 0xE901],
  ];

  /**
   * Every package directory built, so tearDown can take them away again.
   *
   * @var string[]
   */
  private array $builtPackages = [];

  /**
   * Builds a newer layout package in a fresh temporary directory.
   *
   * The layout is the fixture contract every later ticket asserts against: a
   * project file at the root whose base name is free, fonts as placeholders
   * under font/fonts named by extension alone, the sprite at
   * symbol-defs/symbol-defs.svg with bare glyph names for symbol ids, the demo
   * pages inside font/ and symbol-defs/ rather than at the root, and the loose
   * per-glyph SVGs under svg/. No real font binary is ever written.
   *
   * @param string[] $formats
   *   Any combination of 'woff2', 'woff', 'ttf' and 'sprite'. An empty array
   *   builds a package carrying neither a font nor a sprite — loose SVGs and a
   *   project file alone, which is the shape import refuses.
   * @param array[]|null $glyphs
   *   The glyphs to write, each an array carrying a 'name' and a 'code' — both
   *   optional, and both written through unrepaired, so that a glyph with no
   *   name, a code point that is a string and a repeated name are all
   *   expressible. NULL omits the "glyphs" key from the project file entirely.
   * @param string $projectName
   *   The project file's base name, without the ".icomoon.json" suffix.
   *
   * @return string
   *   The package directory.
   */
  private function buildIcoMoonPackage(array $formats = ['woff2', 'woff', 'ttf'], ?array $glyphs = self::DEFAULT_GLYPHS, string $projectName = 'Untitled'): string {
    $package = $this->temporaryDirectory();

    $project = ['palettes' => [], 'formats' => []];
    if ($glyphs !== NULL) {
      $project['glyphs'] = [];
      foreach ($glyphs as $glyph) {
        $extras = [];
        if (array_key_exists('name', $glyph)) {
          $extras['name'] = $glyph['name'];
        }
        if (array_key_exists('code', $glyph)) {
          $extras['codePoint'] = $glyph['code'];
        }
        $project['glyphs'][] = ['extras' => $extras];
      }
    }
    file_put_contents($package . '/' . $projectName . '.icomoon.json', Json::encode($project));

    // The names the loose SVGs and the sprite's symbol ids are written under.
    $names = [];
    foreach ($glyphs ?? [] as $glyph) {
      $name = $glyph['name'] ?? NULL;
      if (is_string($name) && $name !== '' && !in_array($name, $names, TRUE)) {
        $names[] = $name;
      }
    }

    // IcoMoon exports the loose per-glyph SVGs whatever else was ticked, and
    // they are the whole of a package carrying no font and no sprite.
    mkdir($package . '/svg', 0777, TRUE);
    foreach ($names as $name) {
      file_put_contents($package . '/svg/' . $name . '.svg', '<svg viewBox="0 0 1024 1024"><path d="M0 0h1024v1024H0z"/></svg>');
    }

    $extensions = array_values(array_intersect(['woff2', 'woff', 'ttf'], $formats));
    if ($extensions) {
      mkdir($package . '/font/fonts', 0777, TRUE);
      // The project file carries no font name, so the placeholders are named
      // as IcoMoon names an unnamed project and matched on extension alone.
      // The .otf rides along exactly as IcoMoon ships it, and is discarded
      // with the rest of font/.
      foreach ([...$extensions, 'otf'] as $extension) {
        file_put_contents($package . '/font/fonts/Untitled.' . $extension, 'placeholder-' . $extension);
      }
      file_put_contents($package . '/font/style.css', "@font-face {\n  font-family: 'Untitled';\n}\n");
      file_put_contents($package . '/font/demo.html', '<html><body>font demo</body></html>');
    }

    if (in_array('sprite', $formats, TRUE)) {
      mkdir($package . '/symbol-defs', 0777, TRUE);
      $symbols = '';
      foreach ($names as $name) {
        $symbols .= '<symbol id="' . $name . '" viewBox="0 0 1024 1024"><path d="M0 0h1024v1024H0z"/></symbol>';
      }
      file_put_contents($package . '/symbol-defs/symbol-defs.svg', '<svg aria-hidden="true" style="position: absolute; width: 0; height: 0; overflow: hidden;"><defs>' . $symbols . '</defs></svg>');
      file_put_contents($package . '/symbol-defs/demo.html', '<html><body>sprite demo</body></html>');
    }

    return $package;
  }

  /**
   * Creates a fresh empty directory under the system temp dir.
   *
   * @return string
   *   The directory, removed again when the test ends.
   */
  private function temporaryDirectory(): string {
    $directory = sys_get_temp_dir() . '/neo-icon-package-' . uniqid();
    mkdir($directory, 0777, TRUE);
    $this->builtPackages[] = $directory;
    return $directory;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->builtPackages as $package) {
      $this->removeTree($package);
    }
    $this->builtPackages = [];
    parent::tearDown();
  }

  /**
   * Removes a directory tree the builder created.
   *
   * @param string $path
   *   The path to remove.
   */
  private function removeTree(string $path): void {
    if (!file_exists($path)) {
      return;
    }
    if (!is_dir($path)) {
      unlink($path);
      return;
    }
    foreach (scandir($path) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      $this->removeTree($path . '/' . $entry);
    }
    rmdir($path);
  }

}
