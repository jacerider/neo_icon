# CONTEXT — neo_icon

Terms specific to this module: the icon libraries a site installs, the IcoMoon packages they are
built from, the two layouts those packages arrive in, where they land on disk, and how an icon is
looked up once they are there. General Drupal vocabulary (config entity, render element, Twig
function) does not belong here.

## Icon libraries

**Icon library** — the `neo_icon_library` config entity: one **IcoMoon package**, unpacked into its
own **library directory** and re-labelled with the entity's own font name and class prefix, whose
icons the `icon()` Twig function then resolves by name. _Avoid:_ "icon set", "icon pack", and "font"
as a name for the whole library.

**IcoMoon package** — the zip an **icon library** is built from, delivered to the entity as a config
file and accepted only with a `.zip` extension. Every icon library on a site arrives this way,
including the ones this module ships as installed configuration. _Avoid:_ "the archive", "the icon
zip", "the upload".

**Library directory** — the per-library directory beneath the public files where an **IcoMoon
package** is unpacked and rewritten. It is derived from the library's machine name, and nothing
outside this module writes into it. It is never emptied ahead of an extraction: the zip extractor
in `neo_config_file` unpacks elsewhere and replaces the whole directory once the package is on
disk, so a package that will not open leaves the installed library untouched. _Avoid:_ "the icon
directory", "the extraction path".

**Icon id** — the stem `icon-<machine name>` an **icon library** derives from its own id: the font
family it declares, and, with a trailing hyphen, the class prefix every one of its icons carries. A
**newer layout** package is re-labelled to it on import, because that layout carries no name of its
own. _Avoid:_ "the prefix" unqualified, "the font name", "the selector".

**Icon definition** — one entry in a **library directory**'s `definitions.json`: the icon's id, its
name, the library's class prefix, the integer code point its glyph is addressed by, and the extra
code points a multicolor glyph carries. It is the unit a library hands out, and what an **icon
element** ultimately resolves to. _Avoid:_ "icon data", "the glyph record", "the icon entry".

**Library preparation** — the step that runs over a **library directory** the moment an **IcoMoon
package** is unpacked into it: **layout normalization** first where a **newer layout** arrived,
then the discard of the demo files, the rewrite of the stylesheet and `selection.json` to the
library's own **icon id**, and the writing of the **icon definitions**. _Avoid:_ "the import",
"post-processing", "the rewrite" (that is **layout normalization**, one step inside this).

## Package layouts

**Classic layout** — the on-disk shape everything downstream of import assumes: a `selection.json`,
a `style.css`, `fonts/<name>.<ext>`, and for a sprite set a `symbol-defs.svg` at the root. It is
what IcoMoon's older app exported, and what a **library directory** holds once import has finished
whichever layout arrived. _Avoid:_ "the old format", "the selection.json layout".

**Newer layout** — the shape IcoMoon's current app exports: a **project file** at the root, the font
and its stylesheet under `font/`, loose glyph SVGs under `svg/`, a sprite under `symbol-defs/`. It
carries no font name and no class prefix, and it stores one code point per glyph, so it cannot
express a multicolor glyph. _Avoid:_ "the new format", "the v2 package"; the README calls it the
newer layout and so does this file.

**Project file** — the `<project>.icomoon.json` at the root of a **newer layout** package: the one
file that tells the two layouts apart, and the source of every glyph name and code point the import
reads. Its presence is what detection tests for. _Avoid:_ "the manifest", "the json", and
`selection.json`, which is a different file belonging to the **classic layout**.

**Layout normalization** — the in-place rewrite of a **newer layout** package into the **classic
layout**, performed once on import: fonts moved and renamed to the **icon id**, a sprite's symbol
ids prefixed with it, a stylesheet and a `selection.json` synthesized already carrying the library's
own name and prefix, and everything the module has no use for deleted. Nothing downstream of it
knows which layout arrived. _Avoid:_ "conversion", "migration", "the import" as a name for this step
alone.

**Skipped glyph** — a glyph **layout normalization** drops rather than fails over: one with no
usable name, one with no integer code point, or one repeating a name already taken. They are
collected as a list of reasons the import logs as a warning; a package whose glyphs are *all*
skipped is refused instead. _Avoid:_ "invalid glyph", "bad icon", "error".

## Icon lookup

**Icon element** — the composable object the module's icon helpers hand back: a label, an optional
icon, a position, attributes, and the ability to render itself to markup. It resolves its icon
lazily, on the first ask, and what it resolves may be an **absent icon**. _Avoid:_ "the icon
object" (that is the resolved icon itself), "icon builder", "the icon render array".

**Icon repository** — the service that answers "which icon is this?" for a piece of text or an icon
id, searching the enabled **icon libraries** in order and preferring a named one when it is given.
Both of its lookups may answer an **absent icon**. _Avoid:_ "the icon manager" (that is the plugin
manager over the **icon definitions**), "the entity icon manager" (that is a third thing), "icon
service".

**Absent icon** — the NULL that every step of the lookup answers when no library holds a match: a
library's own definition and instance lookups, the **icon repository**, and an **icon element**'s
resolved icon. It is the ordinary answer, not a failure — an unmatched string is what the `icon()`
Twig function is handed all day, and every caller inside this module and outside it already guards
for it. _Avoid:_ "missing icon", "the null icon", "no icon found".

**Entity icon manager** — the plugin manager that answers which entity types may carry an icon of
their own, and reads, writes and clears the icon stored on one. Its plugins are declared in
`{extension}.neo_icon_entity_types.yml`. _Avoid:_ "the entity type manager" unqualified (core owns
that name), "the icon plugin manager" (that is the manager over the **icon definitions**).

**Icon façade** — one of the four global functions `neo_icon.module` keeps: they answer with an
**icon element** and nothing else, and they are how every installing site reaches this module from
procedural code. Their signatures are frozen — all four have call sites in other packages — so
whatever moves behind them, they keep answering exactly what they answer today. Three build the
element themselves and carry no deprecation, because they *are* the replacement and a tag on them
would have nothing to name; the entity one delegates to the **icon element factory** and carries a
docblock `@deprecated` naming it, which reaches a tool and never an operator — nothing is logged or
shown at runtime, on a path every rendered icon takes. _Avoid:_ "the helpers", "the global
functions", and "the shim", which elsewhere in the stack means a forwarder marked deprecated; three
of these are not, the entity one is.

**Icon element factory** — the service that builds an **icon element** for an entity, taking its
label from the entity type's bundle information where the entity type has bundles and from the
entity itself where it does not. It is what the entity **icon façade** is a façade over, and the
only one of the four that has anything behind it. _Avoid:_ "the entity icon manager" (that reads and
writes the icon stored on an entity), "the icon repository" (that resolves an icon from text).
