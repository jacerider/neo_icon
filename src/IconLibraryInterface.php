<?php

namespace Drupal\neo_icon;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\neo_config_file\ConfigFileEntityEventInterface;

/**
 * Provides an interface for defining Neo Icon library entities.
 */
interface IconLibraryInterface extends ConfigEntityInterface, ConfigFileEntityEventInterface, EntityPublishedInterface {

  /**
   * The public URI where the styles are stored.
   */
  const LIBRARY_URI = 'public://neo-icon';

  /**
   * Icons render as the package's own glyphs: a font glyph or sprite symbol.
   */
  const RENDER_FONT = 'font';

  /**
   * Icons render as inline SVG built from the package's path data.
   */
  const RENDER_SVG = 'svg';

  /**
   * Get the icon id.
   *
   * The icon id differes from the entity id as the machine name of the entity
   * may be too generic for use as a selector and may cause CSS conflicts.
   *
   * @return string
   *   The icon id.
   */
  public function getIconId();

  /**
   * Get path to the icon library.
   *
   * @return string
   *   The path to the icon library.
   */
  public function getUri();

  /**
   * Get absolute path to icon library.
   *
   * @return string
   *   The path to the icon library.
   */
  public function getAbsolutePath();

  /**
   * Get relative path to icon library.
   *
   * @return string
   *   The path to the icon library.
   */
  public function getRelativePath();

  /**
   * Get the icon library type.
   *
   * @return string
   *   The icon type. Either 'image' or 'icon'.
   */
  public function getType();

  /**
   * Should this icon package be included globally.
   *
   * @return bool
   *   If TRUE, the icon package will be included on every page.
   */
  public function isGlobal();

  /**
   * Should this icon package be unique.
   *
   * When marked as unique, the library ID will be prepended before each icon
   * name. This allows libraries with the same icon names to coexist.
   *
   * @return bool
   *   If TRUE, only one instance of this icon package can be enabled.
   */
  public function isUnique();

  /**
   * Check if this is an SVG icon set.
   *
   * @return bool
   *   Returns TRUE if this is an SVG icon set.
   */
  public function isSvg();

  /**
   * Check if this is a font icon set.
   *
   * @return bool
   *   Returns TRUE if this is a font icon set.
   */
  public function isFont();

  /**
   * Returns the weight of the icon package.
   *
   * @return int
   *   The icon package weight.
   */
  public function getWeight();

  /**
   * Return the icon definitions provided by the icon package.
   *
   * @return array
   *   The icon definitions.
   */
  public function getIcons();

  /**
   * Return the icon definitions provided by the icon package.
   *
   * @param string $name
   *   The name of the icon.
   *
   * @return array|null
   *   The icon definition, or NULL when this library holds no icon of that
   *   name.
   */
  public function getIcon($name);

  /**
   * Return the icon item instance.
   *
   * @param string $name
   *   The name of the icon.
   *
   * @return \Drupal\neo_icon\IconInterface|null
   *   The icon item instance, or NULL when this library holds no icon of that
   *   name.
   */
  public function getIconInstance($name);

  /**
   * Return all icon item instances.
   *
   * @return \Drupal\neo_icon\IconInterface[]
   *   The icon instances.
   */
  public function getIconInstances();

  /**
   * Returns the library name.
   *
   * @return string
   *   The library name.
   */
  public function getLibraryName();

  /**
   * Return the stylesheet of the icon package if it exists.
   *
   * @return string
   *   The path to the IcoMoon style.css file.
   */
  public function getStylesheet();

  /**
   * Get the file.
   *
   * @return string[]
   *   An array of config file ids.
   */
  public function getFile();

  /**
   * How the library's icons render.
   *
   * Independent of the package type: the type says what the package shipped
   * (a font or a sprite) and is decided by each import, while this is the
   * site's choice of how icons reach the page.
   *
   * @return string
   *   Either static::RENDER_FONT (the package's own glyphs, the default) or
   *   static::RENDER_SVG (inline SVG).
   */
  public function getRender(): string;

  /**
   * Sets how the library's icons render.
   *
   * @param string $render
   *   Either static::RENDER_FONT or static::RENDER_SVG. Anything else is font.
   *
   * @return $this
   */
  public function setRender(string $render): static;

  /**
   * Whether the library renders its icons as inline SVG.
   *
   * @return bool
   *   TRUE when icons render as inline SVG.
   */
  public function isInlineSvg(): bool;

  /**
   * The type an icon of this library renders as.
   *
   * @return string
   *   'svg' for inline SVG, otherwise the package type ('font' or 'image').
   */
  public function getRenderType(): string;

  /**
   * Returns the path data of one icon, for rendering it as inline SVG.
   *
   * Read from the compact per-icon store written when the package is
   * imported, never from selection.json. A library imported before the store
   * existed has it built on first use.
   *
   * @param string $name
   *   The icon name, as the package names it.
   *
   * @return array|null
   *   An array with 'p', the path data strings, and optionally 'w' and 'h',
   *   the glyph's width and height when not 1024, and 'a', per-path attributes
   *   ('fill', 'opacity'). NULL when the store holds no paths for the icon.
   */
  public function getSvgData(string $name): ?array;

  /**
   * Returns the URL of the library's @font-face-only stylesheet.
   *
   * A library rendering inline SVG attaches this instead of its full
   * stylesheet, so CSS-only icons (Tailwind `icon-*` utilities) still have
   * their font, which a browser only downloads where one is shown.
   *
   * @return string|null
   *   The URL, or NULL when the package carries no @font-face.
   */
  public function getFontFaceStylesheet(): ?string;

  /**
   * Returns the name of the Drupal library carrying the full stylesheet.
   *
   * Registered only for a library rendering inline SVG, whose own Drupal
   * library carries the @font-face sheet alone. Whatever still shows icons as
   * font glyphs (the icon browser, an icon the SVG store has no paths for)
   * attaches this one.
   *
   * @return string
   *   The library name, without the extension prefix.
   */
  public function getFontLibraryName(): string;

  /**
   * Get icon library information.
   *
   * @param bool $no_cache
   *   If TRUE, the cached version will be ignored.
   *
   * @return array
   *   The information for the IcoMoon library.
   */
  public function getInfo($no_cache = FALSE);

  /**
   * Get unique IcoMoon library name.
   */
  public function getInfoName();

  /**
   * Get unique IcoMoon library prefix.
   */
  public function getInfoPrefix();

}
