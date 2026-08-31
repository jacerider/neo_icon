<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\IcoMoon\ProjectNormalizer;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies the two stylesheets, and the one rewrite performed on a sprite.
 *
 * A stylesheet is written whichever type a package resolves to, and the reason
 * is structural rather than cosmetic: neo_icon_library_info_build() registers
 * no Drupal library for a set that has no stylesheet, and without that
 * registration a sprite library never pulls in the SVG use polyfill. The two
 * documents share a selector and nothing else. A font library declares a face
 * named for the icon id, lists its sources in the order the class keeps, and
 * carries one content rule per glyph — those per-glyph rules are not
 * decoration, because NeoBuildEventSubscriber emits Tailwind icon utilities
 * only for global libraries and a non-global one has nothing else to set a
 * glyph from. A sprite library declares sizing rules instead.
 *
 * The sprite also has its symbol ids rewritten. The classic layout carried the
 * project's own prefix on every symbol id; the newer layout uses bare glyph
 * names, which would collide the moment a second sprite library is installed,
 * so the icon id is baked into each one here. Icon::getChildren() looks a
 * symbol up by "<prefix><name>", so an unprefixed sprite renders nothing.
 *
 * Every assertion reads a decision out of the written file — the family name,
 * the source order and its format keywords, the version token, the hex of each
 * content rule, the ids on the symbols — never a golden file, which fails on
 * whitespace and names no rule when it breaks.
 */
#[Group('neo_icon')]
final class StylesheetsAndSpriteIdsTest extends UnitTestCase {

  use FileSystemMockTrait;
  use IcoMoonPackageBuilderTrait;

  /**
   * The icon id every package in this class is normalized under.
   */
  private const ICON_ID = 'icon-example';

  /**
   * The glyphs a package carries when a case does not need its own.
   */
  private const GLYPHS = [
    ['name' => 'home', 'code' => 0xE900],
    ['name' => 'user', 'code' => 0xE901],
    ['name' => 'star', 'code' => 0xE902],
  ];

  /**
   * It declares a font face named for the library's icon id.
   *
   * Acceptance criterion: *it declares a font face named for the icon id.*
   *
   * The newer layout ships a style.css of its own declaring a face called
   * "Untitled", the name IcoMoon gives an unnamed project, and that document is
   * discarded rather than rewritten. What replaces it has to agree with three
   * other things or the library renders empty boxes: the font family the
   * synthesized selection.json records, the family the icon rules in this same
   * file resolve against, and the family Tailwind's icon utilities emit for a
   * global library. All three are the icon id, so the face is asserted to carry
   * it and the whole document is checked for the placeholder — a face named
   * right beside a stale rule naming it wrong is still a broken library.
   */
  public function testItDeclaresTheFontFaceNamedForTheIconId(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf'], self::GLYPHS, 'Untitled');

    $this->normalizePackage($package);

    $css = $this->stylesheet($package);

    $this->assertMatchesRegularExpression(
      "/font-family:\s*'" . preg_quote(self::ICON_ID, '/') . "';/",
      $this->fontFace($css),
      'The face is named for the library.'
    );
    $this->assertMatchesRegularExpression(
      "/font-family:\s*'" . preg_quote(self::ICON_ID, '/') . "' !important;/",
      $css,
      'The icon rules resolve against the family the face declares.'
    );
    $this->assertStringNotContainsString('Untitled', $css, 'Nothing IcoMoon called the unnamed project survives.');
  }

  /**
   * It lists the font sources in woff2, woff and truetype order.
   *
   * Acceptance criterion: *it lists the font sources in woff2, woff and
   * truetype order, each carrying a version token.*
   *
   * A browser takes the first source it understands, so the order is the whole
   * of the format negotiation: woff2 first because it is the smallest, the
   * older woff behind it, the ttf last as the fallback. The format keyword
   * beside each url is what tells the browser whether it understands one at all
   * — and it is not the extension, since a .ttf is declared as "truetype" — so
   * the pairs are asserted together rather than as two lists. The version token
   * is the cache buster: without it a site that re-uploads a set keeps serving
   * the browser's copy of the old font, and every glyph that moved renders as
   * the wrong icon. It is derived from the font files themselves rather than
   * from the library's name, which is what makes it change when the bytes do.
   */
  public function testItListsTheFontSourcesInFormatOrderWithVersionTokens(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf']);

    $this->normalizePackage($package);

    $sources = $this->sources($this->stylesheet($package));

    $this->assertSame(
      [
        ['fonts/' . self::ICON_ID . '.woff2', 'woff2'],
        ['fonts/' . self::ICON_ID . '.woff', 'woff'],
        ['fonts/' . self::ICON_ID . '.ttf', 'truetype'],
      ],
      array_map(
        static fn (array $source): array => [explode('?', $source[0])[0], $source[1]],
        $sources
      ),
      'Every source names the file it points at and the format keyword that goes with it, in src order.'
    );

    $tokens = [];
    foreach ($sources as $delta => $source) {
      $matched = preg_match('/\?v=(.+)$/', $source[0], $found);
      $this->assertSame(1, $matched, sprintf('Source %d carries a version token.', $delta));
      $this->assertNotSame('', $found[1], sprintf('Source %d has a version token with something in it.', $delta));
      $tokens[] = $found[1];
    }

    $this->assertCount(1, array_unique($tokens), 'One version token covers the whole face.');
    $this->assertSame(
      md5_file($package . '/fonts/' . self::ICON_ID . '.woff2'),
      $tokens[0],
      'The token is derived from the font files themselves, so it moves when they do.'
    );
    $this->assertNotSame(md5(self::ICON_ID), $tokens[0], 'The token is not the standing fallback derived from the library name alone.');
  }

