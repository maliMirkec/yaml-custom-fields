<?php
/**
 * Unit: the shipped autoloader knows every class in src/
 */

namespace YamlCF\Tests\Unit;

use YamlCF\Tests\TestCase;

/**
 * The plugin loads build/vendor/scoper-autoload.php at runtime, and that loader
 * is generated with setClassMapAuthoritative(true): the classmap is the only
 * thing consulted, and the PSR-4 rule beside it is never reached. A class added
 * to src/ after the last ./build-scoped.sh therefore does not exist as far as a
 * real site is concerned, and the first code path touching it is a fatal error.
 *
 * No other test can catch that. tests/bootstrap.php loads vendor/autoload.php
 * instead — the development loader, where PSR-4 resolves src/ perfectly well — so
 * the whole suite passes against a build that would die on activation.
 *
 * This compares the two by reading files, never by autoloading, so a stale build
 * fails here rather than in production.
 */
class ScopedAutoloaderTest extends TestCase {
  /**
   * Every class, interface and trait declared under src/, with its file.
   *
   * @return array<string, string> Fully qualified name => path relative to the plugin
   */
  private function declaredInSrc() {
    $root = dirname(dirname(__DIR__));
    $found = [];

    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
      if ($file->getExtension() !== 'php') {
        continue;
      }

      $source = file_get_contents($file->getPathname());

      if (!preg_match('/^\s*namespace\s+([^;]+);/m', $source, $ns)) {
        continue;
      }

      if (!preg_match('/^\s*(?:final\s+|abstract\s+)*(?:class|interface|trait)\s+(\w+)/m', $source, $name)) {
        continue;
      }

      $fqn = trim($ns[1]) . '\\' . $name[1];
      $found[$fqn] = str_replace($root . '/', '', $file->getPathname());
    }

    return $found;
  }

  public function testEveryClassInSrcIsInTheShippedClassmap() {
    $classmap_file = dirname(dirname(__DIR__)) . '/build/vendor/composer/autoload_classmap.php';

    $this->assertFileExists(
      $classmap_file,
      'The scoped build is missing entirely. Run ./build-scoped.sh'
    );

    $classmap = require $classmap_file;
    $declared = $this->declaredInSrc();

    $this->assertNotEmpty($declared, 'Found no classes under src/, so this test is not testing anything.');

    $missing = [];
    foreach ($declared as $fqn => $relative_path) {
      if (!isset($classmap[$fqn])) {
        $missing[$fqn] = $relative_path;
      }
    }

    $this->assertSame(
      [],
      $missing,
      "These classes exist in src/ but not in the shipped classmap, which is authoritative.\n"
        . "A site would fatal on the first one it touched. Run ./build-scoped.sh:\n  "
        . implode("\n  ", array_keys($missing))
    );
  }

  /**
   * The guard above is only meaningful while the loader stays authoritative.
   *
   * If a future build drops setClassMapAuthoritative(), PSR-4 covers src/ again
   * and a stale classmap stops mattering — but so does this test, and it should
   * say so rather than quietly pass.
   */
  public function testTheShippedLoaderIsStillClassmapAuthoritative() {
    $autoload_real = dirname(dirname(__DIR__)) . '/build/vendor/composer/autoload_real.php';

    $this->assertFileExists($autoload_real, 'The scoped build is missing entirely. Run ./build-scoped.sh');

    $this->assertStringContainsString(
      'setClassMapAuthoritative(true)',
      file_get_contents($autoload_real),
      'The shipped loader is no longer classmap-authoritative. If that is deliberate, '
        . 'testEveryClassInSrcIsInTheShippedClassmap is no longer load-bearing and both can go.'
    );
  }
}
