# Changelog

## Glyphs named like their library keep their icon

**A classic IcoMoon package no longer loses glyphs whose names start with the
library id.** Renaming the stylesheet to the library collapsed every
`icon-<id>-<id>` into `icon-<id>`, meant for classes that already carried the
id, but it also caught glyph names beginning with it: in a library `fa`,
`.icon-fa-facebook` became `.icon-facebook`, so facebook, fax, fast-forward and
the rest drew nothing. Only a doubled id followed by a dash is collapsed now.
Existing libraries pick the fix up the next time their package is unpacked.
