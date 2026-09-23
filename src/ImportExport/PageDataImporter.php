<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports per-post field data
 *
 * Imported values are always sanitized against this site's schema for the target
 * post's template; any schema inside the import file is ignored.
 */
class PageDataImporter {
  private $sanitizer;
  private $attachmentValidator;
  private $parser;
  private $templateResolver;
  private $cacheManager;

  public function __construct($sanitizer = null, $attachmentValidator = null, $parser = null, $templateResolver = null, $cacheManager = null) {
    $this->sanitizer = $sanitizer ?: new \YamlCF\Form\DataSanitizer();
    $this->attachmentValidator = $attachmentValidator ?: new \YamlCF\Form\AttachmentValidator();
    $this->parser = $parser ?: new \YamlCF\Schema\SchemaParser();
    $this->templateResolver = $templateResolver ?: new \YamlCF\Template\TemplateResolver();
    $this->cacheManager = $cacheManager ?: new \YamlCF\Cache\CacheManager();
  }

  /**
   * Validate the structure of a page data import
   *
   * @param mixed $data Decoded import file
   * @return array ['valid' => bool, 'message' => string]
   */
  public function validate($data) {
    if (!is_array($data) || !isset($data['plugin']) || $data['plugin'] !== 'yaml-custom-fields') {
      return ['valid' => false, 'message' => __('This is not a YAML Custom Fields export file.', 'yaml-custom-fields')];
    }
    $is_single = isset($data['type']) && $data['type'] === 'single-post' && isset($data['post']);
    if (!$is_single && (!isset($data['posts']) || !is_array($data['posts']))) {
      return ['valid' => false, 'message' => __('The file contains no posts.', 'yaml-custom-fields')];
    }
    return ['valid' => true, 'message' => ''];
  }

  /**
   * Find the entry for a specific post in an import file (single-post or multi-post format)
   *
   * @param array $data Decoded import file
   * @param \WP_Post $post Target post
   * @return array ['entry' => array|null, 'error' => string] Error codes: 'no_match', 'no_data'
   */
  public function findEntryForPost($data, $post) {
    $entry = null;

    if (isset($data['type']) && $data['type'] === 'single-post' && isset($data['post'])) {
      $entry = $data['post'];
    } elseif (isset($data['posts']) && is_array($data['posts'])) {
      foreach ($data['posts'] as $candidate) {
        if (!is_array($candidate)) {
          continue;
        }
        if ((isset($candidate['id']) && (int) $candidate['id'] === (int) $post->ID)
          || (isset($candidate['slug']) && $candidate['slug'] === $post->post_name)) {
          $entry = $candidate;
          break;
        }
      }
      // No silent fallback to another post's data
      if ($entry === null) {
        return ['entry' => null, 'error' => 'no_match'];
      }
    }

    if (!$this->isValidEntry($entry)) {
      return ['entry' => null, 'error' => 'no_data'];
    }

    return ['entry' => $entry, 'error' => ''];
  }

  /**
   * Import one entry into a post
   *
   * @param \WP_Post $post Target post
   * @param array $entry Import entry with a 'data' array
   * @return array ['data' => array, 'data_updated' => bool, 'schema_included' => bool, 'original_fields' => int]
   */
  public function importEntry($post, $entry) {
    $schema = $this->getSchemaForPost($post);
    $data = $this->attachmentValidator->validateAndClean($entry['data'], $schema);
    $data = $this->sanitizer->sanitize($data, $schema);

    // update_post_meta() unslashes its value; slash first so backslashes in field values survive
    $data_updated = update_post_meta($post->ID, '_yaml_cf_data', wp_slash($data));
    update_post_meta($post->ID, '_yaml_cf_imported', true);
    if ($schema) {
      update_post_meta($post->ID, '_yaml_cf_schema', wp_slash($schema));
    }

    \YamlCF\Cache\PostTracker::track($post->ID);
    $this->cacheManager->clearDataCaches($post->ID);

    return [
      'data' => $data,
      'data_updated' => (bool) $data_updated,
      'schema_included' => (bool) $schema,
      'original_fields' => count($entry['data']),
    ];
  }

