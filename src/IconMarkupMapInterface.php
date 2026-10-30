<?php

namespace Drupal\neo_icon;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Renders icons for script that shows them, one entry per icon.
 *
 * Script must not build an icon from its class: a library rendering inline
 * SVG has no per-icon stylesheet rules, so `<i class="icon-…">` draws nothing.
 * Whatever hands icons to script — a JSON endpoint, drupalSettings — renders
 * them here instead and ships the map, keyed by selector, once per icon however
 * many items show it. Script then puts the markup in the page as it is.
 */
interface IconMarkupMapInterface {

  /**
   * Renders icons, keyed by their selector.
   *
   * @param iterable $icons
   *   Icons, as \Drupal\neo_icon\IconInterface objects or as selectors
   *   (`icon-regular-cog`). Keys are ignored. An icon given twice is rendered
   *   once; a selector no available library holds is left out.
   * @param \Drupal\Core\Render\BubbleableMetadata|null $metadata
   *   Receives what each icon's rendering bubbled: its library's cache tag,
   *   so a cache holding the map is invalidated when a library is saved or
   *   re-uploaded, and the library it attaches.
   *
   * @return string[]
   *   The rendered markup, keyed by selector: `icon-regular-cog` maps to
   *   `<i class="neo-icon neo-icon-svg icon-regular-cog" …><svg …></svg></i>`
   *   for a library rendering inline SVG, or to its font glyph's `<i>`.
   */
  public function build(iterable $icons, ?BubbleableMetadata $metadata = NULL): array;

}
