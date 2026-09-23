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
 *
 * Imported posts are validated one page at a time, so the page stays fast on large sites.
 */
class ValidationController extends AdminController {
  const PER_PAGE = 50;

  private $attachmentValidator;
  private $postRepository;

  public function __construct($attachmentValidator, $postRepository = null) {
    $this->attachmentValidator = $attachmentValidator;
    $this->postRepository = $postRepository ?: new \YamlCF\Data\Repositories\PostDataRepository();
  }

  /**
   * Kept for backward compatibility with the admin_init hook in the main plugin file.
   * Validation now runs while rendering, for the requested page only.
   */
  public function handleValidation() {
  }

  /**
   * Validate one page of imported posts
   *
   * @param int $paged Page number
   * @return array Template variables
   */
  private function validatePage($paged) {
    $query = $this->postRepository->queryPostsWithData([
      'paged' => $paged,
      'per_page' => self::PER_PAGE,
      'require_imported' => true,
    ]);

    $results = [];
    $posts_with_issues = 0;
    $total_missing = 0;

    foreach ($query['posts'] as $post) {
      $data = get_post_meta($post->ID, '_yaml_cf_data', true);
      if (empty($data)) {
        continue;
      }

      // The stored schema is the full parsed schema; the validator expects its field list
      $schema = get_post_meta($post->ID, '_yaml_cf_schema', true);
      $fields = is_array($schema) && isset($schema['fields']) && is_array($schema['fields']) ? $schema['fields'] : null;

      $missing = $this->attachmentValidator->validateAttachments($data, '', $fields);
      if (!empty($missing)) {
        $posts_with_issues++;
        $total_missing += count($missing);
      }

      $results[] = [
        'post' => $post,
        'missing_attachments' => $missing,
      ];
    }

    return [
      'validation_results' => $results,
      'total_posts' => $query['total'],
      'page_posts' => count($results),
      'posts_with_issues' => $posts_with_issues,
      'total_missing_attachments' => $total_missing,
      'paged' => $query['paged'],
      'total_pages' => $query['total_pages'],
      'per_page' => self::PER_PAGE,
    ];
  }

  /**
   * Render data validation page
   */
  public function render() {
    $this->checkPermission();

    $paged = max(1, RequestHelper::getParamInt('paged', 1));
    $this->loadTemplate('data-validation-page.php', $this->validatePage($paged));
  }
}
