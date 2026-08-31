<?php

declare(strict_types=1);

namespace Drupal\neo_icon;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Builds the icon element for an entity.
 *
 * The body below was `neo_icon_entity()` in the module file, which survives as
 * a one-line façade over this service because its signature is frozen: it is
 * called from another package and from procedural code on every installing
 * site. Nothing about the element it answers moved with it — the same label is
 * chosen from the same two places and the same lookup prefix is written.
 *
 * What moved is where the bundle information comes from. As a global it was
 * fetched from the container mid-branch, so the only way to exercise the
 * bundle-label choice was to boot a site; as a constructor argument it is one
 * stub, which is `docs/adr/0007`'s test — *does making this callable on its own
 * change what a test has to build?* — answering yes.
 *
 * `final` with no interface is the shape the package's own `IconRepository`
 * already carries, for the same reason: nothing needs to substitute an
 * implementation and the tests construct the real class. It carries one method
 * rather than a home for every icon helper — the other three reach no service
 * at all, and a second method here would put a container dependency behind
 * functions that do not have one.
 */
final class IconElementFactory {

  /**
   * Constructs an IconElementFactory object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle information.
   */
  public function __construct(
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  /**
   * Builds the icon element for a given entity.
   *
   * The label is the bundle's where the entity type has bundles and the
   * entity's own where it does not; an entity type that is the bundle of
   * another is looked up under that other type, so a node type wears a node's
   * icon rather than asking for one of its own.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to build the icon element for.
   * @param string|null $labelOverride
   *   An optional label override. If provided, this will be used as the label
   *   for the icon element instead of either label above.
   *
   * @return \Drupal\neo_icon\IconElement
   *   The icon element for the entity.
   */
  public function build(EntityInterface $entity, ?string $labelOverride = NULL): IconElement {
    $label = $entity->label();
    $entityTypeId = $entity->getEntityTypeId();
    if ($entity->getEntityType()->getKey('bundle')) {
      $bundle = $entity->bundle();
      $bundleInfo = $this->bundleInfo->getAllBundleInfo();
      if (isset($bundleInfo[$entity->getEntityTypeId()][$bundle])) {
        $label = $bundleInfo[$entity->getEntityTypeId()][$bundle]['label'];
      }
    }
    elseif ($bundleOf = $entity->getEntityType()->getBundleOf()) {
      $entityTypeId = $bundleOf;
    }
    return new IconElement($labelOverride ?? $label, NULL, NULL, ['entity.' . $entityTypeId]);
  }

}
