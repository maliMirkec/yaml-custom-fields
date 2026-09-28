<?php
/**
 * Unit: every message the plugin shows is the same notice
 */

namespace YamlCF\Tests\Unit;

use YamlCF\Tests\TestCase;

/**
 * The plugin used to have several ways of saying something: floating toasts
 * that faded away, WordPress notices with and without icons, coloured text next
 * to a button, callout boxes and a tooltip. They looked different, sat in
 * different places, and the toasts disappeared before a screen reader user
 * could reach them.
 *
 * Now there is one notice (see the Notices section of admin-assets/admin.css,
 * NotificationManager::renderNotice() and YamlCF.notify() in admin.js). This
 * reads the source, so a new message written the old way fails here.
 */
class NoticeConsistencyTest extends TestCase {
  /**
   * Every PHP file that prints markup
   *
   * @return string[] Paths
   */
  private function markupFiles() {
    $root = dirname(__DIR__, 2);
    $files = glob($root . '/templates/*.php');
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src'));
    foreach ($iterator as $file) {
      if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
        $files[] = $file->getPathname();
      }
    }

    return $files;
  }

  public function testEveryNoticeIsThePluginsNotice() {
    foreach ($this->markupFiles() as $path) {
      $source = file_get_contents($path);
      if (!preg_match_all('/class=\\\\?["\']([^"\']*\bnotice notice-[a-z]+[^"\']*)\\\\?["\']/', $source, $matches)) {
        continue;
      }

      foreach ($matches[1] as $classes) {
        $this->assertStringContainsString('yaml-cf-notice', $classes, basename($path) . ' prints a notice without the yaml-cf-notice class: ' . $classes);
      }
    }
  }

  public function testNoNoticeCarriesALiveRegionRole() {
    // Markup on the page when it loads is not announced by a live region; the
    // script announces notices with wp.a11y.speak() instead, once.
    $source = file_get_contents(dirname(__DIR__, 2) . '/src/Core/NotificationManager.php');

    $this->assertStringNotContainsString('role=', $source);
  }

  public function testTheOldWaysAreGone() {
    $root = dirname(__DIR__, 2);
    $scripts = implode("\n", array_map('file_get_contents', glob($root . '/admin-assets/*.js')));
    $styles = file_get_contents($root . '/admin-assets/admin.css');

    foreach (['showMessage', 'hideMessage', 'yaml-cf-toast', 'yaml-cf-notifications', 'yaml-cf-snippet-tooltip', 'yaml-cf-message-'] as $old) {
      $this->assertStringNotContainsString($old, $scripts, 'admin-assets/*.js still uses ' . $old);
      $this->assertStringNotContainsString($old, $styles, 'admin.css still styles ' . $old);
    }

    foreach ($this->markupFiles() as $path) {
      $this->assertStringNotContainsString('yaml-cf-callout', file_get_contents($path), basename($path) . ' still prints a callout');
    }
  }

  /**
   * After a page has loaded, a message never goes in above what is being read:
   * that pushes the whole screen down under the pointer. An action on the page
   * reports in its status slot (YamlCF.status); a notice built by the script
   * goes inline, under the button it is about.
   */
  public function testScriptsNeverInsertANoticeAtTheTopOfThePage() {
    foreach (glob(dirname(__DIR__, 2) . '/admin-assets/*.js') as $path) {
      $source = file_get_contents($path);
      $offset = 0;

      while (($start = strpos($source, 'YamlCF.notify(', $offset)) !== false) {
        $end = strpos($source, ');', $start);
        $call = substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
        $this->assertStringContainsString('target', $call, basename($path) . ' shows a notice at the top of the page: ' . $call);
        $offset = $start + 1;
      }
    }
  }
}
