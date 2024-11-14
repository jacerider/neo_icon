<?php

namespace Drupal\neo_icon;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;

/**
 * Provides the default Neo icon manager.
 */
class IconManager extends DefaultPluginManager implements IconManagerInterface {

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The object that discovers plugins managed by this manager.
   *
   * @var \Drupal\Core\Plugin\Discovery\YamlDiscovery
   */
  protected $discovery;

  /**
   * A definition cache.
   *
   * @var array
   */
  protected $definitionCache = [];

  /**
   * Provides default values for all neo_icon plugins.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'icon' => '',
    'library' => '',
    'regex' => '',
    'prefix' => [],
    'weight' => 0,
  ];

  /**
   * Constructs a new StyleVariableManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler to invoke the alter hook with.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, CacheBackendInterface $cache_backend) {
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->alterInfo('neo_icon_info');
    $this->setCacheBackend($cache_backend, 'neo_icon', ['neo_icon']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $directories = $this->moduleHandler->getModuleDirectories() + $this->themeHandler->getThemeDirectories();
      $this->discovery = new YamlDiscovery('neo.icon', $directories);
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, [
      'Drupal\Component\Utility\SortArray', 'sortByWeightElement',
    ]);
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);
    $definition['id'] = $plugin_id;

    if (empty($definition['icon'])) {
      throw new PluginException(sprintf('Neo Icon property (%s) definition "icon" is required.', $plugin_id));
    }

    if (!empty($definition['word'])) {
      $word = preg_quote($definition['word']);
      $definition['regex'] = "\b$word(s?|es?)\b";
      $definition['weight'] = $definition['weight'] ?: 10;
    }
    elseif (!empty($definition['start'])) {
      $word = preg_quote($definition['start']);
      $definition['regex'] = "\b^$word(s?|es?)\b";
      $definition['weight'] = $definition['weight'] ?: 10;
    }
    elseif (!empty($definition['end'])) {
      $word = preg_quote($definition['end']);
      $definition['regex'] = "\b$word(s?|es?)$\b";
      $definition['weight'] = $definition['weight'] ?: 10;
    }
    elseif (!empty($definition['exact'])) {
      $word = preg_quote($definition['exact']);
      $definition['regex'] = "^$word$";
    }
    unset($definition['word'], $definition['start'], $definition['end'], $definition['exact']);

    if (empty($definition['regex'])) {
      throw new PluginException(sprintf('Neo Icon property (%s) definition needs either "word", "start", "end" or "exact".', $plugin_id));
    }

    if (is_string($definition['prefix'])) {
      $definition['prefix'] = [$definition['prefix']];
    }
    if (!empty($definition['weight'])) {
      $definition['weight'] = 10000 + $definition['weight'];
    }

    if (!is_array($definition['prefix'])) {
      throw new PluginException(sprintf('Neo Icon property (%s) definition "prefix" should be an array of strings.', $plugin_id));
    }

    if (empty($definition['prefix'])) {
      $definition['prefix'][] = '_any';
    }
    if (!in_array($definition['provider'], $definition['prefix'])) {
      $definition['prefix'] = array_merge([$definition['provider']], $definition['prefix']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsWithPrefix(array $prefix = []) {
    $definitions = $this->getDefinitions();
    if (!empty($prefix) && array_filter($prefix)) {
      if (in_array('any', $prefix)) {
        $key = '_anyprefix';
        if (!isset($this->definitionCache[$key])) {
          $this->definitionCache[$key] = $definitions;
        }
      }
      else {
        $key = implode('.', $prefix);
        if (!isset($this->definitionCache[$key])) {
          $this->definitionCache[$key] = [];
          foreach ($definitions as $id => $definition) {
            if (array_intersect($prefix, $definition['prefix'])) {
              $this->definitionCache[$key][$id] = $definition;
            }
            // If we didn't find a match and the prefix is only the provider
            // it is allowed to be used as a fallback.
            if (!isset($this->definitionCache[$key][$id]) && in_array('_any', $definition['prefix'])) {
              $this->definitionCache[$key][$id] = $definition;
            }
          }
        }
      }
    }
    else {
      $key = '_noprefix';
      if (!isset($this->definitionCache[$key])) {
        $this->definitionCache[$key] = array_filter($definitions, function ($definition) {
          return in_array('_any', $definition['prefix']);
        });
      }
    }
    return $this->definitionCache[$key];
  }

}
