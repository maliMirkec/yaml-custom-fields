<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends JSON export data as a file download
 */
class JsonDownload {
  /**
   * Output headers and JSON body (caller exits afterwards)
   *
   * @param array $data Export data
   * @param string $filename Download file name
   */
  public static function send($data, $filename) {
    nocache_headers();
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode($data, JSON_PRETTY_PRINT);
  }
}
