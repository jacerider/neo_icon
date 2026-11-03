<?php

namespace Drupal\neo_icon\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\neo_config_file\ConfigFileInterface;
use Drupal\neo_config_file\Exception\ExtractionRefusedException;
use Drupal\neo_icon\IcoMoon\ProjectNormalizer;
use Drupal\neo_icon\Icon;
use Drupal\neo_icon\IconLibraryInterface;

/**
 * Defines the Neo Icon Library entity.
 *
 * @ConfigEntityType(
 *   id = "neo_icon_library",
 *   label = @Translation("Neo Icon Library"),
 *   label_plural = @Translation("Icon Libraries"),
 *   label_collection = @Translation("Icon Libraries"),
 *   handlers = {
 *     "storage" = "Drupal\neo_icon\IconLibraryStorage",
 *     "view_builder" = "Drupal\neo_icon\IconLibraryViewBuilder",
 *     "list_builder" = "Drupal\neo_icon\IconLibraryListBuilder",
 *     "form" = {
 *       "add" = "Drupal\neo_icon\Form\IconLibraryForm",
 *       "edit" = "Drupal\neo_icon\Form\IconLibraryForm",
 *       "delete" = "Drupal\neo_icon\Form\IconLibraryDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *   },
 *   config_prefix = "neo_icon_library",
 *   admin_permission = "administer neo_icon",
 *   static_cache = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "type",
 *     "render",
 *     "file",
 *     "status",
 *     "global",
 *     "unique",
 *     "weight",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/neo/icon/library/{neo_icon_library}",
 *     "add-form" = "/admin/config/neo/icon/library/add",
 *     "edit-form" = "/admin/config/neo/icon/library/{neo_icon_library}/edit",
 *     "delete-form" = "/admin/config/neo/icon/library/{neo_icon_library}/delete",
 *     "collection" = "/admin/config/neo/icon"
 *   }
 * )
 */
class IconLibrary extends ConfigEntityBase implements IconLibraryInterface {

  /**
   * The Neo Icon Library ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Neo Icon Library label.
   *
   * @var string
   */
  protected $label;

  /**
   * The icon type. Either 'font' or 'image'.
   *
   * @var string
   */
  protected $type;

  /**
   * How the icons render: 'font' (the package's own glyphs) or 'svg'.
   *
   * @var string
   */
  protected $render = IconLibraryInterface::RENDER_FONT;

  /**
   * The Neo Icon Library file.
   *
   * @var string
   */
  protected $file;

  /**
   * Path data shards read this request, keyed by shard id.
   *
   * @var array<string, array>
   */
  protected array $svgShards = [];

  /**
   * The library weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The icon definitions, or NULL until they are read.
   *
   * @var array|null
   */
  protected $iconDefinitions;

  /**
   * The library info.
   *
   * @var array<string, mixed>
   */
  protected $info;

  /**
   * The list cache tags to invalidate for this entity.
   *
   * @return string[]
   *   Set of list cache tags.
   */
  protected function getListCacheTagsToInvalidate() {
    $tags = parent::getListCacheTagsToInvalidate();
    $tags[] = 'library_info';
    $tags[] = 'neo_icon_libraries';
    return $tags;
  }

  /**
   * {@inheritDoc}
   */
  public function getIconId() {
    return 'icon-' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritDoc}
   */
  public function getFile() {
    return $this->file;
  }

