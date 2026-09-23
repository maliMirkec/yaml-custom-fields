<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Build the export file for the items chosen in the wizard
 * Action: wp_ajax_yaml_cf_export_bundle
 *
 * The file is returned as JSON and saved by the browser, the same way the
 * per-post export already works.
 */
class ExportBundle extends AjaxHandler {
  private $exporter;

  public function __construct($exporter) {
    $this->exporter = $exporter;
  }

  public function handle() {
    $this->verifyRequest();

    // Nonce verified in verifyRequest()
    $selection = isset($_POST['selection']) && is_array($_POST['selection'])
      ? array_map('sanitize_text_field', wp_unslash($_POST['selection']))
      : [];

    $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids'])
      ? array_filter(array_map('absint', wp_unslash($_POST['post_ids'])))
      : [];

    if (empty($selection) && empty($post_ids)) {
      $this->sendError(__('Choose at least one thing to export.', 'yaml-custom-fields'));
    }

    $this->sendSuccess($this->exporter->export($selection, $post_ids, $this->postText('match_by', 'slug')));
  }
}
