<?php

namespace Drupal\neo_icon;

use Drupal\Core\Render\Markup;

/**
 * The icon repository.
 */
class Icon implements IconInterface {

  /**
   * The icon id.
   *
   * @var string
   */
  protected $id;

  /**
   * The icon name.
   *
   * @var string
   */
  protected $name;

  /**
   * The icon prefix.
   *
   * @var string
   */
  protected $prefix;

  /**
   * The icon code.
   *
   * @var array
   */
  protected $code;

  /**
   * The icon codes.
   *
   * @var array
   */
  protected $codes;

  /**
   * The icon library.
   *
   * @var \Drupal\neo_icon\IconLibraryInterface
   */
  protected $library;

  /**
   * Construct an icon.
   */
  public function __construct(array $icon_definition, IconLibraryInterface $library) {
    $this->id = $icon_definition['id'];
    $this->name = $icon_definition['name'];
    $this->prefix = $icon_definition['prefix'];
    $this->code = $icon_definition['code'];
    $this->codes = $icon_definition['codes'];
    $this->library = $library;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrary() {
    return $this->library;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * {@inheritdoc}
   */
  public function getCodes() {
    return $this->codes;
  }

  /**
   * {@inheritdoc}
   */
  public function getHex() {
    return $this->getLibrary()->isFont() ? '\\' . dechex($this->getCode()) : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getSelector() {
    return $this->prefix . $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getTag() {
    return $this->getLibrary()->isSvg() ? 'svg' : 'i';
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren() {
    $build = [];
    if ($this->getLibrary()->isFont()) {
      // Font glyphs cannot have more than one color by default. Using CSS,
      // IcoMoon layers multiple glyphs on top of each other to implement
      // multicolor glyphs. As a result, these glyphs take more than one
      // character code and cannot have ligatures. To avoid multicolor glyphs,
      // reimport your SVG after changing all its colors to the same color.
      if (!empty($this->codes) && count($this->codes)) {
        for ($i = 1; $i <= count($this->codes); $i++) {
          $build[]['#markup'] = '<span class="path' . $i . '"></span>';
        }
      }
    }
    else {
      $build['#markup'] = Markup::create('<use xlink:href="' . $this->getLibrary()->getRelativePath() . '/symbol-defs.svg#' . $this->getSelector() . '"></use>');
      $build['#allowed_tags'] = ['use', 'xlink'];
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      '#theme' => 'neo_icon__' . $this->getLibrary()->getType(),
      '#icon' => $this,
    ];
  }

}
