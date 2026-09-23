<?php

namespace YamlCF\Ajax\Handlers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use YamlCF\Ajax\AjaxHandler;

/**
 * Return the page data export for selected posts as JSON
 * Action: wp_ajax_yaml_cf_export_page_data
 */
class ExportPageData extends AjaxHandler {
  private $exporter;

  public function __construct($exporter) {
    $this->exporter = $exporter;
  }

  public function handle() {
    $this->verifyRequest();

    // Nonce verified in verifyRequest()
    $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_map('absint', wp_unslash($_POST['post_ids'])) : [];
    $post_ids = array_filter($post_ids);
    if (empty($post_ids)) {
      $this->sendError(__('Choose at least one post.', 'yaml-custom-fields'));
    }

    $this->sendSuccess($this->exporter->exportMultiple($post_ids, $this->postText('match_by', 'slug')));
  }
}
