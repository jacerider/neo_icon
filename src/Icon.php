<?php

namespace Drupal\neo_icon;

use Drupal\Component\Utility\Html;
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
   * @var int
   */
  protected $code;

  /**
   * The icon codes.
   *
   * @var array
   */
  protected $codes;

  /**
   * Whether the icon is unique.
   *
   * @var bool
   */
  protected bool $unique;

  /**
   * The icon library.
   *
   * @var \Drupal\neo_icon\IconLibraryInterface
   */
  protected $library;

  /**
   * Whether this icon ignores its library's inline SVG rendering.
   *
   * @var bool
   */
  protected bool $noInlineSvg = FALSE;

  /**
   * Construct an icon.
   */
  public function __construct(array $icon_definition, IconLibraryInterface $library) {
    $this->id = $icon_definition['id'];
    $this->name = $icon_definition['name'];
    $this->prefix = $icon_definition['prefix'];
    $this->code = $icon_definition['code'];
    $this->codes = $icon_definition['codes'];
    $this->unique = !empty($icon_definition['unique']) ? $icon_definition['unique'] : FALSE;
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
    return $this->unique ? $this->id : $this->name;
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
  public function getRenderType(): string {
    if (!$this->noInlineSvg && $this->getLibrary()->isInlineSvg() && $this->getSvgData()) {
      return IconLibraryInterface::RENDER_SVG;
    }
    return (string) $this->getLibrary()->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function withoutInlineSvg(): static {
    $icon = clone $this;
    $icon->noInlineSvg = TRUE;
    return $icon;
  }

  /**
   * Returns this icon's path data from its library's store.
   *
   * @return array|null
   *   The path data, or NULL when the store holds none for this icon.
   */
  protected function getSvgData(): ?array {
    return $this->getLibrary()->getSvgData((string) $this->name);
  }

  /**
   * {@inheritdoc}
   */
  public function getTag() {
    // Inline SVG keeps the <i> wrapper a font glyph renders in, so the classes,
    // the attributes and every rule written against `.neo-icon` still apply.
    return $this->getRenderType() === 'image' ? 'svg' : 'i';
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren() {
    $build = [];
    $type = $this->getRenderType();
    if ($type === IconLibraryInterface::RENDER_SVG) {
      $build['#markup'] = Markup::create(static::buildSvg($this->getSvgData()));
    }
    elseif ($type === 'font') {
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
      '#theme' => 'neo_icon__' . $this->getRenderType(),
      '#icon' => $this,
    ];
  }

  /**
   * Builds the inline SVG for an icon's path data.
   *
   * The box is the glyph's own: 1em tall and as wide as the glyph advances in
   * the font, so it takes the space the font glyph took. Fill is the current
   * color, so `color` styles it as it did the glyph; a path with a fill or an
   * opacity of its own keeps it, as the package's stylesheet gave its font
   * glyph. The baseline offset lives in the module's inline SVG stylesheet.
   *
   * @param array $data
   *   The path data, as IconLibraryInterface::getSvgData() returns it.
   *
   * @return string
   *   The SVG markup.
   */
  public static function buildSvg(array $data): string {
    $width = (int) ($data['w'] ?? 1024);
    $height = (int) ($data['h'] ?? 1024);
    $em = rtrim(rtrim(number_format($width / max($height, 1), 4, '.', ''), '0'), '.');
    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $em . 'em" height="1em" fill="currentColor">';
    foreach ($data['p'] ?? [] as $delta => $d) {
      $svg .= '<path d="' . Html::escape((string) $d) . '"';
      $attrs = $data['a'][$delta] ?? [];
      if (isset($attrs['fill']) && is_string($attrs['fill'])) {
        $svg .= ' fill="' . Html::escape($attrs['fill']) . '"';
      }
      if (isset($attrs['opacity']) && is_numeric($attrs['opacity'])) {
        $svg .= ' opacity="' . (float) $attrs['opacity'] . '"';
      }
      $svg .= '/>';
    }
    return $svg . '</svg>';
  }

}