  /**
   * Set the icon library type.
   *
   * @param string $type
   *   The icon library type. Either image or font.
   *
   * @return $this
   */
  protected function setType($type) {
    $this->type = $type == 'image' ? 'image' : 'font';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isGlobal() {
    return (bool) $this->get('global');
  }

  /**
   * {@inheritdoc}
   */
  public function isUnique() {
    return (bool) $this->get('unique');
  }

  /**
   * {@inheritdoc}
   */
  public function isSvg() {
    return $this->getType() == 'image';
  }

  /**
   * {@inheritdoc}
   */
  public function isFont() {
    return $this->getType() == 'font';
  }

  /**
   * {@inheritdoc}
   */
  public function getRender(): string {
    return $this->render === IconLibraryInterface::RENDER_SVG ? IconLibraryInterface::RENDER_SVG : IconLibraryInterface::RENDER_FONT;
  }

  /**
   * {@inheritdoc}
   */
  public function setRender(string $render): static {
    $this->render = $render === IconLibraryInterface::RENDER_SVG ? IconLibraryInterface::RENDER_SVG : IconLibraryInterface::RENDER_FONT;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isInlineSvg(): bool {
    return $this->getRender() === IconLibraryInterface::RENDER_SVG;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderType(): string {
    return $this->isInlineSvg() ? IconLibraryInterface::RENDER_SVG : (string) $this->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function getFontLibraryName(): string {
    return $this->getLibraryName() . '.font';
  }

  /**
   * {@inheritdoc}
   */
  public function getSvgData(string $name): ?array {
    $shard = static::svgShard($name);
    if (!isset($this->svgShards[$shard])) {
      if (!file_exists($this->getSvgStoreUri() . '/manifest.json')) {
        // A library imported before the store existed. Built once, from the
        // package's selection.json, and read from shards from then on.
        $this->writeSvgStore();
      }
      $this->svgShards[$shard] = [];
      $path = $this->getSvgStoreUri() . '/' . $shard . '.json';
      if (file_exists($path)) {
        $this->svgShards[$shard] = Json::decode((string) file_get_contents($path)) ?: [];
      }
    }
    $data = $this->svgShards[$shard][$name] ?? NULL;
    return !empty($data['p']) ? $data : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFontFaceStylesheet(): ?string {
    $path = $this->getUri() . '/font-face.css';
    if (!file_exists($path) && !$this->writeFontFaceStylesheet()) {
      return NULL;
    }
    return \Drupal::service('file_url_generator')->generateString($path);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Switching a library to inline SVG, including by a configuration import,
    // must not leave the first page to pay for the derived files. Both are
    // also built on demand, should a files directory lack them.
    if ($this->isInlineSvg() && file_exists($this->getUri() . '/selection.json')) {
      if (!file_exists($this->getSvgStoreUri() . '/manifest.json')) {
        $this->writeSvgStore();
      }
      if (!file_exists($this->getUri() . '/font-face.css')) {
        $this->writeFontFaceStylesheet();
      }
    }
  }

  /**
   * The directory of the per-icon path data store.
   *
   * @return string
   *   The store's URI.
   */
  protected function getSvgStoreUri(): string {
    return $this->getUri() . '/svg';
  }

  /**
   * The store shard an icon's path data is written to.
   *
   * The store is sharded so a page reads a few small files rather than a
   * whole library's path data: the regular set alone carries 3.4MB of paths.
   *
   * @param string $name
   *   The icon name.
   *
   * @return string
   *   Two hex characters.
   */
  protected static function svgShard(string $name): string {
    return substr(md5($name), 0, 2);
  }

  /**
   * Writes the per-icon path data store from the package's selection.json.
   *
   * Each icon keeps only what rendering needs: its paths, its width and
   * height when they are not 1024, and the fill and opacity of any path that
   * has one. Icons without paths (a package normalized without its loose SVG
   * files) are left out, and render as their font glyph instead.
   */
  public function writeSvgStore(): void {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $directory = $this->getSvgStoreUri();
    $file_system->deleteRecursive($directory);
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->svgShards = [];

    $info = $this->getInfo(TRUE);
    $shards = [];
    $count = 0;
    foreach ($info['icons'] ?? [] as $icon) {
      $data = static::svgDataFromSelection($icon, (int) ($info['height'] ?? 1024));
      if (!$data) {
        continue;
      }
      $names = isset($icon['properties']['name']) ? [$icon['properties']['name']] : ($icon['icon']['tags'] ?? []);
      foreach ($names as $name) {
        $shards[static::svgShard((string) $name)][(string) $name] = $data;
        $count++;
      }
    }
    foreach ($shards as $shard => $icons) {
      $file_system->saveData(Json::encode($icons), $directory . '/' . $shard . '.json', FileExists::Replace);
    }
    // Written last: its presence is what says the store is complete.
    $file_system->saveData(Json::encode(['version' => 1, 'icons' => $count]), $directory . '/manifest.json', FileExists::Replace);
  }

  /**
   * Extracts one icon's path data from its selection.json entry.
   *
   * @param array $icon
   *   The entry in selection.json's "icons".
   * @param int $height
   *   The package's glyph height.
   *
   * @return array|null
   *   The compact path data, or NULL when the entry has no paths.
   */
  public static function svgDataFromSelection(array $icon, int $height = 1024): ?array {
    $paths = array_values(array_filter($icon['icon']['paths'] ?? [], 'is_string'));
    if (!$paths) {
      return NULL;
    }
    $data = ['p' => $paths];
    // A glyph normalized from a newer package's loose SVG carries its own
    // height, which is its viewBox's.
    $height = (int) ($icon['icon']['height'] ?? $height);
    $width = (int) ($icon['icon']['width'] ?? $height);
    if ($width !== 1024) {
      $data['w'] = $width;
    }
    if ($height !== 1024) {
      $data['h'] = $height;
    }
    $attrs = [];
    $any = FALSE;
    foreach (array_keys($paths) as $delta) {
      $source = $icon['icon']['attrs'][$delta] ?? $icon['attrs'][$delta] ?? [];
      $attr = [];
      if (isset($source['fill']) && is_string($source['fill']) && $source['fill'] !== '') {
        $attr['fill'] = $source['fill'];
      }
      if (isset($source['opacity']) && is_numeric($source['opacity'])) {
        $attr['opacity'] = (float) $source['opacity'];
      }
      $attrs[] = $attr;
      $any = $any || $attr;
    }
    if ($any) {
      $data['a'] = $attrs;
    }
    return $data;
  }

  /**
   * Writes the @font-face-only stylesheet from the package's style.css.
   *
   * @return bool
   *   TRUE when a stylesheet carrying at least one @font-face was written.
   */
  protected function writeFontFaceStylesheet(): bool {
    $source = $this->getUri() . '/style.css';
    if (!file_exists($source)) {
      return FALSE;
    }
    $css = static::extractFontFace((string) file_get_contents($source));
    if ($css === '') {
      return FALSE;
    }
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->saveData($css, $this->getUri() . '/font-face.css', FileExists::Replace);
    return TRUE;
  }

  /**
   * Returns the @font-face rules of a stylesheet and nothing else.
   *
   * @param string $css
   *   The stylesheet.
   *
   * @return string
   *   Its @font-face rules, with `font-display: swap`.
   */
  public static function extractFontFace(string $css): string {
    if (!preg_match_all('/@font-face\s*\{[^}]*\}/', $css, $matches)) {
      return '';
    }
    return static::swapFontDisplay(implode("\n\n", $matches[0])) . "\n";
  }

  /**
   * Has a stylesheet's fonts swap in rather than block the text.
   *
   * @param string $css
   *   The stylesheet.
   *
   * @return string
   *   The stylesheet with every `font-display: block` made `swap`.
   */
  public static function swapFontDisplay(string $css): string {
    return (string) preg_replace('/font-display:\s*block/', 'font-display: swap', $css);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritDoc}
   */
  public function getLibraryName() {
    return 'library.' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getStylesheet() {
    $path = $this->getUri() . '/style.css';
    return file_exists($path) ? \Drupal::service('file_url_generator')->generateString($path) : NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getUri() {
    return IconLibraryInterface::LIBRARY_URI . '/' . str_replace('_', '-', $this->id());
  }

  /**
   * {@inheritDoc}
   */
  public function getAbsolutePath() {
    return \Drupal::service('file_url_generator')->generateAbsoluteString($this->getUri());
  }

  /**
   * {@inheritDoc}
   */
  public function getRelativePath() {
    return \Drupal::service('file_url_generator')->transformRelative($this->getAbsolutePath());
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo($no_cache = FALSE) {
    if (empty($this->info) || $no_cache) {
      $this->info = [];
      $path = $this->getUri() . '/selection.json';
      if (file_exists($path)) {
        $data = file_get_contents($path);
        $this->info = Json::decode($data);
      }
    }
    return $this->info;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfoName() {
    $info = $this->getInfo();
    return $info['metadata']['name'] ?? substr($this->getInfoPrefix(), 0, -1);
  }

  /**
   * {@inheritdoc}
   */
  public function getInfoPrefix() {
    $info = $this->getInfo();
    return $info['preferences'][$this->getType() . 'Pref']['prefix'] ?? $this->getIconId() . '-';
  }

  /**
   * {@inheritdoc}
   */
  public function getIcons() {
    if (!isset($this->iconDefinitions)) {
      $this->iconDefinitions = [];
      $path = $this->getUri() . '/definitions.json';
      if (file_exists($path)) {
        $data = file_get_contents($path);
        $this->iconDefinitions = Json::decode($data);
        if ($this->isUnique()) {
          $uniqueIconDefinitions = [];
          foreach ($this->iconDefinitions as $icon) {
            $uniqueIconDefinitions[$icon['id']] = $icon;
            $uniqueIconDefinitions[$icon['id']]['unique'] = TRUE;
          }
          $this->iconDefinitions = $uniqueIconDefinitions;
        }
      }
    }
    return $this->iconDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon($name) {
    $icon = NULL;
    $icons = $this->getIcons();
    if (isset($icons[$name])) {
      $icon = $icons[$name];
    }
    return $icon;
  }

  /**
   * {@inheritdoc}
   */
  public function getIconInstance($name) {
    if ($icon = $this->getIcon($name)) {
      return new Icon($icon, $this);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIconInstances() {
    $instances = [];
    foreach ($this->getIcons() as $icon) {
      $instances[$icon['id']] = new Icon($icon, $this);
    }
    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return !empty($this->status);
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished() {
    $this->status = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnpublished() {
    $this->status = FALSE;
    return $this;
  }

  /**
   * Sorts by weight.
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    /** @var \Drupal\neo_icon\Entity\IconLibrary $a */
    /** @var \Drupal\neo_icon\Entity\IconLibrary $b */
    // Separate enabled from disabled.
    $status = (int) $b->status() - (int) $a->status();
    if ($status !== 0) {
      return $status;
    }
    return $a->getWeight() - $b->getWeight();
  }

  /**
   * Prepare the library.
   */
  protected function prepareLibrary() {
    $path = $this->getUri();
    $base_id = $this->id();
    $icon_id = $this->getIconId();
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    // The package was just unpacked over the library directory. Info read
    // earlier in the request, such as by the library form, names the
    // previous package, and renaming from those names would match nothing.
    $this->getInfo(TRUE);

    // Remove unnecessary files.
    $file_system->deleteRecursive($path . '/demo-files');
    foreach ([
      'demo-external-svg.html',
      'demo.html',
      'Read Me.txt',
    ] as $filepath) {
      if (file_exists($path . '/' . $filepath)) {
        $file_system->delete($path . '/' . $filepath);
      }
    }

    // Newer IcoMoon packages ship a project file rather than a selection.json
    // and nest their assets in subdirectories. Rewrite them into the classic
    // layout so nothing downstream has to know the difference. The normalizer
    // writes a selection.json that already carries this entity's name and
    // prefix, so the rewriting below is neither needed nor wanted.
    $normalizer = new ProjectNormalizer($path, $icon_id, $file_system);
    if ($project_file = $normalizer->detect()) {
      $this->setType($normalizer->normalize($project_file));
      if ($skipped = $normalizer->getSkipped()) {
        \Drupal::logger('neo_icon')->warning('Skipped @count unusable @label glyphs: @glyphs.', [
          '@count' => count($skipped),
          '@label' => $this->label(),
          '@glyphs' => implode(', ', array_slice($skipped, 0, 10)),
        ]);
      }
      $this->prepareDefinitions();
      $this->save();
      return;
    }

    if (!file_exists($path . '/selection.json')) {
      throw new \Exception(sprintf('The %s package is not a recognized IcoMoon archive. It contains neither a selection.json nor a *.icomoon.json project file.', $this->label()));
    }

    // A font takes precedence over a sprite. Classic packages only ever
    // contained one or the other, but the newer export can carry both.
    $font_glob = $file_system->realpath($path . '/fonts/*.*');
    $font_files = $font_glob ? (glob($font_glob) ?: []) : [];
    if ($font_files) {
      $this->setType('font');
      foreach ($font_files as $file_to_rename_path) {
        $file_new_path = str_replace('fonts/' . $this->getInfoName(), 'fonts/' . $icon_id, $file_to_rename_path);
        if ($file_to_rename_path !== $file_new_path) {
          $file_system->move($file_to_rename_path, $file_new_path, FileExists::Replace);
        }
      }
    }
    elseif (file_exists($path . '/symbol-defs.svg')) {
      $this->setType('image');
      // Update symbol to match new id.
      $file_path = $path . '/symbol-defs.svg';
      $file_contents = file_get_contents($file_path);
      $file_contents = str_replace($this->getInfoPrefix(), $icon_id . '-', $file_contents);
      file_put_contents($file_path, $file_contents);
    }
    else {
      throw new \Exception(sprintf('The %s IcoMoon package contains neither a font nor an SVG sprite.', $this->label()));
    }

    // Used after type has been set.
    $name = $this->getInfoName();
    $prefix = $this->getInfoPrefix();

    // Update selection.json.
    $file_path = $path . '/selection.json';
    $file_contents = file_get_contents($file_path);
    $replacements = [
      '"icons":' => '_IconPackagesIcons',
      '"icon":' => '_IconPackageIcon',
      'iconIdx' => '_IconPackageIdx',
    ];
    // Prevent overwrite of system properties.
    foreach ($replacements as $find => $replace) {
      $file_contents = str_replace($find, $replace, $file_contents);
    }
    // The name and selector should be updated to match entity info.
    $file_contents = str_replace($prefix, $icon_id . '-', $file_contents);
    $file_contents = str_replace('"' . $name . '"', '"' . $icon_id . '"', $file_contents);
    $file_contents = str_replace('".' . $name . '"', '".' . $icon_id . '"', $file_contents);
    $file_contents = str_replace('"icon-"', '"' . $icon_id . '-"', $file_contents);
    $file_contents = str_replace('icomoon', $icon_id, $file_contents);
    // Revert system properties.
    foreach ($replacements as $replace => $find) {
      $file_contents = str_replace($find, $replace, $file_contents);
    }
    file_put_contents($file_path, $file_contents);

    // Update IcoMoon stylesheet.
    $file_path = $path . '/style.css';
    $file_contents = static::rewriteStylesheet((string) file_get_contents($file_path), $prefix, $name, $icon_id, $base_id);
    file_put_contents($file_path, $file_contents);

    $this->prepareDefinitions();
    $this->save();
  }

  /**
   * Rewrites a classic IcoMoon stylesheet to this library's names.
   *
   * @param string $css
   *   The package's style.css.
   * @param string $prefix
   *   The package's class prefix, such as "icon-".
   * @param string $name
   *   The package's font name, such as "icomoon".
   * @param string $iconId
   *   The library's icon id, such as "icon-fa".
   * @param string $baseId
   *   The library's machine name, such as "fa".
   *
   * @return string
   *   The stylesheet with every selector and the font renamed, font URLs
   *   stripped of IcoMoon's query strings and given a version token instead.
   */
  public static function rewriteStylesheet(string $css, string $prefix, string $name, string $iconId, string $baseId): string {
    // The style.css file provided by IcoMoon contains query parameters where it
    // loads in the font files. Drupal CSS aggregation doesn't handle this well
    // so we need to remove it.
    $css = preg_replace('(\?[a-zA-Z0-9#\-\_]*)', '', $css);
    // The name and selector should be updated to match entity info.
    $css = str_replace($prefix, $iconId . '-', $css);
    $css = str_replace($name, $iconId, $css);
    // Under some conditions, icon-icon exists.
    $css = str_replace('icon-icon', 'icon', $css);
    // A package whose classes already carried the library id comes out with
    // it twice. Only a doubled id followed by a dash is collapsed: a glyph
    // whose own name starts with the id ("facebook" in a library "fa") must
    // keep its class.
    $css = str_replace($iconId . '-' . $baseId . '-', $iconId . '-', $css);
    // A blocked glyph hides the text it sits in until the font arrives, so
    // icon fonts swap in instead.
    $css = static::swapFontDisplay($css);
    // Add a version query string to prevent caching issues.
    $version = md5($css);
    foreach (['.eot', '.woff', '.woff2', '.ttf', '.svg'] as $extension) {
      $css = str_replace($extension, $extension . '?v=' . $version, $css);
    }
    return $css;
  }

  /**
   * Prepare definitions.
   *
   * In order to speed up indexing a definitions file is created.
   */
  protected function prepareDefinitions() {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    // Unset rather than emptied, so the next getIcons() reads the definitions
    // written below instead of answering an empty library for the rest of the
    // request.
    $this->iconDefinitions = NULL;
    $info = $this->getInfo(TRUE);
    $definitions = [];
    if (!empty($info['icons'])) {
      $id = $this->id();
      $prefix = $this->getInfoPrefix();
      foreach ($info['icons'] as $icon) {
        if (isset($icon['properties']['name'])) {
          $name = $icon['properties']['name'];
          $definitions[$name] = [
            'id' => $id . '-' . $name,
            'name' => $name,
            'prefix' => $prefix,
            'code' => $icon['properties']['code'],
          ];
          $definitions[$name]['codes'] = [];
          if (isset($icon['properties']['codes'])) {
            $definitions[$name]['codes'] = $icon['properties']['codes'];
          }
        }
        else {
          foreach ($icon['icon']['tags'] as $name) {
            $definitions[$name] = [
              'id' => $id . '-' . $name,
              'name' => $name,
              'prefix' => $prefix,
              'code' => $icon['properties']['code'],
            ];
            $definitions[$name]['codes'] = [];
            if (isset($icon['properties']['codes'])) {
              $definitions[$name]['codes'] = $icon['properties']['codes'];
            }
          }
        }
      }
    }
    $file_system->saveData(Json::encode($definitions), $this->getUri() . '/definitions.json', FileExists::Replace);
    // The derived files every library carries whatever it renders as, so
    // switching it to inline SVG later needs no re-import.
    $this->writeSvgStore();
    $this->writeFontFaceStylesheet();
  }

  /**
   * {@inheritDoc}
   */
  public function neoConfigFileUpdate(ConfigFileInterface $config_file) {
    /** @var \Drupal\neo_config_file\ZipExtractor $zip_extractor */
    $zip_extractor = \Drupal::service('neo_config_file.zip_extractor');
    $file = $config_file->getFile();
    if (!$file) {
      throw new \Exception(sprintf('Cannot find %s.', $config_file->getConfigUri()));
    }
    $zip_uri = $file->getFileUri();
    $directory = $this->getUri();
    // Nothing is emptied first: the extractor unpacks elsewhere and replaces
    // the library directory only once the whole package is on disk, so a
    // package that will not open leaves the installed library where it is.
    try {
      $zip_extractor->extract($zip_uri, $directory);
    }
    catch (ExtractionRefusedException $e) {
      throw new \Exception(sprintf('Cannot extract %s, not a valid archive.', $zip_uri), 0, $e);
    }
    $this->prepareLibrary();
  }

  /**
   * {@inheritDoc}
   */
  public function neoConfigFileDelete(ConfigFileInterface $config_file) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->deleteRecursive($this->getUri());
  }

}
