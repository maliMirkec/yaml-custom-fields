<?php

namespace YamlCF\Admin\Controllers;

use YamlCF\Helpers\RequestHelper;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for data validation page
 * Handles: attachment validation, missing attachment detection
 */
class ValidationController extends AdminController {
  private $attachmentValidator;
  private $validationResults = null;
  private $totalPosts = 0;
  private $postsWithIssues = 0;
  private $totalMissingAttachments = 0;

  public function __construct($attachmentValidator) {
    $this->attachmentValidator = $attachmentValidator;
  }

  /**
   * Handle validation processing
   * This runs on admin_init, before any output
   */
  public function handleValidation() {
    // Only run on the validation page
    if (RequestHelper::getParam('page') !== 'yaml-cf-data-validation') {
      return;
    }

    // Get all posts with custom field data and validate them
    // Try to get from cache first
    $cache_key = 'yaml_cf_validation_posts';
    $posts = wp_cache_get($cache_key, 'yaml-custom-fields');

    if (false === $posts) {
      // Query all posts without meta_key to avoid slow query warnings
      // Filter in PHP using metadata_exists() which uses WordPress's cached get_post_meta()
      $all_posts = get_posts([
        'post_type' => ['page', 'post'],
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => ['post_type' => 'ASC', 'title' => 'ASC'],
        'fields' => 'all',
        'no_found_rows' => true,
        'update_post_term_cache' => false,
      ]);

      // Filter to only include posts with both required meta keys
      // This is faster than meta_query because WordPress caches post meta
      $posts = [];
      foreach ($all_posts as $post) {
        // Check if post has both required meta keys
        if (metadata_exists('post', $post->ID, '_yaml_cf_imported') &&
            metadata_exists('post', $post->ID, '_yaml_cf_data')) {
          $posts[] = $post;
        }
      }

      // Cache for 5 minutes
      wp_cache_set($cache_key, $posts, 'yaml-custom-fields', 300);
    }

    $this->validationResults = [];
    $this->totalPosts = count($posts);
    $this->postsWithIssues = 0;
    $this->totalMissingAttachments = 0;

    foreach ($posts as $post) {
      $data = get_post_meta($post->ID, '_yaml_cf_data', true);
      if (empty($data)) {
        continue;
      }

      // Get the schema for this post
      $schema = get_post_meta($post->ID, '_yaml_cf_schema', true);

      $missing_attachments = $this->attachmentValidator->validateAttachments($data, '', $schema);

      if (!empty($missing_attachments)) {
        $this->postsWithIssues++;
        $this->totalMissingAttachments += count($missing_attachments);
      }

      $this->validationResults[] = [
        'post' => $post,
        'missing_attachments' => $missing_attachments
      ];
    }
  }

  /**
   * Render data validation page
   */
  public function render() {
    $this->checkPermission();
    $this->loadTemplate('data-validation-page.php', [
      'validation_results' => $this->validationResults,
      'total_posts' => $this->totalPosts,
      'posts_with_issues' => $this->postsWithIssues,
      'total_missing_attachments' => $this->totalMissingAttachments,
    ]);
  }
}