  /**
   * It writes one content rule per glyph, carrying its hex code point.
   *
   * Acceptance criterion: *it writes one content rule per glyph, carrying that
   * glyph's code point as hex.*
   *
   * These rules are the only thing that sets a glyph in a library that is not
   * global, because Tailwind's icon utilities are emitted for global libraries
   * alone. A code point written as decimal is not a near miss — CSS reads the
   * escape as hex regardless, so 59648 resolves to some entirely different
   * character and the icon renders as whatever glyph happens to live there. The
   * glyphs are chosen so a decimal would be visibly wrong and so the hex spans
   * letters and digits, and the rules are asserted as name-and-code pairs in
   * project order, because three right names and three right code points can
   * still be paired wrongly.
   */
  public function testItWritesOneContentRulePerGlyphCarryingItsHexCodePoint(): void {
    $package = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf'], [
      ['name' => 'home', 'code' => 0xE900],
      ['name' => 'user', 'code' => 0xE9FF],
      ['name' => 'star', 'code' => 0xF00A],
    ]);

    $this->normalizePackage($package);

    $rules = $this->contentRules($this->stylesheet($package));

    $this->assertCount(3, $rules, 'One content rule per glyph, and no rule for a glyph the set does not carry.');
    $this->assertSame(
      [['home', 'e900'], ['user', 'e9ff'], ['star', 'f00a']],
      $rules,
      'Every rule sets its own glyph from its own code point, written as hex.'
    );
  }

  /**
   * It writes sizing rules rather than a font face for a sprite package.
   *
   * Acceptance criterion: *it writes sizing rules instead of a font face for a
   * sprite package.*
   *
   * A sprite has no face to declare and no code point to set, so a stylesheet
   * is written for it only because the absence of one would leave the set
   * unregistered with Drupal and the SVG use polyfill unattached. What it
   * carries instead is the sizing an inline SVG needs to behave like a glyph:
   * a box that follows the font size and paint that follows the text colour.
   * The absence of the font document is asserted as well as the presence of
   * this one, because a sprite library handed the font stylesheet would name a
   * face for a font file that was never shipped.
   */
  public function testItWritesSizingRulesInsteadOfFontFaceForSprites(): void {
    $package = $this->buildIcoMoonPackage(['sprite']);

    $this->normalizePackage($package);

    $css = $this->stylesheet($package);

    $this->assertStringNotContainsString('@font-face', $css, 'A sprite library declares no face.');
    $this->assertStringNotContainsString('content:', $css, 'A sprite library sets no glyph from a code point.');
    $this->assertStringContainsString('[class^="' . self::ICON_ID . '-"]', $css, 'The sizing rules are scoped to the library own classes.');
    foreach ([
      'display:\s*inline-block' => 'The symbol sits in the line like a glyph.',
      'width:\s*1em' => 'The symbol is as wide as the font size.',
      'height:\s*1em' => 'The symbol is as tall as the font size.',
      'fill:\s*currentColor' => 'The symbol is painted in the text colour.',
      'stroke:\s*currentColor' => 'The symbol is stroked in the text colour.',
    ] as $declaration => $message) {
      $this->assertMatchesRegularExpression('/' . $declaration . '/', $css, $message);
    }
  }

  /**
   * It prefixes every symbol id in the sprite with the icon id.
   *
   * Acceptance criterion: *it prefixes every symbol id in the sprite with the
   * icon id.*
   *
   * The classic export carried the project's own prefix on every symbol id and
   * the newer one uses bare glyph names, so two sprite libraries installed side
   * by side would both define a symbol called "home" and whichever loaded last
   * would win for both. Icon::getChildren() references a symbol by
   * "<prefix><name>", so the prefix has to be in the file. Every id is asserted
   * by name in one list, which is what catches a rewrite that prefixed the
   * first symbol only, prefixed one twice, or left a bare id behind. It is
   * asserted again over a package that also ships a font, because the font wins
   * the type and the sprite still ships beside it — an unprefixed sprite there
   * is a collision nobody would think to look for.
   */
  public function testItPrefixesEverySymbolIdInTheSpriteWithTheIconId(): void {
    $expected = [
      self::ICON_ID . '-home',
      self::ICON_ID . '-user',
      self::ICON_ID . '-star',
    ];

    $sprite = $this->buildIcoMoonPackage(['sprite'], self::GLYPHS);

    $this->normalizePackage($sprite);

    $this->assertSame($expected, $this->symbolIds($sprite), 'Every symbol of a sprite library is namespaced by the library icon id.');

    // The font wins the type when a package ships both, and the sprite ships
    // with it — so it is namespaced there too.
    $both = $this->buildIcoMoonPackage(['woff2', 'woff', 'ttf', 'sprite'], self::GLYPHS);

    $this->assertSame('font', $this->normalizePackage($both), 'The package carrying both resolves to the font.');
    $this->assertSame($expected, $this->symbolIds($both), 'The sprite that rode along with a font is namespaced all the same.');
  }

  /**
   * Runs layout normalization over a package the builder wrote.
   *
   * @param string $package
   *   The package directory.
   *
   * @return string
   *   The library type the package resolved to.
   */
  private function normalizePackage(string $package): string {
    $normalizer = new ProjectNormalizer($package, self::ICON_ID, $this->actingFileSystem());
    return $normalizer->normalize((string) $normalizer->detect());
  }

  /**
   * The stylesheet layout normalization wrote for a package.
   *
   * @param string $package
   *   The package directory.
   *
   * @return string
   *   The stylesheet contents.
   */
  private function stylesheet(string $package): string {
    $file_path = $package . '/style.css';
    $this->assertFileExists($file_path, 'Every library is written a stylesheet, or Drupal registers no library at all.');
    return (string) file_get_contents($file_path);
  }

  /**
   * The body of the one font face a stylesheet declares.
   *
   * @param string $css
   *   The stylesheet contents.
   *
   * @return string
   *   Everything between the face braces.
   */
  private function fontFace(string $css): string {
    $found = [];
    preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $found, PREG_SET_ORDER);
    $this->assertCount(1, $found, 'A font library declares exactly one face.');
    return $found[0][1];
  }

  /**
   * The sources a stylesheet font face lists, in the order it lists them.
   *
   * @param string $css
   *   The stylesheet contents.
   *
   * @return array<int, array{0: string, 1: string}>
   *   One [url, format keyword] pair per source.
   */
  private function sources(string $css): array {
    $found = [];
    preg_match_all("/url\('([^']*)'\)\s*format\('([^']*)'\)/", $this->fontFace($css), $found, PREG_SET_ORDER);
    $this->assertNotEmpty($found, 'The face lists a source to load the font from.');
    return array_map(
      static fn (array $source): array => [$source[1], $source[2]],
      $found
    );
  }

  /**
   * The per-glyph content rules a stylesheet carries, in the order written.
   *
   * @param string $css
   *   The stylesheet contents.
   *
   * @return array<int, array{0: string, 1: string}>
   *   One [glyph name, escaped code point] pair per rule.
   */
  private function contentRules(string $css): array {
    $found = [];
    preg_match_all(
      '/\.' . preg_quote(self::ICON_ID, '/') . '-([^\s:{]+):before\s*\{\s*content:\s*"\\\\([^"]*)";\s*\}/',
      $css,
      $found,
      PREG_SET_ORDER
    );
    return array_map(
      static fn (array $rule): array => [$rule[1], $rule[2]],
      $found
    );
  }

  /**
   * The symbol ids the sprite at a library root carries, in document order.
   *
   * @param string $package
   *   The package directory.
   *
   * @return string[]
   *   One id per symbol.
   */
  private function symbolIds(string $package): array {
    $file_path = $package . '/symbol-defs.svg';
    $this->assertFileExists($file_path, 'The sprite is moved up to the library root.');
    $found = [];
    preg_match_all('/<symbol\s[^>]*\bid="([^"]*)"/', (string) file_get_contents($file_path), $found);
    $this->assertNotEmpty($found[1], 'The sprite carries symbols to reference.');
    return $found[1];
  }

}
