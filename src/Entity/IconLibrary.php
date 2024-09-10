<?php

namespace Drupal\neo_icon\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\neo_config_file\ConfigFileInterface;
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
 *     "file",
 *     "status",
 *     "global",
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
   * The Neo Icon Library file.
   *
   * @var string
   */
  protected $file;

  /**
   * The library weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The icon definitions.
   *
   * @var array
   */
  protected $iconDefinitions;

  /**
   * The library info.
   *
   * @var bool
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
    return $info['preferences'][$this->getType() . 'Pref']['prefix'];
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
  protected function parepareLibrary() {
    $path = $this->getUri();
    $base_id = $this->id();
    $icon_id = $this->getIconId();
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

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

    if (file_exists($path . '/symbol-defs.svg')) {
      $this->setType('image');
      // Update symbol to match new id.
      $file_path = $path . '/symbol-defs.svg';
      $file_contents = file_get_contents($file_path);
      $file_contents = str_replace($this->getInfoPrefix(), $icon_id . '-', $file_contents);
      file_put_contents($file_path, $file_contents);
    }
    else {
      $this->setType('font');
      $files_to_rename = $path . '/fonts/*.*';
      foreach (glob($file_system->realpath($files_to_rename)) as $file_to_rename_path) {
        $file_new_path = str_replace('fonts/' . $this->getInfoName(), 'fonts/' . $icon_id, $file_to_rename_path);
        if ($file_to_rename_path !== $file_new_path) {
          $file_system->move($file_to_rename_path, $file_new_path, FileExists::Replace);
        }
      }
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
    $file_contents = file_get_contents($file_path);
    // The style.css file provided by IcoMoon contains query parameters where it
    // loads in the font files. Drupal CSS aggregation doesn't handle this well
    // so we need to remove it.
    $file_contents = preg_replace('(\?[a-zA-Z0-9#\-\_]*)', '', $file_contents);
    // The name and selector should be updated to match entity info.
    $file_contents = str_replace($prefix, $icon_id . '-', $file_contents);
    $file_contents = str_replace($name, $icon_id, $file_contents);
    // Under some conditions, icon-icon exists.
    $file_contents = str_replace('icon-icon', 'icon', $file_contents);
    $file_contents = str_replace($icon_id . '-' . $base_id, $icon_id, $file_contents);
    file_put_contents($file_path, $file_contents);

    $this->prepareDefinitions();
    $this->save();
  }

  /**
   * Prepare definitions.
   *
   * In order to speed up indexing a definitions file is created.
   */
  protected function prepareDefinitions() {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $this->iconDefinitions = [];
    $info = $this->getInfo(TRUE);
    $definitions = [];
    if ($info) {
      $id = $this->id();
      foreach ($info['icons'] as $icon) {
        if (isset($icon['properties']['name'])) {
          $name = $icon['properties']['name'];
          $definitions[$name] = [
            'id' => $id . '-' . $name,
            'name' => $name,
            'prefix' => $info['preferences'][$this->getType() . 'Pref']['prefix'],
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
              'prefix' => $info['preferences'][$this->getType() . 'Pref']['prefix'],
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
  }

  /**
   * {@inheritDoc}
   */
  public function neoConfigFileUpdate(ConfigFileInterface $config_file) {
    /** @var \Drupal\Core\Archiver\ArchiverManager $archiver_manager */
    $archiver_manager = \Drupal::service('plugin.manager.archiver');
    $file = $config_file->getFile();
    if (!$file) {
      throw new \Exception(t('Cannot find %file.', ['%file' => $config_file->getConfigUri()]));
    }
    $zip_uri = $file->getFileUri();
    $archiver = $archiver_manager->getInstance(['filepath' => $zip_uri]);
    if (!$archiver) {
      throw new \Exception(t('Cannot extract %file, not a valid archive.', ['%file' => $zip_uri]));
    }
    $directory = $this->getUri();
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $file_system->deleteRecursive($directory);
    $archiver->extract($directory);
    $this->parepareLibrary();
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
