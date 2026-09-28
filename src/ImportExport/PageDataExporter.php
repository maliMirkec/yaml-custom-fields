<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exports per-post field data
 *
 * Formats are unchanged from earlier versions so old export files stay importable:
 * a single post uses 'type' => 'single-post' with a 'post' entry, several posts use a 'posts' list.
 */
class PageDataExporter {
  private $templateResolver;

  private $schemaLocator;

  public function __construct($templateResolver = null, $schemaLocator = null) {
    $this->templateResolver = $templateResolver ?: new \YamlCF\Template\TemplateResolver();
    $this->schemaLocator = $schemaLocator ?: new \YamlCF\Schema\PostSchemaLocator(
      $this->templateResolver,
      new \YamlCF\Schema\SchemaParser()
    );
  }

  /**
   * Build the export entry for one post
   *
   * @param \WP_Post $post Post
   * @return array|null Entry, or null when the post has no field data
   */
  public function buildPostEntry($post) {
    $data = get_post_meta($post->ID, '_yaml_cf_data', true);
    if (empty($data)) {
      return null;
    }

    $entry = [
      'id' => $post->ID,
      'title' => $post->post_title,
      'slug' => $post->post_name,
      'type' => $post->post_type,
      'template' => $this->templateResolver->getTemplateForPost($post),
      'data' => $data,
    ];

    // A child page's slug is only unique under its parent: /about/team and
    // /careers/team are both "team". The full path is what tells them apart.
    if (is_post_type_hierarchical($post->post_type)) {
      $entry['path'] = get_page_uri($post);
    }

    // Which fields show the shared value on this page. Without it an imported
    // page falls back to its own copy of those fields, which may be years old.
    $use_shared = get_post_meta($post->ID, '_yaml_cf_use_template_global_fields', true);
    if (is_array($use_shared) && !empty($use_shared)) {
      $entry['use_shared'] = $use_shared;
    }

    // The schema the post's template uses now, rather than a copy frozen into
    // the post when it was last saved
    $schema = $this->schemaLocator->forPost($post);
    if (!empty($schema)) {
      $entry['schema'] = $schema;
    }

    return $entry;
  }

  /**
   * Export a single post
   *
   * @param int $post_id Post ID
   * @return array|null Export data, or null when the post is missing or has no data
   */
  public function exportPost($post_id) {
    $post = get_post($post_id);
    if (!$post) {
      return null;
    }

    $entry = $this->buildPostEntry($post);
    if (!$entry) {
      return null;
    }

    return array_merge($this->header(), [
      'type' => 'single-post',
      'post' => $entry,
    ]);
  }

  /**
   * Export several posts (posts without data are skipped)
   *
   * @param int[] $post_ids Post IDs
   * @param string $match_by How the import should match posts: 'slug' or 'id'
   * @return array Export data
   */
  public function exportMultiple($post_ids, $match_by = 'slug') {
    $export = array_merge($this->header(), [
      'match_by' => $match_by === 'id' ? 'id' : 'slug',
      'posts' => [],
    ]);

    foreach ($post_ids as $post_id) {
      $post = get_post((int) $post_id);
      if (!$post) {
        continue;
      }
      $entry = $this->buildPostEntry($post);
      if ($entry) {
        $export['posts'][] = $entry;
      }
    }

    return $export;
  }

  /**
   * Send export data as a JSON file download
   *
   * @param array $data Export data
   * @param string $filename Download file name
   */
  public function download($data, $filename) {
    JsonDownload::send($data, $filename);
  }

  /**
   * Common export header
   *
   * @return array Header fields
   */
  private function header() {
    return [
      'plugin' => 'yaml-custom-fields',
      'version' => YAML_CF_VERSION,
      'exported_at' => current_time('mysql'),
      'site_url' => get_site_url(),
    ];
  }
}
