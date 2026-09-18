<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\neo_icon\Entity\IconLibrary;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies how a classic IcoMoon stylesheet is renamed to its library.
 *
 * A classic package (selection.json and style.css at the root) names its font
 * and classes after the IcoMoon project, "icomoon" and "icon-" by default. The
 * library renames both to its icon id, so a library "fa" draws `.icon-fa-home`
 * in the `icon-fa` font, which is the class neo_icon renders for the glyph.
 */
#[Group('neo_icon')]
final class ClassicStylesheetRewriteTest extends UnitTestCase {

  /**
   * An IcoMoon stylesheet as a classic export writes it.
   */
  private const STYLESHEET = <<<'CSS'
@font-face {
  font-family: 'icomoon';
  src: url('fonts/icomoon.ttf?x7k2p9') format('truetype'),
    url('fonts/icomoon.woff?x7k2p9') format('woff');
}
[class^="icon-"], [class*=" icon-"] {
  font-family: 'icomoon' !important;
}
.icon-home:before {
  content: "\f015";
}
.icon-facebook:before {
  content: "\f09a";
}
.icon-fax:before {
  content: "\f1ac";
}
.icon-fa:before {
  content: "\f2b4";
}
CSS;

  /**
   * Every glyph gets the library's class, including ones named like the id.
   *
   * A glyph whose name starts with the library id ("facebook", "fax" and "fa"
   * itself in a library "fa") used to lose the id from its class, so its rule
   * no longer matched the class neo_icon renders and the glyph drew nothing.
   */
  public function testRenamesEveryGlyphClassIncludingThoseStartingWithTheLibraryId(): void {
    $css = IconLibrary::rewriteStylesheet(self::STYLESHEET, 'icon-', 'icomoon', 'icon-fa', 'fa');

    foreach (['home', 'facebook', 'fax', 'fa'] as $glyph) {
      $this->assertStringContainsString('.icon-fa-' . $glyph . ':before', $css, $glyph);
    }
    $this->assertStringNotContainsString('.icon-facebook:before', $css);
    $this->assertStringNotContainsString('.icon-fax:before', $css);
    $this->assertStringContainsString('[class^="icon-fa-"], [class*=" icon-fa-"]', $css);
    $this->assertStringContainsString("font-family: 'icon-fa' !important;", $css);
  }

  /**
   * A class that already carried the library id is not doubled.
   */
  public function testCollapsesALibraryIdTheClassesAlreadyCarried(): void {
    $css = IconLibrary::rewriteStylesheet(".icon-fa-home:before {\n  content: \"\\f015\";\n}\n", 'icon-', 'icomoon', 'icon-fa', 'fa');

    $this->assertStringContainsString('.icon-fa-home:before', $css);
    $this->assertStringNotContainsString('icon-fa-fa-', $css);
  }

  /**
   * Font URLs lose IcoMoon's query string and carry a version token instead.
   */
  public function testReplacesTheFontQueryStringWithAVersionToken(): void {
    $css = IconLibrary::rewriteStylesheet(self::STYLESHEET, 'icon-', 'icomoon', 'icon-fa', 'fa');

    $this->assertStringNotContainsString('x7k2p9', $css);
    $this->assertMatchesRegularExpression("/url\\('fonts\\/icon-fa\\.ttf\\?v=[0-9a-f]{32}'\\)/", $css);
    $this->assertMatchesRegularExpression("/url\\('fonts\\/icon-fa\\.woff\\?v=[0-9a-f]{32}'\\)/", $css);
  }

}
