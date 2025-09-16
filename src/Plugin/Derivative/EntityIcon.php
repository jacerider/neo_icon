<?php

namespace Drupal\neo_icon\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\neo_icon\IconEntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves block plugin definitions for all toolbar region items.
 */
final class EntityIcon extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a EntityIcon object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    private readonly EntityTypeManager $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleManager,
    private readonly IconEntityTypeManager $iconPluginManager,
  ) {
    $this->config = $config_factory->get('neo_icon.entity');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('neo_icon.entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    foreach ($this->iconPluginManager->getDefinitions() as $entityTypeId => $iconDefinition) {
      if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
        continue;
      }
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $bundleOf = $entityType->getBundleOf();
      $bundleInfo = $this->bundleManager->getBundleInfo($bundleOf);
      foreach ($bundleInfo as $bundleId => $bundle) {
        $entityType = $this->entityTypeManager->getStorage($entityTypeId)->load($bundleId);
        if ($icon = $this->iconPluginManager->getEntityIcon($entityType)) {
          $this->derivatives[$entityTypeId . '.' . $bundleId] = [
            'icon' => $icon,
            'exact' => strtolower($entityType->label()),
            'prefix' => [
              'entity',
              'entity.' . $entityTypeId,
              'entity.' . $bundleOf,
            ],
            'weight' => -1,
          ] + $base_plugin_definition;
        }
      }
    }
    foreach ($this->config->get('types') ?? [] as $entityTypeId => $icon) {
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $this->derivatives[$entityTypeId] = [
        'icon' => $icon,
        'startend' => strtolower($entityType->getLabel()),
        'prefix' => [
          'entity',
          'entity.' . $entityTypeId,
        ],
        'weight' => -1,
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
