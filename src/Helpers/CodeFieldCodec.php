<?php

/**
 * Code Field Codec
 * Reads the base64 form code field values are stored in
 */

namespace YamlCF\Helpers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Form\DataSanitizer;

/**
 * A code field is stored as a marker followed by base64, so that the HTML,
 * CSS or script in it survives WordPress's own filtering of post meta. The
 * editor has to show the code, not the stored form.
 */
class CodeFieldCodec {
  /**
   * Decode a code field value that may be base64 encoded
   *
   * Values without the marker are returned as they are: they were saved before
   * code fields were encoded, or they are not code at all.
   *
   * @param mixed $value The stored value
   * @return mixed The decoded value, '' for null, anything else unchanged
   */
  public static function decode($value) {
    if (empty($value) || !is_string($value)) {
      return $value === null ? '' : $value;
    }

    if (strpos($value, DataSanitizer::CODE_FIELD_MARKER) === 0) {
      $decoded = base64_decode(substr($value, strlen(DataSanitizer::CODE_FIELD_MARKER)), true);
      if ($decoded !== false) {
        return $decoded;
      }
    }

    return $value;
  }

  /**
   * Decode a value only if it carries the code field marker
   *
   * What the template functions hand a theme. Unlike decode(), anything that
   * is not a non-empty string comes back exactly as it went in, null included.
   *
   * @param mixed $value The stored value
   * @return mixed The decoded value, or the value unchanged
   */
  public static function maybeDecode($value) {
    if (empty($value) || !is_string($value)) {
      return $value;
    }

    return self::decode($value);
  }
}
