<?php

namespace Drupal\neo_icon;

use Drupal\Component\Utility\ToStringTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\neo_tooltip\Tooltip;

/**
 * The icon element.
 */
class IconElement implements IconElementInterface {

  use StringTranslationTrait;
  use ToStringTrait;

  /**
   * The Neo icon repository service.
   *
   * @var \Drupal\neo_icon\IconRepositoryInterface
   */
  protected static $iconRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected static $renderer;

  /**
   * The icon text.
   *
   * @var mixed
   */
  protected $text;

  /**
   * The text to use for icon lookup.
   *
   * @var mixed
   */
  protected $iconLookupText;

  /**
   * The icon id.
   *
   * @var string
   */
  protected $icon;

  /**
   * The library id.
   *
   * @var string
   */
  protected $library;

  /**
   * The prefixes to limit.
   *
   * @var string[]
   */
  protected $prefix;

  /**
   * Ignore status of library.
   *
   * @var bool
   */
  protected $ignoreStatus;

  /**
   * If true will show as icon-only.
   *
   * @var bool
   */
  protected $iconOnly = FALSE;

  /**
   * If true will show as tooltip.
   *
   * @var bool
   */
  protected $asTooltip = FALSE;

  /**
   * The tooltip text.
   *
   * @var string|null
   */
  protected $tooltip = NULL;

  /**
   * The icon object.
   *
   * @var \Drupal\neo_icon\IconInterface
   */
  protected $iconObject;

  /**
   * Set icon position as it related to the string.
   *
   * @var bool
   */
  protected $iconPosition = 'before';

  /**
   * The item attributes.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected Attribute $iconAttributes;

  /**
   * The label attributes.
   *
   * @var \Drupal\Core\Template\Attribute
   */
  protected Attribute $labelAttributes;

  /**
   * Construct an icon.
   *
   * @param mixed $text
   *   The icon text.
   * @param string $icon
   *   The icon id.
   * @param string $library
   *   The library id.
   * @param array $prefix
   *   An array of prefixes.
   * @param bool $ignore_status
   *   If TRUE, the status will be ignored.
   */
  public function __construct($text = NULL, $icon = NULL, $library = NULL, array $prefix = [], $ignore_status = FALSE) {
    $this->text = $text;
    $this->icon = $icon;
    $this->library = $library;
    $this->prefix = $prefix;
    $this->ignoreStatus = $ignore_status;
  }

  /**
   * {@inheritdoc}
   */
  public function iconOnly($icon_only = TRUE): self {
    $this->iconOnly = $icon_only;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function asTooltip($as_tooltip = TRUE, mixed $content = NULL): self {
    $this->asTooltip = $as_tooltip;
    $this->tooltip = $content;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isTooltip(): bool {
    return $this->asTooltip;
  }

  /**
   * {@inheritdoc}
   */
  public function iconPosition($position) {
    $this->iconPosition = $position == 'before' ? 'before' : 'after';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function iconBefore() {
    $this->iconPosition('before');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function iconAfter() {
    $this->iconPosition('after');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function iconLookup(mixed $text) {
    $this->iconLookupText = $text;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    if (!isset($this->iconObject)) {
      $this->iconObject = $this->iconRepository()->getIcon($this->iconLookupText ?? $this->text, $this->icon, $this->library, $this->prefix, $this->ignoreStatus);
    }
    return $this->iconObject;
  }

  /**
   * {@inheritdoc}
   */
  public function getText($rendered = TRUE) {
    $text = $this->text ?? '';
    if ($rendered && is_array($this->text)) {
      $text = $this->renderer()->render($text);
    }
    if ($text && is_string($text)) {
      // phpcs:ignore
      $text = $this->t($text);
    }
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function setIconAttributes(array $attributes) {
    $this->iconAttributes = new Attribute($attributes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIconAttributes($asArray = TRUE): Attribute|array {
    if (!isset($this->iconAttributes)) {
      $this->setIconAttributes([]);
    }
    return $asArray ? $this->iconAttributes->toArray() : $this->iconAttributes;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabelAttributes(array $attributes) {
    $this->labelAttributes = new Attribute($attributes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelAttributes($asArray = TRUE): Attribute|array {
    if (!isset($this->labelAttributes)) {
      $this->setLabelAttributes([]);
    }
    return $asArray ? $this->labelAttributes->toArray() : $this->labelAttributes;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderable(): array {
    $icon = $this->getIcon();
    $text = $this->getText();
    if (!$icon) {
      $build = ['#markup' => $text];
    }
    elseif (!empty($text)) {
      $build = [
        '#theme' => 'neo_icon_element',
        '#title' => $text,
        '#icon' => $icon,
        '#position' => $this->iconPosition,
        '#icon_only' => $this->iconOnly,
        '#attributes_icon' => $this->getIconAttributes(),
        '#attributes_label' => $this->getLabelAttributes(),
      ];
      if (!$this->isTooltip()) {
        $build['#attributes_icon']['title'] = $text;
      }
    }
    else {
      $build = [
        '#theme' => 'neo_icon',
        '#icon' => $icon,
        '#attributes' => $this->getIconAttributes(),
      ];
    }
    if ($this->isTooltip()) {
      $tooltip = new Tooltip($this->tooltip ?? $text);
      $tooltip->applyTo($build);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = $this->getRenderable();
    $output = $this->renderer()->render($build);
    return $output;
  }

  /**
   * Returns a representation of the object for use in JSON serialization.
   *
   * @return string
   *   The safe string content.
   */
  public function jsonSerialize():string {
    return $this->__toString();
  }

  /**
   * Magic __sleep() method to avoid serializing the services.
   */
  public function __sleep() {
    return ['text', 'icon', 'library', 'prefix', 'iconOnly', 'iconPosition'];
  }

  /**
   * Gets the Neo icon repository.
   *
   * @return \Drupal\neo_icon\IconRepositoryInterface
   *   The Neo icon repository.
   */
  protected function iconRepository() {
    if (!static::$iconRepository) {
      // @phpstan-ignore-next-line
      static::$iconRepository = \Drupal::service('neo_icon.repository');
    }
    return static::$iconRepository;
  }

  /**
   * Gets the renderer service.
   *
   * @return \Drupal\Core\Render\RendererInterface
   *   The renderer service.
   */
  protected function renderer() {
    if (!static::$renderer) {
      // @phpstan-ignore-next-line
      static::$renderer = \Drupal::service('renderer');
    }
    return static::$renderer;
  }

}
