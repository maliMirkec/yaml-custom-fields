<?php
/**
 * Nonce Verifier
 * Helper for WordPress nonce verification
 */

namespace YamlCF\Form;

class NonceVerifier {
  /**
   * Verify nonce
   *
   * @param string $nonce Nonce value
   * @param string $action Nonce action
   * @return bool True if valid
   */
  public static function verify($nonce, $action) {
    return wp_verify_nonce($nonce, $action);
  }

  /**
   * Verify AJAX nonce
   *
   * @param string $nonce Nonce value
   * @param string $action Nonce action
   * @return void Dies if invalid
   */
  public static function verifyAjax($nonce, $action) {
    check_ajax_referer($action, $nonce);
  }
}
