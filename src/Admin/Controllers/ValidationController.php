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
  private $schemaDiff;
  private $notifications;

  public function __construct($attachmentValidator, $postRepository = null, $schemaLocator = null, $schemaDiff = null, $notifications = null) {
    $this->attachmentValidator = $attachmentValidator;
    $this->postRepository = $postRepository ?: new \YamlCF\Data\Repositories\PostDataRepository();
    $this->schemaLocator = $schemaLocator ?: new \YamlCF\Schema\PostSchemaLocator(
      new \YamlCF\Template\TemplateResolver(),
      new \YamlCF\Schema\SchemaParser()
    );
    $this->schemaDiff = $schemaDiff ?: new \YamlCF\Schema\SchemaDiff();
    $this->notifications = $notifications ?: \YamlCF\Core\Plugin::getInstance()->get('notification_manager');
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
    // Every page and post holding field data, not only imported ones: a value
    // left behind by a removed field can be anywhere, and this is the screen
    // that reports problems with stored data.
    $query = $this->postRepository->queryPostsWithData([
      'paged' => $paged,
      'per_page' => self::PER_PAGE,
    ]);

    $results = [];
    $posts_with_issues = 0;
    $total_missing = 0;
    $total_orphaned = 0;
    $posts_without_schema = 0;

    foreach ($query['posts'] as $post) {
      $data = get_post_meta($post->ID, '_yaml_cf_data', true);
      if (empty($data) || !is_array($data)) {
        continue;
      }

      // Worked out from the post's template, so an edited schema takes effect at
      // once. This used to read a copy frozen into the post when it was saved,
      // which meant old posts were checked against an out-of-date field list.
      $schema = $this->schemaLocator->forPost($post);
      $fields = is_array($schema) && isset($schema['fields']) && is_array($schema['fields'])
        ? $schema['fields']
        : null;

      $missing = $this->attachmentValidator->validateAttachments($data, '', $fields);
      if (!empty($missing)) {
        $posts_with_issues++;
        $total_missing += count($missing);
      }

      // No schema at all is not the same as "every field is stranded". Without
      // this the screen would list every value on the page and offer to delete
      // the lot, which is the opposite of what it is for.
      $has_schema = $schema !== null;
      $orphaned = $has_schema ? $this->orphanedFieldNames($data, $schema) : [];

      if (!$has_schema) {
        $posts_without_schema++;
      }

      $total_orphaned += count($orphaned);

      $results[] = [
        'post' => $post,
        'missing_attachments' => $missing,
        'orphaned_fields' => $orphaned,
        'has_schema' => $has_schema,
      ];
    }

    return [
      'validation_results' => $results,
      'total_posts' => $query['total'],
      'page_posts' => count($results),
      'posts_with_issues' => $posts_with_issues,
      'total_missing_attachments' => $total_missing,
      'total_orphaned_fields' => $total_orphaned,
      'posts_without_schema' => $posts_without_schema,
      'paged' => $query['paged'],
      'total_pages' => $query['total_pages'],
      'per_page' => self::PER_PAGE,
    ];
  }

  /**
   * The names stored on a post that its schema no longer describes
   *
   * Top-level names only, matching how the values are keyed and how the save
   * path decides what to keep. A name reused inside a group is a different
   * field, so it does not make a stranded top-level value look current.
   *
   * @param array $data Stored field data
   * @param array $schema Parsed schema for the post's template
   * @return string[] Field names with nothing describing them
   */
  private function orphanedFieldNames($data, $schema) {
    $known = array_flip($this->schemaDiff->topLevelFieldNames($schema));
    $orphaned = [];

    foreach (array_keys($data) as $key) {
      $key = (string) $key;

      if (isset($known[$key])) {
        continue;
      }

      // The plugin's own bookkeeping keys are not the writer's content, and
      // removing one changes what a page renders. Not cleanup; not listed here.
      if (in_array($key, \YamlCF\Schema\SchemaValidator::RESERVED_NAMES, true)) {
        continue;
      }

      $orphaned[] = $key;
    }

    return $orphaned;
  }

  /**
   * Render data validation page
   */
  public function render() {
    $this->checkPermission();

    $this->handleRemoval();

    $paged = max(1, RequestHelper::getParamInt('paged', 1));
    $this->loadTemplate('data-validation-page.php', $this->validatePage($paged));
  }

  /**
   * Remove the stranded values listed on the page that was submitted
   *
   * Nothing here trusts the form's own list of names. The page may have been
   * open for an hour, and in that time somebody may have put the field back in
   * the schema — deleting it then would destroy content that is in use again.
   * So the stranded set is worked out afresh for each post, and only names in
   * both lists go.
   *
   * @return void
   */
  private function handleRemoval() {
    if (!isset($_POST['yaml_cf_remove_orphans_nonce'])) {
      return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_remove_orphans_nonce'])), 'yaml_cf_remove_orphans')) {
      wp_die(esc_html__('This link has expired. Go back, reload the page and try again.', 'yaml-custom-fields'));
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $submitted = isset($_POST['yaml_cf_orphans']) && is_array($_POST['yaml_cf_orphans'])
      ? map_deep(wp_unslash($_POST['yaml_cf_orphans']), 'sanitize_text_field')
      : [];

    $posts_changed = 0;
    $fields_removed = 0;

    foreach ($submitted as $post_id => $names) {
      $post_id = (int) $post_id;
      $post = get_post($post_id);

      if (!$post || !is_array($names)) {
        continue;
      }

      // The screen needs manage_options, but removing content from one post is
      // still an edit of that post.
      if (!current_user_can('edit_post', $post_id)) {
        continue;
      }

      $data = $this->postRepository->getData($post_id);
      $schema = $this->schemaLocator->forPost($post);

      if (empty($data) || $schema === null) {
        continue;
      }

      $removable = array_intersect(
        array_map('strval', $names),
        $this->orphanedFieldNames($data, $schema)
      );

      if (empty($removable)) {
        continue;
      }

      foreach ($removable as $name) {
        unset($data[$name]);
        $fields_removed++;
      }

      if (empty($data)) {
        // Nothing left: drop the row rather than store an empty array, or the
        // post keeps turning up on this screen for ever.
        $this->postRepository->deleteData($post_id);
        \YamlCF\Cache\PostTracker::untrack($post_id);
      } else {
        $this->postRepository->saveData($post_id, $data);
      }

      $posts_changed++;
    }

    if ($fields_removed === 0) {
      $this->notifications->info(__('Nothing was removed. The fields may have been put back into a schema since this page was opened.', 'yaml-custom-fields'));
      return;
    }

    $this->notifications->success(sprintf(
      /* translators: 1: number of values removed, 2: number of pages */
      _n(
        '%1$s stored value was removed, from %2$s page.',
        '%1$s stored values were removed, from %2$s pages.',
        $fields_removed,
        'yaml-custom-fields'
      ),
      number_format_i18n($fields_removed),
      number_format_i18n($posts_changed)
    ));
  }
}
