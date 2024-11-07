<?php

namespace Drupal\neo_icon;

use Drupal\Component\Render\MarkupInterface;

/**
 * Interface for an icon.
 */
interface IconElementInterface extends MarkupInterface {

  /**
   * Only show the icon.
   *
   * @param bool $icon_only
   *   (optional) Whether to hide the string and only show the icon.
   *
   * @return $this
   *   The current instance of the IconElement class.
   */
  public function iconOnly($icon_only = TRUE): self;

  /**
   * Sets the icon to be displayed as a tooltip.
   *
   * @param bool $as_tooltip
   *   (optional) Whether to display the icon as a tooltip. Defaults to TRUE.
   *
   * @return $this
   *   The current instance of the IconElement class.
   */
  public function asTooltip($as_tooltip = TRUE): self;

  /**
   * Set the icon position. Either 'before' or 'after'.
   *
   * @return $this
   */
  public function iconPosition($position);

  /**
   * Show the icon before the title.
   *
   * @return $this
   */
  public function iconBefore();

  /**
   * Show the icon before the title.
   *
   * @return $this
   */
  public function iconAfter();

  /**
   * Get the text.
   *
   * @return string
   *   The text.
   */
  public function getText();

  /**
   * Get the icon.
   *
   * @return \Drupal\neo_icon\IconInterface
   *   The icon.
   */
  public function getIcon();

}
