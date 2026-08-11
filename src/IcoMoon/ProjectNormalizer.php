<?php

namespace Drupal\neo_icon\IcoMoon;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Normalizes a new-style IcoMoon package into the layout neo_icon expects.
 *
 * IcoMoon's newer app exports a package that shares nothing structurally with
 * the classic one:
 *
 * @code
 * <project>.icomoon.json      {palettes, formats, glyphs}
 * font/style.css              @font-face only, no per-icon rules
 * font/fonts/Untitled.{otf,ttf,woff,woff2}
 * font/demo.html
 * svg/<name>.svg              one file per glyph
 * symbol-defs/symbol-defs.svg symbol ids are raw names
 * symbol-defs/demo.html
 * @endcode
 *
 * Rather than teach every consumer about a second shape, this class rewrites
 * the extracted directory in place so it matches the classic on-disk contract:
 * a selection.json, a style.css, fonts/<icon id>.<ext> and, when the package
 * ships one, a symbol-defs.svg at the root. The synthesized selection.json is
 * written already carrying the entity's own name and prefix, so the caller can
 * skip the string-rewriting the classic path performs.
 */
final class ProjectNormalizer {

  /**
   * Font extensions worth keeping, in @font-face src order.
   *
   * IcoMoon also ships an .otf, but nothing references it and it costs a
   * megabyte, so it is dropped.
   */
  private const FONT_EXTENSIONS = [
    'woff2' => 'woff2',
    'woff' => 'woff',
    'ttf' => 'truetype',
  ];

  /**
   * Glyphs skipped for want of a name or code point.
   *
   * @var string[]
   */
  private array $skipped = [];