  /**
   * Import every entry of an import file, matching posts by slug or ID
   *
   * Posts the current user cannot edit are skipped.
   *
   * @param array $data Decoded import file
   * @return array Result with 'success', 'message', 'imported', 'skipped', 'errors', 'debug'
   */
  public function importMany($data) {
    $validation = $this->validate($data);
    if (!$validation['valid']) {
      return ['success' => false, 'message' => $validation['message']];
    }

    if (isset($data['type']) && $data['type'] === 'single-post' && isset($data['post'])) {
      $entries = [$data['post']];
      $match_by = 'slug';
    } else {
      $entries = $data['posts'];
      $match_by = isset($data['match_by']) && $data['match_by'] === 'id' ? 'id' : 'slug';
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $debug = [];

    foreach ($entries as $entry) {
      if (!$this->isValidEntry($entry)) {
        $skipped++;
        $errors[] = __('Skipped a post with no field content.', 'yaml-custom-fields');
        continue;
      }

      $source_id = isset($entry['id']) ? absint($entry['id']) : 0;
      $source_slug = isset($entry['slug']) && is_string($entry['slug']) ? sanitize_title($entry['slug']) : '';
      $source_type = isset($entry['type']) && is_string($entry['type']) ? sanitize_key($entry['type']) : 'post';
      $source_title = isset($entry['title']) && is_string($entry['title']) ? sanitize_text_field($entry['title']) : '';

      $target_post = $this->findTargetPost($match_by, $source_id, $source_slug, $source_type);

      if (!$target_post || !current_user_can('edit_post', $target_post->ID)) {
        $skipped++;
        /* translators: 1: post title, 2: post slug, 3: post ID */
        $errors[] = sprintf(__('No matching post for "%1$s" (web address: %2$s, ID: %3$d).', 'yaml-custom-fields'), $source_title, $source_slug, $source_id);
        $debug[] = [
          'action' => 'skipped',
          'reason' => 'post_not_found',
          'search_by' => $match_by,
          'search_value' => $match_by === 'id' ? $source_id : $source_slug,
        ];
        continue;
      }

      $result = $this->importEntry($target_post, $entry);

      $debug[] = [
        'action' => 'imported',
        'post_id' => $target_post->ID,
        'post_title' => $target_post->post_title,
        'original_data_fields' => $result['original_fields'],
        'cleaned_data_fields' => count($result['data']),
        'data_updated' => $result['data_updated'],
        'schema_included' => $result['schema_included'],
      ];
      $imported++;
    }

    $this->cacheManager->clearDataCaches();

    return [
      'success' => true,
      /* translators: 1: number of imported posts, 2: number of skipped posts */
      'message' => sprintf(__('Import done. %1$d posts updated, %2$d skipped.', 'yaml-custom-fields'), $imported, $skipped),
      'imported' => $imported,
      'skipped' => $skipped,
      'errors' => $errors,
      'debug' => $debug,
      'imported_from' => isset($data['site_url']) && is_string($data['site_url']) ? esc_url_raw($data['site_url']) : 'unknown',
      'exported_at' => isset($data['exported_at']) && is_string($data['exported_at']) ? sanitize_text_field($data['exported_at']) : 'unknown',
    ];
  }

  /**
   * Get the parsed schema this site uses for a post's template
   *
   * @param \WP_Post $post Post
   * @return array|null Parsed schema or null
   */
  public function getSchemaForPost($post) {
    $schemas = get_option('yaml_cf_schemas', []);
    $template = $this->templateResolver->getTemplateForPost($post);
    if (!is_array($schemas) || empty($schemas[$template]) || !is_string($schemas[$template])) {
      return null;
    }
    $schema = $this->parser->parse($schemas[$template]);
    return is_array($schema) ? $schema : null;
  }

  /**
   * Check an import entry has a data array
   *
   * @param mixed $entry Import entry
   * @return bool
   */
  private function isValidEntry($entry) {
    return is_array($entry) && isset($entry['data']) && is_array($entry['data']);
  }

  /**
   * Find the post an import entry refers to
   *
   * @param string $match_by 'id' or 'slug'
   * @param int $source_id Post ID from the file
   * @param string $source_slug Post slug from the file
   * @param string $source_type Post type from the file
   * @return \WP_Post|null Target post
   */
  private function findTargetPost($match_by, $source_id, $source_slug, $source_type) {
    if ($match_by === 'id') {
      return $source_id ? get_post($source_id) : null;
    }
    if ($source_slug === '') {
      return null;
    }
    $posts = get_posts([
      'name' => $source_slug,
      'post_type' => $source_type,
      'post_status' => 'any',
      'posts_per_page' => 1,
    ]);
    return !empty($posts) ? $posts[0] : null;
  }
}
