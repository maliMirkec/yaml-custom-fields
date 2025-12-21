<?php
namespace YamlCF\Cache;

/**
 * Manages temporary transients for validation errors and import status
 */
class TransientManager {
  /**
   * Store invalid schema temporarily for re-display
   *
   * @param string $type Schema type (schema, global_schema, template_global_schema)
   * @param string $schema_content Invalid schema content
   * @param int $user_id User ID (defaults to current user)
   * @param int $expiration Expiration in seconds (default: 300 = 5 minutes)
   */
  public function storeInvalidSchema($type, $schema_content, $user_id = null, $expiration = 300) {
    if ($user_id === null) {
      $user_id = get_current_user_id();
    }

    $transient_key = 'yaml_cf_invalid_' . $type . '_' . $user_id;
    set_transient($transient_key, $schema_content, $expiration);
  }

  /**
   * Retrieve and delete invalid schema
   *
   * @param string $type Schema type (schema, global_schema, template_global_schema)
   * @param int $user_id User ID (defaults to current user)
   * @return string|false Invalid schema content or false if not found
   */
  public function retrieveInvalidSchema($type, $user_id = null) {
    if ($user_id === null) {
      $user_id = get_current_user_id();
    }

    $transient_key = 'yaml_cf_invalid_' . $type . '_' . $user_id;
    $invalid_schema = get_transient($transient_key);

    if ($invalid_schema !== false) {
      delete_transient($transient_key);
    }

    return $invalid_schema;
  }

  /**
   * Store import error temporarily
   *
   * @param string $error_type Error type identifier
   * @param int $post_id Post ID (optional, for post-specific errors)
   * @param int $user_id User ID (defaults to current user)
   * @param int $expiration Expiration in seconds (default: 60 = 1 minute)
   */
  public function storeImportError($error_type, $post_id = null, $user_id = null, $expiration = 60) {
    if ($user_id === null) {
      $user_id = get_current_user_id();
    }

    $transient_key = 'yaml_cf_import_error_' . $user_id;
    if ($post_id !== null) {
      $transient_key .= '_' . $post_id;
    }

    set_transient($transient_key, $error_type, $expiration);
  }

  /**
   * Retrieve and delete import error
   *
   * @param int $post_id Post ID (optional, for post-specific errors)
   * @param int $user_id User ID (defaults to current user)
   * @return string|false Error type or false if not found
   */
  public function retrieveImportError($post_id = null, $user_id = null) {
    if ($user_id === null) {
      $user_id = get_current_user_id();
    }

    $transient_key = 'yaml_cf_import_error_' . $user_id;
    if ($post_id !== null) {
      $transient_key .= '_' . $post_id;
    }

    $error = get_transient($transient_key);

    if ($error !== false) {
      delete_transient($transient_key);
    }

    return $error;
  }

  /**
   * Clear all plugin transients for a user
   *
   * @param int $user_id User ID (defaults to current user)
   */
  public function clearUserTransients($user_id = null) {
    if ($user_id === null) {
      $user_id = get_current_user_id();
    }

    // Clear known transient types using WordPress API
    $transient_types = [
      'schema',
      'global_schema',
      'template_global_schema'
    ];

    // Delete invalid schema transients
    foreach ($transient_types as $type) {
      delete_transient('yaml_cf_invalid_' . $type . '_' . $user_id);
    }

    // Delete import error transients
    delete_transient('yaml_cf_import_error_' . $user_id);

    // Note: Post-specific import errors (yaml_cf_import_error_{user_id}_{post_id})
    // will expire automatically after 60 seconds as set in storeImportError()
  }
}
