<?php

declare(strict_types=1);

namespace Drupal\neo_icon;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;

/**
 * Defines a plugin manager to deal with neo_icon_entity_types.
 *
 * Modules can define neo_icon_entity_types in a
 * MODULE_NAME.neo_icon_entity_types.yml file contained in the module's base
 * directory. Each neo_icon_entity_type has the following structure:
 *
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 * @endcode
 *
 * @see \Drupal\neo_icon\IconEntityTypeDefault
 * @see \Drupal\neo_icon\IconEntityTypeInterface
 */
final class IconEntityTypeManager extends DefaultPluginManager {

  /**
   * The object that discovers plugins managed by this manager.
   *
   * @var \Drupal\Core\Plugin\Discovery\YamlDiscovery
   */
  protected $discovery;

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
  ];

  /**
   * Constructs IconEntityTypePluginManager object.
   */
  public function __construct(ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend) {
    $this->factory = new ContainerFactory($this);
    $this->moduleHandler = $module_handler;
    $this->alterInfo('neo_icon_entity_type_info');
    $this->setCacheBackend($cache_backend, 'neo_icon_entity_type_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): YamlDiscovery {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('neo_icon_entity_types', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
    }
    return $this->discovery;
  }

  /**
   * Get the entity icon key.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the icon key for.
   *
   * @return string
   *   The entity icon key.
   */
  public function getEntityIconKey(EntityInterface $entity): string {
    return 'entity:' . $entity->getEntityTypeId() . ':' . $entity->id();
  }

  /**
   * Get entity icon.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the icon for.
   *
   * @return string
   *   The entity icon.
   */
  public function getEntityIcon(EntityInterface $entity): string {
    if ($entity instanceof ThirdPartySettingsInterface) {
      return $entity->getThirdPartySetting('neo_icon', $this->getEntityIconKey($entity), '');
    }
    return '';
  }

  /**
   * Set entity icon.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to set the icon for.
   * @param string $icon
   *   The icon to set.
   */
  public function setEntityIcon(EntityInterface $entity, string $icon): void {
    if ($entity instanceof ThirdPartySettingsInterface) {
      $entity->setThirdPartySetting('neo_icon', $this->getEntityIconKey($entity), $icon);
    }
  }

  /**
   * Unset entity icon.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to unset the icon for.
   */
  public function unsetEntityIcon(EntityInterface $entity): void {
    if ($entity instanceof ThirdPartySettingsInterface) {
      $entity->unsetThirdPartySetting('neo_icon', $this->getEntityIconKey($entity));
    }
  }

  /**
   * Check if entity is supported by any of the plugins.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if entity is supported by any of the plugins, FALSE otherwise.
   */
  public function isSupportedEntity(EntityInterface $entity): bool {
    return $this->hasDefinition($entity->getEntityTypeId());
  }

  /**
   * Check if entity is supported by any of the plugins.
   *
   * @param string $entityTypeId
   *   The entity type id to check.
   *
   * @return bool
   *   TRUE if entity is supported by any of the plugins, FALSE otherwise.
   */
  public function isSupportedEntityTypeId($entityTypeId): bool {
    return $this->hasDefinition($entityTypeId);
  }

}
