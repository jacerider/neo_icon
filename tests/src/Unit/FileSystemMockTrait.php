<?php

declare(strict_types=1);

namespace Drupal\Tests\neo_icon\Unit;

use Drupal\Core\File\FileSystemInterface;

/**
 * Builds a file system double that really performs the work it is asked for.
 *
 * Layout normalization delegates six operations to the injected file system
 * and does the rest — glob(), file_exists(), file_get_contents(),
 * file_put_contents(), md5_file() — against the filesystem directly, so a
 * virtual filesystem would cover only half of it. The double is therefore a
 * mock whose used methods act on a real temporary directory.
 *
 * Every method layout normalization is not supposed to call stays unstubbed,
 * so a call that should never happen does not quietly succeed.
 */
trait FileSystemMockTrait {

  /**
   * Builds a file system whose used methods act on the real filesystem.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The mocked file system.
   */
  private function actingFileSystem(): FileSystemInterface {
    $fileSystem = $this->createMock(FileSystemInterface::class);

    $fileSystem->method('realpath')->willReturnCallback(
      static fn (string $uri): string|false => realpath($uri)
    );

    // The by-reference $directory parameter cannot be written back through a
    // callback, so the directory is created here rather than merely named.
    $fileSystem->method('prepareDirectory')->willReturnCallback(
      static function (string $directory, int $options = FileSystemInterface::MODIFY_PERMISSIONS): bool {
        if (!is_dir($directory) && ($options & FileSystemInterface::CREATE_DIRECTORY)) {
          mkdir($directory, 0777, TRUE);
        }
        return is_dir($directory);
      }
    );

    $fileSystem->method('move')->willReturnCallback(
      static fn (string $source, string $destination): string|false => rename($source, $destination) ? $destination : FALSE
    );

    $fileSystem->method('saveData')->willReturnCallback(
      static fn (string $data, string $destination): string|false => file_put_contents($destination, $data) === FALSE ? FALSE : $destination
    );

    $fileSystem->method('delete')->willReturnCallback(
      static fn (string $path): bool => !file_exists($path) || unlink($path)
    );

    // An absent tree is a no-op, not a warning: a package built without a font
    // is pruned of a font directory it never had, and the PHPUnit
    // configuration these run under fails on warnings.
    $fileSystem->method('deleteRecursive')->willReturnCallback(
      static function (string $path): bool {
        if (!file_exists($path)) {
          return TRUE;
        }
        if (!is_dir($path)) {
          return unlink($path);
        }
        $children = new \RecursiveIteratorIterator(
          new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
          \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($children as $child) {
          $child->isDir() ? rmdir($child->getPathname()) : unlink($child->getPathname());
        }
        return rmdir($path);
      }
    );

    return $fileSystem;
  }

}
