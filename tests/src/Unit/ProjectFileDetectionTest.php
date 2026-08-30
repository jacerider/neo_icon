<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\neo_icon\IcoMoon\ProjectNormalizer;
use PHPUnit\Framework\Attributes\Group;

/**
 * Specifies how a newer layout package is told apart from a classic one.
 *
 * Detection is the entry point of layout normalization: it answers "is this a
 * newer layout package?" by globbing the extracted directory for a project
 * file. It used to answer that question with a static method reaching the
 * global container for the file system service — the same service the object
 * already holds — which put the whole class behind a container bootstrap this
 * module cannot cheaply supply, because its info file pulls a dependency graph
 * that cycles back through neo.
 *
 * These tests run as a pure unit test with no container at all, so they are
 * the pin on that: detection resolves its directory through the file system it
 * was constructed with, or it does not run here.
 */
#[Group('neo_icon')]
final class ProjectFileDetectionTest extends UnitTestCase {

  use FileSystemMockTrait;
  use IcoMoonPackageBuilderTrait;

  /**
   * It detects the project file of a newer layout package.
   *
   * Acceptance criterion: *it detects the project file of a newer layout
   * package.*
   *
   * The base name is the exporter's, not the module's, so the fixture uses one
   * that is nothing like the icon id: detection is a glob for the
   * `.icomoon.json` suffix and nothing more. What comes back is the path built
   * from the directory the object was constructed with, which is what the
   * caller goes on to read.
   */
  public function testItDetectsTheProjectFileOfTheNewerLayout(): void {
    $package = $this->buildIcoMoonPackage(projectName: 'Some Project');

    $normalizer = new ProjectNormalizer($package, 'icon-example', $this->actingFileSystem());

    $this->assertSame($package . '/Some Project.icomoon.json', $normalizer->detect());
  }

  /**
   * It detects nothing without a project file, or without a directory.
   *
   * Acceptance criterion: *it detects nothing when the directory carries no
   * project file or cannot be resolved.*
   *
   * A classic layout package is the case that matters: its selection.json is
   * not a project file, and answering otherwise would send a package that is
   * already in the shape everything downstream expects through the rewrite. A
   * directory that does not resolve at all is the other answer detection has
   * to have, because the caller extracts before it asks.
   */
  public function testItDetectsNothingWithoutTheProjectFileOrTheDirectory(): void {
    $classic = $this->temporaryDirectory();
    file_put_contents($classic . '/selection.json', '{}');
    $classicNormalizer = new ProjectNormalizer($classic, 'icon-example', $this->actingFileSystem());

    $this->assertNull($classicNormalizer->detect());

    $missing = $this->temporaryDirectory() . '/never-extracted';
    $missingNormalizer = new ProjectNormalizer($missing, 'icon-example', $this->actingFileSystem());

    $this->assertNull($missingNormalizer->detect());
  }

  /**
   * It resolves the package directory through the file system it was given.
   *
   * Acceptance criterion: *it resolves the package directory through its
   * injected file system rather than the container.*
   *
   * Asserted as indirection rather than as absence, because absence is not
   * observable: the injected service is pointed at a second package whose
   * project file has a different base name, so the answer can only carry that
   * name if the glob ran over the directory this file system resolved. A
   * detection reaching past the object for a file system of its own resolves
   * the package directory instead and answers the other name — and, in a test
   * with no container to reach, does not get that far at all.
   */
  public function testItResolvesThePackageDirectoryThroughItsInjectedFileSystem(): void {
    $package = $this->buildIcoMoonPackage(projectName: 'alpha');
    $elsewhere = $this->buildIcoMoonPackage(projectName: 'beta');
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->expects($this->once())
      ->method('realpath')
      ->with($package)
      ->willReturn(realpath($elsewhere));

    $normalizer = new ProjectNormalizer($package, 'icon-example', $fileSystem);

    $this->assertSame($package . '/beta.icomoon.json', $normalizer->detect());
  }

}
