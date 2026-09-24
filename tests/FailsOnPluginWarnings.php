<?php
/**
 * Fail a test on any PHP warning or notice raised by this plugin's own code
 *
 * WordPress's AJAX test case switches E_WARNING off for the duration of every
 * test (see WP_Ajax_UnitTestCase::set_up() in wp-phpunit's testcase-ajax.php):
 *
 *     $this->_error_level = error_reporting();
 *     error_reporting( $this->_error_level & ~E_WARNING );
 *
 * It does that because admin-ajax.php legitimately warns about headers in a test
 * process. The side effect is that a warning raised by the plugin itself — an
 * undefined array key, say — disappears with them, which is the one thing these
 * tests exist to notice.
 *
 * This trait puts an error handler in place that throws for warnings and notices
 * coming from the plugin's own files, and silently drops everything else, so
 * WordPress core's own noise cannot fail a test or corrupt a JSON response.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests;

trait FailsOnPluginWarnings {
  /**
   * Start failing on the plugin's own warnings
   *
   * Call after parent::setUp(), because that is what turns E_WARNING off.
   *
   * @return void
   */
  protected function failOnPluginWarnings() {
    error_reporting(error_reporting() | E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

    set_error_handler(function ($severity, $message, $file, $line) {
      if ($this->isPluginFile($file)) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
      }

      // Not ours: swallow it, so core's header warnings neither fail the test
      // nor end up inside a response the test is about to parse as JSON.
      return true;
    });
  }

  /**
   * Stop failing on warnings
   *
   * @return void
   */
  protected function stopFailingOnPluginWarnings() {
    restore_error_handler();
  }

  /**
   * Whether a file is the plugin's own code, rather than a dependency or a test
   *
   * @param string $file Absolute path the warning came from
   * @return bool
   */
  private function isPluginFile($file) {
    $root = dirname(__DIR__);

    if (strpos($file, $root) !== 0) {
      return false;
    }

    foreach (['/vendor/', '/build/', '/tests/', '/node_modules/'] as $excluded) {
      if (strpos($file, $root . $excluded) === 0) {
        return false;
      }
    }

    return true;
  }
}
