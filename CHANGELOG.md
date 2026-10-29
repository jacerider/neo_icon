# Changelog

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
