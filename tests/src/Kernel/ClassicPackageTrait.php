<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\file\FileInterface;
use Drupal\neo_config_file\ConfigFileInterface;
use Drupal\neo_icon\Entity\IconLibrary;
use Drupal\neo_icon\IconLibraryInterface;

/**
 * Imports a small classic IcoMoon package into an icon library.
 *
 * The package goes through the same door a real upload does — the zip is
 * unpacked by the config file module's extractor and prepared by the library's
 * own import — so what a test asserts is what a site would get, not a set of
 * derived files written by hand. Only the config file entity and its file are
 * doubles: they exist to hand the library a zip, and nothing else about them is
 * read.
 *
 * Three glyphs cover the shapes a renderer has to get right: a narrow
 * single-path glyph, a two-path glyph whose first path is translucent, and a
 * glyph whose paths carry a fixed fill.
 */
trait ClassicPackageTrait {

  /**
   * {@inheritdoc}
   *
   * The inherited set-up maps the public stream onto a virtual filesystem, on
   * which `realpath()` and `glob()` answer nothing, so an unpacked package's
   * fonts are never found and the import refuses it. A real site directory is
   * what core's own file tests use for the same reason.
   */
  protected function setUpFilesystem(): void {
    $files = $this->siteDirectory . '/files';
    mkdir($files, 0775, TRUE);
    mkdir($this->siteDirectory . '/config/sync', 0775, TRUE);
    $this->setSetting('file_public_path', $files);
    $this->setSetting('config_sync_directory', $this->siteDirectory . '/config/sync');
  }

  /**
   * The glyphs every package is built from.
   *
   * @return array[]
   *   Each with a name, a code point, its paths, its per-path attrs and,
   *   optionally, a width.
   */
  protected function classicGlyphs(): array {
    return [
      [
        'name' => 'chevron',
        'code' => 0xE900,
        'width' => 640,
        'paths' => ['M0 0h640v1024h-640z'],
        'attrs' => [[]],
      ],
      [
        'name' => 'duo',
        'code' => 0xE901,
        'paths' => ['M0 0h512v512h-512z', 'M512 512h512v512h-512z'],
        'attrs' => [['fill' => 'rgb(0, 0, 0)', 'opacity' => 0.4], ['fill' => 'rgb(0, 0, 0)']],
        'codes' => [0xE901, 0xE902],
      ],
      [
        'name' => 'grid',
        'code' => 0xE903,
        'width' => 1297,
        'paths' => ['M0 0h100v100h-100z', 'M200 0h100v100h-100z'],
        'attrs' => [['fill' => 'rgb(238, 49, 36)'], ['fill' => 'rgb(238, 49, 36)']],
      ],
    ];
  }

  /**
   * Creates a library and imports a classic package into it.
   *
   * @param string $id
   *   The library id.
   * @param array $values
   *   Further entity values, such as 'render'.
   *
   * @return \Drupal\neo_icon\IconLibraryInterface
   *   The library, saved by its own import.
   */
  protected function importClassicLibrary(string $id, array $values = []): IconLibraryInterface {
    $library = IconLibrary::create($values + [
      'id' => $id,
      'label' => ucfirst($id),
      'type' => 'font',
      'status' => TRUE,
    ]);
    $library->save();
    $library->neoConfigFileUpdate($this->classicConfigFile($this->buildClassicZip()));
    return $library;
  }

  /**
   * Builds the classic package zip in the temporary directory.
   *
   * The layout is IcoMoon's classic export: selection.json at the root, the
   * font files under fonts/ named after the project ("icomoon"), and a
   * style.css whose @font-face and class rules use IcoMoon's own names, which
   * the import rewrites to the library's.
   *
   * @return string
   *   The zip's URI.
   */
  protected function buildClassicZip(): string {
    $icons = [];
    $rules = '';
    foreach ($this->classicGlyphs() as $glyph) {
      $icon = [
        'paths' => $glyph['paths'],
        'attrs' => $glyph['attrs'],
        'tags' => [$glyph['name']],
      ];
      if (isset($glyph['width'])) {
        $icon['width'] = $glyph['width'];
      }
      $properties = ['name' => $glyph['name'], 'code' => $glyph['code']];
      if (isset($glyph['codes'])) {
        $properties['codes'] = $glyph['codes'];
      }
      $icons[] = ['icon' => $icon, 'attrs' => $glyph['attrs'], 'properties' => $properties];
      $rules .= '.icon-' . $glyph['name'] . ":before {\n  content: \"\\" . dechex($glyph['code']) . "\";\n}\n";
    }
    $selection = [
      'IcoMoonType' => 'selection',
      'icons' => $icons,
      'height' => 1024,
      'metadata' => ['name' => 'icomoon'],
      'preferences' => [
        'fontPref' => [
          'prefix' => 'icon-',
          'metadata' => ['fontFamily' => 'icomoon'],
        ],
      ],
    ];
    $css = "@font-face {\n"
      . "  font-family: 'icomoon';\n"
      . "  src:  url('fonts/icomoon.woff?abc123') format('woff');\n"
      . "  font-weight: normal;\n"
      . "  font-style: normal;\n"
      . "  font-display: block;\n"
      . "}\n\n"
      . "[class^=\"icon-\"], [class*=\" icon-\"] {\n"
      . "  font-family: 'icomoon' !important;\n"
      . "  line-height: 1;\n"
      . "}\n\n"
      . $rules;

    $directory = $this->container->get('file_system')->getTempDirectory();
    $path = $directory . '/neo-icon-classic-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new \ZipArchive();
    $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFromString('selection.json', Json::encode($selection));
    $zip->addFromString('style.css', $css);
    $zip->addFromString('fonts/icomoon.woff', 'placeholder-woff');
    $zip->addFromString('demo.html', '<html></html>');
    $zip->close();
    return $path;
  }

  /**
   * Builds a config file double handing over the given zip.
   *
   * @param string $uri
   *   The zip's URI.
   *
   * @return \Drupal\neo_config_file\ConfigFileInterface
   *   The double.
   */
  protected function classicConfigFile(string $uri): ConfigFileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($uri);
    $configFile = $this->createMock(ConfigFileInterface::class);
    $configFile->method('getFile')->willReturn($file);
    return $configFile;
  }

}
