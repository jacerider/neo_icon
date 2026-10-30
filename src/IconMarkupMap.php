<?php

namespace Drupal\neo_icon;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;

/**
 * Renders icons for script that shows them, one entry per icon.
 */
class IconMarkupMap implements IconMarkupMapInterface {

  /**
   * Constructs an IconMarkupMap.
   *
   * @param \Drupal\neo_icon\IconRepositoryInterface $iconRepository
   *   The icon repository, which resolves a selector to its icon.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    protected readonly IconRepositoryInterface $iconRepository,
    protected readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(iterable $icons, ?BubbleableMetadata $metadata = NULL): array {
    $map = [];
    foreach ($icons as $icon) {
      if (is_string($icon)) {
        if ($icon === '' || isset($map[$icon])) {
          continue;
        }
        $icon = $this->iconRepository->getIconBySelector($icon);
      }
      if (!$icon instanceof IconInterface || isset($map[$icon->getSelector()])) {
        continue;
      }
      // Each icon in isolation: the markup goes to script, not into the page
      // being rendered, so nothing may bubble into that page by accident. What
      // the icon depends on is handed to the caller instead.
      $build = $icon->render();
      $markup = (string) $this->renderer->renderInIsolation($build);
      // Twig's debug comments, where a site has them on, would ride along in
      // every entry; the icon template itself writes none.
      $map[$icon->getSelector()] = trim((string) preg_replace('/<!--.*?-->\s*/s', '', $markup));
      if ($metadata) {
        $rendered = BubbleableMetadata::createFromRenderArray($build);
        $metadata->addCacheableDependency($rendered);
        $attachments = BubbleableMetadata::mergeAttachments($metadata->getAttachments(), $rendered->getAttachments());
        // Each library once, however many of its icons the map holds.
        if (isset($attachments['library'])) {
          $attachments['library'] = array_values(array_unique($attachments['library']));
        }
        $metadata->setAttachments($attachments);
      }
    }
    return $map;
  }

}
