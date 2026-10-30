# Changelog

## Icons rendered for script, once per icon

**`neo_icon.markup_map` renders icons for script to show.** Script that builds
`<i class="icon-…">` from a selector draws nothing once the icon's library
renders inline SVG. Code that hands icons to script — a JSON endpoint's results,
drupalSettings — can now give the service the icons or their selectors and ship
what it answers: the rendered markup keyed by selector, each icon once however
many items use it, with the libraries' cache tags collected for whatever caches
it. See "Icons for script" in the README.

- New: `IconMarkupMapInterface` and the `neo_icon.markup_map` service.
- Saving a library resets what `IconLibraryStorage::loadAvailable()` answers, so
  a library switched to inline SVG earlier in a request is answered as switched.

## Icon libraries can render as inline SVG

**Each icon library now renders its icons as a font or as inline SVG, set per
library.** In inline SVG, an icon is its own paths inside the usual wrapper,
`<i class="neo-icon neo-icon-svg icon-…"><svg …></svg></i>`, so a page carries
only the icons it shows instead of a whole font and a stylesheet with a rule
per icon (the regular set's are 1.4MB and 280KB). The SVG takes the glyph's
width and baseline and fills with the current colour, so `color`, `font-size`,
opacity and `animate-spin` behave as before; multicolour and duotone paths keep
their own fill and opacity.

- **Existing libraries stay font.** An update writes `render: font` on every
  library a site has; export the configuration after `drush updb`. Switch a
  library on its edit form, or set `render: svg` in its configuration.
- **The libraries the module ships render as inline SVG**, so new sites start
  on it.
- **CSS-only icons keep working.** A library rendering inline SVG drops its
  `style.css` and attaches an `@font-face`-only `font-face.css`, so the
  Tailwind `icon-*` utilities still have their font, which a browser only
  downloads where one shows.
- **Hand-written icon markup does not.** Anything that builds
  `<i class="icon-…">` itself, or concatenates a rendered icon into a string
  Drupal XSS-filters, shows nothing for an inline SVG library. The README lists
  what to look for before switching a library.
- **Fonts swap in.** Icon fonts use `font-display: swap` instead of `block`, so
  text no longer waits for them. The update rewrites existing libraries'
  stylesheets; new imports get it directly.
- Path data comes from a compact store written at import beside the package
  (`svg/`), built on switching for a library imported earlier. An icon it has
  no paths for falls back to its font glyph.
- The icon browser still shows font glyphs, so its payload does not grow.
- A newer-layout package keeps its glyphs' path data, read from its loose SVGs
  before they are discarded.
- **Inline SVG icons stay inline wherever the sheet loads.** `icon-inline.css`
  declares the layer order Tailwind's output uses before its own `components`
  rules. Loaded ahead of a theme's compiled CSS (as on admin pages), it
  otherwise ranked `components` below `base`, and preflight's
  `svg { display: block }` broke an icon onto its own line inside buttons.
- New: `IconLibraryInterface::getRender()`, `setRender()`, `isInlineSvg()`,
  `getRenderType()`, `getSvgData()`, `getFontFaceStylesheet()`,
  `getFontLibraryName()`; `IconInterface::getRenderType()`,
  `withoutInlineSvg()`; the `neo_icon/icon-inline` library.

## A rendered icon carries its library's cache tag

**Re-uploading an icon package now refreshes cached markup holding its icons.**
A rendered icon carried no cacheability, so a page, block or component cached
with an icon kept the old glyph after its library's package was replaced, until
a full cache clear. Every icon rendered through `neo_icon` or
`neo_icon_element` now bubbles its library's config cache tag, which saving the
library invalidates, and re-uploading a package saves it.

A library unpacked during a request also answers its new icons for the rest of
that request; it answered none until the next one.

## Glyphs named like their library keep their icon

**A classic IcoMoon package no longer loses glyphs whose names start with the
library id.** Renaming the stylesheet to the library collapsed every
`icon-<id>-<id>` into `icon-<id>`, meant for classes that already carried the
id, but it also caught glyph names beginning with it: in a library `fa`,
`.icon-fa-facebook` became `.icon-facebook`, so facebook, fax, fast-forward and
the rest drew nothing. Only a doubled id followed by a dash is collapsed now.
Existing libraries pick the fix up the next time their package is unpacked.
