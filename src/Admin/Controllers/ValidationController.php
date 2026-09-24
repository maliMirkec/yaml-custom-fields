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
  private $schemaLocator;

  public function __construct($attachmentValidator, $postRepository = null, $schemaLocator = null) {
    $this->attachmentValidator = $attachmentValidator;
    $this->postRepository = $postRepository ?: new \YamlCF\Data\Repositories\PostDataRepository();
    $this->schemaLocator = $schemaLocator ?: new \YamlCF\Schema\PostSchemaLocator(
      new \YamlCF\Template\TemplateResolver(),
      new \YamlCF\Schema\SchemaParser()
    );
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

      // Worked out from the post's template, so an edited schema takes effect at
      // once. This used to read a copy frozen into the post when it was saved,
      // which meant old posts were checked against an out-of-date field list.
      $fields = $this->schemaLocator->fieldsForPost($post);

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