  /**
   * Constructs a normalizer.
   *
   * @param string $path
   *   The directory the archive was extracted into.
   * @param string $iconId
   *   The library's icon id, i.e. "icon-<entity id>".
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   */
  public function __construct(
    private readonly string $path,
    private readonly string $iconId,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Locates the project file of a new-style package.
   *
   * @param string $path
   *   The directory the archive was extracted into.
   *
   * @return string|null
   *   The project file path, or NULL when this is not a new-style package.
   */
  public static function detect($path) {
    $realpath = \Drupal::service('file_system')->realpath($path);
    if (!$realpath) {
      return NULL;
    }
    foreach (glob($realpath . '/*.icomoon.json') ?: [] as $candidate) {
      return $path . '/' . basename($candidate);
    }
    return NULL;
  }

  /**
   * Rewrites the extracted package into the classic layout.
   *
   * @param string $project_file
   *   The project file located by ::detect().
   *
   * @return string
   *   The library type, either 'font' or 'image'.
   *
   * @throws \Exception
   *   When the package cannot be turned into a usable library.
   */
  public function normalize($project_file) {
    $glyphs = $this->readGlyphs($project_file);

    $formats = $this->relocate();
    $this->prune($project_file);

    if ($formats['font']) {
      $type = 'font';
    }
    elseif ($formats['sprite']) {
      $type = 'image';
    }
    else {
      throw new \Exception(sprintf('The IcoMoon package contains neither a font nor an SVG sprite. Re-export it from IcoMoon with the "Font" or "Symbol Defs" format enabled (loose SVG files alone cannot be used).'));
    }

    if ($formats['sprite']) {
      $this->prefixSpriteIds();
    }
    // A stylesheet is written either way: it is what makes
    // neo_icon_library_info_build() register a Drupal library for this set, and
    // an image library needs that library to pull in the SVG use polyfill.
    if ($type === 'font') {
      $this->writeFontStylesheet($glyphs, $formats['fonts']);
    }
    else {
      $this->writeSpriteStylesheet();
    }
    $this->writeSelection($glyphs);

    return $type;
  }

  /**
   * Glyph names that were skipped during parsing.
   *
   * @return string[]
   *   A list of human readable reasons, one per skipped glyph.
   */
  public function getSkipped() {
    return $this->skipped;
  }

  /**
   * Reads the name to code point map out of the project file.
   *
   * The decoded project is large — roughly 64MB of PHP arrays for a 4,800
   * glyph set — and only two scalars per glyph are of any use, so the decoded
   * structure is released as soon as it has been walked.
   *
   * Glyphs are returned as a list rather than a name keyed map because plenty
   * of icons are named "0", "1", "100" and so on, and PHP would silently turn
   * those names into integer keys.
   *
   * @param string $project_file
   *   The project file path.
   *
   * @return array[]
   *   A list of ['name' => string, 'code' => int], in project order.
   *
   * @throws \Exception
   *   When the project file cannot be read or contains no usable glyphs.
   */
  private function readGlyphs($project_file) {
    $data = file_get_contents($project_file);
    if ($data === FALSE) {
      throw new \Exception(sprintf('Cannot read the IcoMoon project file %s.', basename($project_file)));
    }
    $project = Json::decode($data);
    unset($data);
    if (!is_array($project) || !isset($project['glyphs']) || !is_array($project['glyphs'])) {
      throw new \Exception(sprintf('%s is not a valid IcoMoon project file: no "glyphs" were found.', basename($project_file)));
    }

    $glyphs = [];
    $seen = [];
    foreach ($project['glyphs'] as $delta => $glyph) {
      $name = $glyph['extras']['name'] ?? NULL;
      $code = $glyph['extras']['codePoint'] ?? NULL;
      if (!is_string($name) || $name === '' || !is_int($code)) {
        $this->skipped[] = sprintf('glyph %d (%s)', $delta, is_string($name) && $name !== '' ? $name : 'unnamed');
        continue;
      }
      if (isset($seen[$name])) {
        $this->skipped[] = sprintf('glyph %d (%s, duplicate name)', $delta, $name);
        continue;
      }
      $seen[$name] = TRUE;
      $glyphs[] = ['name' => $name, 'code' => $code];
    }
    unset($project, $seen);

    if (!$glyphs) {
      throw new \Exception(sprintf('%s contains no usable glyphs.', basename($project_file)));
    }
    return $glyphs;
  }

  /**
   * Moves the files the module needs up to the library root.
   *
   * @return array
   *   An array with a 'font' bool, a 'sprite' bool and a 'fonts' list of the
   *   font extensions that survived, in @font-face src order.
   */
  private function relocate() {
    $fonts = [];
    foreach (array_keys(self::FONT_EXTENSIONS) as $extension) {
      $source = $this->findFont($extension);
      if (!$source) {
        continue;
      }
      $destination = $this->path . '/fonts/' . $this->iconId . '.' . $extension;
      if ($source !== $destination) {
        $directory = $this->path . '/fonts';
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $this->fileSystem->move($source, $destination, FileExists::Replace);
      }
      $fonts[] = $extension;
    }

    $sprite = FALSE;
    $source = $this->path . '/symbol-defs/symbol-defs.svg';
    if (file_exists($source)) {
      $this->fileSystem->move($source, $this->path . '/symbol-defs.svg', FileExists::Replace);
    }
    if (file_exists($this->path . '/symbol-defs.svg')) {
      $sprite = TRUE;
    }

    return [
      'font' => (bool) $fonts,
      'fonts' => $fonts,
      'sprite' => $sprite,
    ];
  }

  /**
   * Finds the font file of a given extension.
   *
   * The project file carries no font name — IcoMoon calls an unnamed project
   * "Untitled" — so the extension is the only thing to match on.
   *
   * @param string $extension
   *   The font extension, without a dot.
   *
   * @return string|null
   *   The font path, or NULL when the package has no font of that type.
   */
  private function findFont($extension) {
    foreach (['/font/fonts', '/fonts'] as $directory) {
      $realpath = $this->fileSystem->realpath($this->path . $directory);
      if (!$realpath) {
        continue;
      }
      $matches = glob($realpath . '/*.' . $extension);
      if ($matches) {
        return $this->path . $directory . '/' . basename(reset($matches));
      }
    }
    return NULL;
  }

  /**
   * Deletes everything the module has no use for.
   *
   * @param string $project_file
   *   The project file path.
   */
  private function prune($project_file) {
    foreach (['/font', '/symbol-defs', '/svg'] as $directory) {
      $this->fileSystem->deleteRecursive($this->path . $directory);
    }
    if (file_exists($project_file)) {
      $this->fileSystem->delete($project_file);
    }
  }

  /**
   * Namespaces the sprite's symbol ids with the library's icon id.
   *
   * Classic IcoMoon sprites carried the project's own prefix on every symbol
   * id; the new export uses bare glyph names, which would collide the moment a
   * second sprite library is installed. Icon::getChildren() looks the symbol up
   * by "<prefix><name>", so the prefix has to be baked in here.
   */
  private function prefixSpriteIds() {
    $file_path = $this->path . '/symbol-defs.svg';
    $contents = file_get_contents($file_path);
    if ($contents === FALSE) {
      return;
    }
    $contents = preg_replace(
      '/(<symbol\s[^>]*\bid=")/',
      '${1}' . $this->iconId . '-',
      $contents
    );
    file_put_contents($file_path, $contents);
  }

  /**
   * Writes a stylesheet in the shape the classic path would have produced.
   *
   * The new package's own style.css only declares the @font-face and a generic
   * ".icon" rule, so it is regenerated rather than rewritten. The per-icon
   * rules matter: NeoBuildEventSubscriber only emits Tailwind icon utilities
   * for global libraries, so a non-global library has nothing else to set the
   * glyph's content from.
   *
   * @param array[] $glyphs
   *   A list of ['name' => string, 'code' => int].
   * @param string[] $fonts
   *   The font extensions that survived, in @font-face src order.
   */
  private function writeFontStylesheet(array $glyphs, array $fonts) {
    $version = $this->fontVersion($fonts);

    $sources = [];
    foreach ($fonts as $extension) {
      $sources[] = sprintf(
        "url('fonts/%s.%s?v=%s') format('%s')",
        $this->iconId,
        $extension,
        $version,
        self::FONT_EXTENSIONS[$extension]
      );
    }

    $css = "@font-face {\n";
    $css .= "  font-family: '" . $this->iconId . "';\n";
    $css .= "  src:  " . implode(",\n    ", $sources) . ";\n";
    $css .= "  font-weight: normal;\n";
    $css .= "  font-style: normal;\n";
    $css .= "  font-display: block;\n";
    $css .= "}\n\n";
    $css .= '[class^="' . $this->iconId . '-"], [class*=" ' . $this->iconId . '-"] {' . "\n";
    $css .= "  /* use !important to prevent issues with browser extensions that change fonts */\n";
    $css .= "  font-family: '" . $this->iconId . "' !important;\n";
    $css .= "  speak: never;\n";
    $css .= "  font-style: normal;\n";
    $css .= "  font-weight: normal;\n";
    $css .= "  font-variant: normal;\n";
    $css .= "  text-transform: none;\n";
    $css .= "  line-height: 1;\n\n";
    $css .= "  /* Better Font Rendering =========== */\n";
    $css .= "  -webkit-font-smoothing: antialiased;\n";
    $css .= "  -moz-osx-font-smoothing: grayscale;\n";
    $css .= "}\n\n";
    foreach ($glyphs as $glyph) {
      $css .= '.' . $this->iconId . '-' . $glyph['name'] . ":before {\n";
      $css .= '  content: "\\' . dechex($glyph['code']) . "\";\n";
      $css .= "}\n";
    }

    $this->fileSystem->saveData($css, $this->path . '/style.css', FileExists::Replace);
  }

  /**
   * Writes the stylesheet for a sprite backed library.
   *
   * The newer package has no equivalent of the stylesheet IcoMoon shipped
   * alongside a classic symbol-defs.svg, so the sizing rules are reproduced
   * here. Its existence also matters structurally: a library with no
   * stylesheet is never registered with Drupal, and without that registration
   * the SVG use polyfill would never be attached.
   */
  private function writeSpriteStylesheet() {
    $css = '[class^="' . $this->iconId . '-"], [class*=" ' . $this->iconId . '-"] {' . "\n";
    $css .= "  display: inline-block;\n";
    $css .= "  width: 1em;\n";
    $css .= "  height: 1em;\n";
    $css .= "  stroke-width: 0;\n";
    $css .= "  stroke: currentColor;\n";
    $css .= "  fill: currentColor;\n";
    $css .= "}\n";

    $this->fileSystem->saveData($css, $this->path . '/style.css', FileExists::Replace);
  }

  /**
   * Builds a cache busting token from the font files themselves.
   *
   * @param string[] $fonts
   *   The font extensions that survived.
   *
   * @return string
   *   A version token.
   */
  private function fontVersion(array $fonts) {
    foreach ($fonts as $extension) {
      $file_path = $this->path . '/fonts/' . $this->iconId . '.' . $extension;
      $hash = @md5_file($file_path);
      if ($hash) {
        return $hash;
      }
    }
    return md5($this->iconId);
  }

  /**
   * Writes a selection.json equivalent to what the classic path would leave.
   *
   * It is written already normalized — the name and prefix are the entity's,
   * not IcoMoon's — so the caller does not run the classic rewriting pass over
   * it. Path data is omitted because nothing in the module reads it.
   *
   * Both prefixes are written because getInfoPrefix() picks between them by
   * library type, and the type is decided by what the package happened to ship.
   *
   * @param array[] $glyphs
   *   A list of ['name' => string, 'code' => int].
   */
  private function writeSelection(array $glyphs) {
    $icons = [];
    foreach ($glyphs as $glyph) {
      $icons[] = [
        'icon' => [
          'tags' => [$glyph['name']],
        ],
        'properties' => [
          'name' => $glyph['name'],
          'code' => $glyph['code'],
          'codes' => [],
        ],
      ];
    }

    $prefix = $this->iconId . '-';
    $selection = [
      'IcoMoonType' => 'selection',
      'height' => 1024,
      'metadata' => [
        'name' => $this->iconId,
      ],
      'preferences' => [
        'fontPref' => [
          'prefix' => $prefix,
          'metadata' => [
            'fontFamily' => $this->iconId,
          ],
        ],
        'imagePref' => [
          'prefix' => $prefix,
        ],
      ],
      'icons' => $icons,
    ];

    $this->fileSystem->saveData(Json::encode($selection), $this->path . '/selection.json', FileExists::Replace);
  }

}
