<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports plugin settings (schemas, template settings, etc.)
 *
 * SECURITY: this class performs no nonce or capability checks of its own -
 * it has no access to the request. Whatever caller wires this up to an
 * AJAX/admin-post action MUST verify check_ajax_referer()/wp_verify_nonce()
 * and current_user_can('manage_options') before calling import().
 */
class SettingsImporter {
  /**
   * Validate import data format
   *
   * @param array $data Import data
   * @return array ['valid' => bool, 'message' => string]
   */
  public function validate($data) {
    // TODO: Phase 14 - Implement validation
    if (!$data || !isset($data['plugin']) || $data['plugin'] !== 'yaml-custom-fields') {
      return ['valid' => false, 'message' => 'Invalid import file format'];
    }

    if (!isset($data['type']) || $data['type'] !== 'settings') {
      return ['valid' => false, 'message' => 'Invalid import file type'];
    }

    return ['valid' => true, 'message' => ''];
  }

  /**
   * Import settings data
   *
   * @param array $data Import data
   * @return array ['success' => bool, 'message' => string]
   */
  public function import($data) {
    // TODO: Phase 14 - Implement import logic
    $validation = $this->validate($data);
    if (!$validation['valid']) {
      return ['success' => false, 'message' => $validation['message']];
    }

    return ['success' => true, 'message' => 'Settings imported successfully'];
  }
}
