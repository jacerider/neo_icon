CONTENTS OF THIS FILE
---------------------

 * Introduction
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
