<?php

namespace Drupal\neo_icon;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Template\Attribute;

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
   * Check if only the icon is shown.
   *
   * @return bool
   *   TRUE if only the icon is shown, FALSE otherwise.
   */
  public function isIconOnly(): bool;

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
   * Check if the icon is displayed as a tooltip.
   *
   * @return bool
   *   TRUE if the icon is displayed as a tooltip, FALSE otherwise.
   */
  public function isTooltip(): bool;

  /**
   * Set the icon position. Either 'before' or 'after'.
   *
   * @return $this
   */
  public function iconPosition($position);

  /**
   * Set the icon library.
   *
   * @param string|null $library
   *   The icon library.
   *
   * @return $this
   */
  public function iconLibrary(?string $library = NULL): self;

  /**
   * Set the icon prefixes.
   *
   * @param array $prefix
   *   An array of prefixes to use when looking up the icon.
   *
   * @return $this
   */
  public function iconPrefix(array $prefix = []): self;

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
   * Set the text that will be used when matching an icon.
   *
   * @param string $text
   *   The text.
   *
   * @return $this
   *   The current instance of the IconElement class.
   */
  public function iconLookup($text);

  /**
   * Set the text.
   *
   * @param string $text
   *   The text.
   */
  public function setText(mixed $text);

  /**
   * Get the text.
   *
   * @return string
   *   The text.
   */
  public function getText();

  /**
   * Set whether to assign the title attribute to the icon element.
   *
   * @param bool $assign_title
   *   Whether to assign the title attribute.
   *
   * @return $this
   */
  public function assignTitle(bool $assign_title = TRUE): self;

  /**
   * Get the icon attributes.
   *
   * @param bool $asArray
   *   (optional) If TRUE, return the attributes as an array. Defaults to TRUE.
   *
   * @return \Drupal\Core\Template\Attribute|array
   *   The icon attributes.
   */
  public function getIconAttributes($asArray = TRUE): Attribute|array;

  /**
   * Set the icon attributes.
   *
   * @param array $attributes
   *   The icon attributes.
   *
   * @return $this
   */
  public function setIconAttributes(array $attributes);

  /**
   * Add a class on the icon attributes.
   *
   * @param string $class
   *   The class to add.
   *
   * @return $this
   */
  public function addIconClass(string $class);

  /**
   * Get the label attributes.
   *
   * @param bool $asArray
   *   (optional) If TRUE, return the attributes as an array. Defaults to TRUE.
   *
   * @return \Drupal\Core\Template\Attribute|array
   *   The icon attributes.
   */
  public function getLabelAttributes($asArray = TRUE): Attribute|array;

  /**
   * Set the label attributes.
   *
   * @param array $attributes
   *   The icon attributes.
   *
   * @return $this
   */
  public function setLabelAttributes(array $attributes);

  /**
   * Add a class on the label attributes.
   *
   * @param string $class
   *   The class to add.
   *
   * @return $this
   */
  public function addLabelClass(string $class);

  /**
   * Get the icon.
   *
   * @return \Drupal\neo_icon\IconInterface|null
   *   The icon, or NULL when nothing matches.
   */
  public function getIcon();

  /**
   * Get the renderable array for the icon.
   *
   * @return array
   *   The renderable array.
   */
  public function getRenderable(): array;

  /**
   * Render the icon.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The rendered HTML.
   */
  public function render();

}
