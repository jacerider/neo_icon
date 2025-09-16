<?php

namespace Drupal\neo_icon;

use Drupal\Core\Entity\EntityInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * A class providing NeoIcon Twig extensions.
 *
 * This provides a Twig extension that registers the {{ icon() }} extension
 * to Twig.
 */
class TwigExtension extends AbstractExtension {

  /**
   * Gets a unique identifier for this Twig extension.
   *
   * @return string
   *   A unique identifier for this Twig extension.
   */
  public function getName() {
    return 'twig.neo_icon';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('icon', [$this, 'renderIcon']),
      new TwigFunction('icon_entity', [$this, 'renderIconFromEntity']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('icon_only', [$this, 'iconOnly']),
      new TwigFilter('icon_prefix', [$this, 'iconPrefix']),
      new TwigFilter('icon_class', [$this, 'iconClass']),
    ];
  }

  /**
   * Render the icon.
   *
   * @param string $icon
   *   The icon_id of the icon to render.
   * @param string $title
   *   The title of the icon.
   *
   * @return mixed[]
   *   A render array.
   */
  public static function renderIcon($icon = NULL, $title = NULL) {
    $build = [
      '#type' => 'neo_icon',
      '#title' => $title,
      '#icon' => $icon,
    ];
    return $build;
  }

  /**
   * Render the icon from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render the icon for.
   *
   * @return mixed[]
   *   A render array.
   */
  public static function renderIconFromEntity(EntityInterface $entity) {
    $entityType = $entity->getEntityType();
    $bundleTypeId = $entityType->getBundleEntityType();
    if (!$bundleTypeId) {
      return [];
    }
    $bundleLabel = \Drupal::entityTypeManager()
      ->getStorage($bundleTypeId)
      ->load($entity->bundle())
      ->label();
    if (!$bundleLabel) {
      return [];
    }
    $build = [
      '#type' => 'neo_icon',
      '#title' => $bundleLabel,
      '#icon_prefix' => ['entity', 'entity.' . $entityType->getBundleEntityType() ?: $entityType->id()],
    ];
    return $build;
  }

  /**
   * Set the icon only flag.
   */
  public function iconOnly(array|IconElementInterface $build, $iconOnly = TRUE) {
    if ($build instanceof IconElementInterface) {
      $build->iconOnly($iconOnly);
      return $build;
    }
    $build['#icon_only'] = $iconOnly;
    return $build;
  }

  /**
   * Set the icon only flag.
   */
  public function iconPrefix(array|IconElementInterface $build, array $prefix = []) {
    if ($build instanceof IconElementInterface) {
      $build->iconPrefix($prefix);
      return $build;
    }
    $build['#icon_prefix'] = $prefix;
    return $build;
  }

  /**
   * Add classes to a renderable array.
   */
  public function iconClass(array|IconElementInterface $build, string $class) {
    if ($build instanceof IconElementInterface) {
      $build->addIconClass($class);
      return $build;
    }
    $build['#icon_attributes']['class'][] = $class;
    return $build;
  }

}
