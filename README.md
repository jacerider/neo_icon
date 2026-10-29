CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Rendering: font or inline SVG
 * Requirements
 * Installation
 * Drush Commands
 * Usage within PHP
 * Usage within SCSS
 * Icon Definitions


INTRODUCTION
------------

Allow Icomoon icon packages to be utilized within Drupal.


ICOMOON PACKAGES
----------------

Both IcoMoon export layouts are accepted, and the one in use is detected on
upload.

The classic layout:

```
selection.json
style.css
fonts/<name>.{ttf,woff,eot,svg}
symbol-defs.svg   (SVG packages)
```

The newer layout, produced by IcoMoon's current app:

```
<project>.icomoon.json
font/style.css
font/fonts/<name>.{otf,ttf,woff,woff2}
svg/<name>.svg
symbol-defs/symbol-defs.svg
```

A newer package is rewritten into the classic layout on import, so nothing else
in the module has to care which one was uploaded. Two things are worth knowing:

* The newer project file carries no font name and no class prefix — IcoMoon
  names an unnamed project "Untitled" — so both are taken from the library's own
  machine name, as `icon-<id>` and `icon-<id>-`.
* When a package contains both a font and an SVG sprite, the font wins. The
  loose `svg/` directory, the demo pages and the project file itself are
  discarded once the icon definitions have been generated.

Multicolor (duotone) glyphs are only supported in the classic layout. The newer
project file stores a single code point per glyph, so it cannot express the
layered glyphs the classic `properties.codes` array described; use the SVG
sprite for a multicolor set.


RENDERING: FONT OR INLINE SVG
-----------------------------

Each icon library renders its icons one of two ways, set per library on its
edit form (`render` in its configuration):

* **Font** (`render: font`) — the package's own glyphs. The library attaches
  the package's full `style.css`, and with it the whole font. A sprite package
  renders its `<use>` reference.
* **Inline SVG** (`render: svg`) — each icon is rendered with its own paths:

  ```html
  <i class="neo-icon neo-icon-svg icon-regular-chevron-left" aria-hidden="true">
    <svg viewBox="0 0 640 1024" width="0.625em" height="1em" fill="currentColor">…</svg>
  </i>
  ```

  A page carries only the icons it shows. The SVG is 1em tall and as wide as
  the glyph was, sits on the baseline the glyph did, and fills with the
  current colour, so `color`, `font-size`, opacity and transforms such as
  `animate-spin` behave as they did. A path with a fill or opacity of its own
  (multicolour and duotone glyphs) keeps it.

The libraries the module ships render as inline SVG, so a new site starts on
it. Libraries a site already had were set to font by the update that added the
setting, and stay font until switched. In both modes the fonts use
`font-display: swap`.

The paths come from a compact store written beside the package when it is
imported (`svg/` in the library directory, sharded so a page reads a few small
files), never from `selection.json`. A library imported before the store
existed has it built when it is switched to inline SVG. An icon the store has
no paths for renders as its font glyph and attaches the full stylesheet.

### What inline SVG changes

* **CSS-only icons.** A library rendering inline SVG drops its `style.css` and
  attaches an `@font-face`-only sheet (`font-face.css`). The Tailwind `icon-*`
  and `icon-<library>-*` utilities, and `@apply icon-*` in a theme's CSS, keep
  working: the font is still declared, and a browser only downloads it on a
  page where one of them shows.
* **Icon markup written by hand shows nothing.** An `<i class="icon-regular-…">`
  built as a string — in a theme's PHP, a preprocess, a template or script —
  relied on the per-icon rules in `style.css`, which are gone. Render the icon
  through the module instead: `icon()` in Twig, `IconTrait::icon()` /
  `neo_icon()` in PHP, or the `neo_icon` render element. Script that needs an
  icon should take it rendered from the server (a `<template>`, a data
  attribute, drupalSettings) rather than build the class.
* **Rendered icons concatenated into a string** are XSS-filtered by Drupal,
  which strips `<svg>`. Put the icon element in `#markup` whole, or as a render
  array child, rather than `'<div>' . $icon . '</div>'`. Likewise a form
  button's `#value` is plain text: an icon there cannot render as SVG.
* **Styling the wrapper.** `.neo-icon` and the `icon-<library>-<name>` class
  stay on the `<i>`, so rules aimed at them still apply; the SVG is centred in
  it. A rule that sizes or pads `.neo-icon` as a box (a round badge, a fixed
  height) is worth checking by eye, and a height workaround written for the
  font glyph may no longer be needed.
* **Pseudo-element content** written against a library's own class
  (`content: '\e918'` for a prefix no installed library has) is unaffected,
  since it never came from this module.

The icon browser keeps showing font glyphs in either mode, so its payload does
not grow with every icon's paths; it attaches the library's full stylesheet
itself.


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/node/1897420 for further information.


DRUSH COMMANDS
-----

`neo:icon:list [search]` (alias `neoi-list`) — search the available icon names
for the `icon()` Twig function (and the `IconTrait::icon()` helper) so you don't
have to guess. An optional search term filters by substring; `--limit` caps the
number of results (default 50). Supports `--format=json` for machine parsing.

```bash
# List icons (capped at --limit).
drush neo:icon:list

# Find icon names containing "arrow".
drush neo:icon:list arrow

# Widen the result cap.
drush neo:icon:list chevron --limit=100
```


USAGE WITHIN SCSS
-----

A PHP trait has been provided that can be a dropin replacement for the Drupal
core StringTranslationTrait.

```php
use Drupal\neo_icon\IconTrait;

// Dynamic icon using icon repository.
$this->icon('Angel');
// Specific icon by name using weighted library match.
$this->icon('Angel', 'drupal');
// Specific icon by name from specific library (if published). If library does
// not exist or is not published, a weighted library match will be used.
$this->icon('Angel', 'drupal', 'solid');
// Supplying a prefix will filter the dynamic icon repository lookup to only
// those definitions that support that prefix. It allows, for example, an icon
// to be scoped to only be available in the admin.
$this->icon('Angel', NULL, NULL, ['admin']);
// If you want to get an get a dynamic icon regardless of the prefix, you can
// pass 'any' as the prefix.
$this->icon('Angel', NULL, NULL, ['all']);
```


USAGE WITHIN TAILWIND
-----

Global icon sets can utilitized within Neo Build.

```scss
<div class="before:icon-drupal after:icon-drupal">
```


ICON DEFINITIONS
-----

Dynamic icons can be defined via a `MODULE_NAME.neo.icon.yml` file.

```yaml
# A definition that will match any string starting with "drupal".
drupal.start:
  start: icon
  icon: drupal
# A definition that will match any string ending with "drupal".
drupal.end:
  end: icon
  icon: drupal
# A definition that will match any string containing the word "drupal".
drupal.word:
  word: icon
  icon: drupal
# A definition that will match the exact string "drupal".
drupal.exact:
  exact: icon
  icon: drupal
# A weight can be added to the definition to control their lookup order.
drupal.weight:
  exact: icon
  icon: drupal
  weight: 10
# A definition with a prefix. Prefix can also be an array of strings.
drupal.prefix:
  start: icon
  icon: drupal
  prefix: admin
```
