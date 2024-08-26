<?php

namespace Drupal\neo_icon;

use Drupal\Component\Utility\ToStringTrait;

/**
 * The icon element.
 */
class IconElement implements IconElementInterface {
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
  public function iconOnly($icon_only = TRUE) {
    $this->iconOnly = $icon_only;
    return $this;
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
  public function getIcon() {
    if (!isset($this->iconObject)) {
      $this->iconObject = $this->iconRepository()->getIcon($this->text, $this->icon, $this->library, $this->prefix, $this->ignoreStatus);
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
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $icon = $this->getIcon();
    $text = $this->getText();
    if (!$icon) {
      return $text;
    }
    if (!empty($text)) {
      $markup = [
        '#theme' => 'neo_icon_element',
        '#title' => $text,
        '#icon' => $icon,
        '#position' => $this->iconPosition,
        '#icon_only' => $this->iconOnly,
      ];
    }
    else {
      $markup = [
        '#theme' => 'neo_icon',
        '#icon' => $icon,
      ];
    }
    $output = $this->renderer()->render($markup);
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
