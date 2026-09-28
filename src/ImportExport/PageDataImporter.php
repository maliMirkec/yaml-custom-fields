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
    // Values here come out of an import file, never from this user's own typing
    $this->sanitizer = ($sanitizer ?: new \YamlCF\Form\DataSanitizer())->forUntrustedSource();
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
      // Matched the way the file says it was made to be matched, as importMany()
      // does. Accepting either used to let an ID from another site, where it
      // belongs to some other page, win over the entry with the right address.
      $match_by = isset($data['match_by']) && $data['match_by'] === 'id' ? 'id' : 'slug';

      foreach ($data['posts'] as $candidate) {
        if (!is_array($candidate) || !$this->typeMatches($candidate, $post)) {
          continue;
        }
        $matches = $match_by === 'id'
          ? isset($candidate['id']) && (int) $candidate['id'] === (int) $post->ID
          : $this->addressMatches($candidate, $post);
        if ($matches) {
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

    // An import file carries whatever the site it came from had. Where it says
    // nothing about a field, the value already here is left alone: importing a
    // headline is not a request to throw away content that has nothing to do
    // with the file. That holds for fields this site's schema defines as much
    // as for ones it does not; only restoring the second kind emptied every
    // field a trimmed file, or one from a site with fewer fields, left out.
    // A field the file does name wins, even when it names it empty.
    $stored = get_post_meta($post->ID, '_yaml_cf_data', true);
    $data = \YamlCF\Core\Plugin::getInstance()->get('schema_diff')->restoreStranded(
      $data,
      is_array($stored) ? $stored : [],
      $schema,
      \YamlCF\Schema\SchemaValidator::RESERVED_NAMES
    );
    $data = $this->keepUnmentioned($data, is_array($stored) ? $stored : []);

    // update_post_meta() unslashes its value; slash first so backslashes in field values survive
    $data_updated = update_post_meta($post->ID, '_yaml_cf_data', wp_slash($data));
    update_post_meta($post->ID, '_yaml_cf_imported', true);

    // "Use the shared value on this page", for files that carry it. A file from
    // an older version says nothing either way, so what is here is kept.
    if (isset($entry['use_shared']) && is_array($entry['use_shared'])) {
      $use_shared = [];
      foreach ($entry['use_shared'] as $name => $ticked) {
        if ($ticked === '1' || $ticked === 1 || $ticked === true) {
          $use_shared[sanitize_text_field((string) $name)] = '1';
        }
      }
      update_post_meta($post->ID, '_yaml_cf_use_template_global_fields', wp_slash($use_shared));
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
      // '' when the file does not say, as files from older versions do not
      $source_type = isset($entry['type']) && is_string($entry['type']) ? sanitize_key($entry['type']) : '';
      $source_title = isset($entry['title']) && is_string($entry['title']) ? sanitize_text_field($entry['title']) : '';
      $source_path = isset($entry['path']) && is_string($entry['path']) ? $this->sanitizePath($entry['path']) : '';

      $target_post = $this->findTargetPost($match_by, $source_id, $source_slug, $source_type, $source_path);

      if ($target_post === false) {
        $skipped++;
        /* translators: 1: post title, 2: post slug */
        $errors[] = sprintf(__('Skipped "%1$s": more than one page has the web address "%2$s". Export the file again with this version of the plugin, which records the full address.', 'yaml-custom-fields'), $source_title, $source_slug);
        $debug[] = [
          'action' => 'skipped',
          'reason' => 'ambiguous_slug',
          'search_by' => $match_by,
          'search_value' => $source_slug,
        ];
        continue;
      }

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

    // Each imported post has had its own caches cleared already. Clearing every
    // tracked post again here cost one pass over the whole site per import.

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
   * Keep what is stored for top-level fields the file does not name
   *
   * @param array $imported Sanitised values from the file
   * @param array $stored Values already on the post
   * @return array
   */
  private function keepUnmentioned($imported, $stored) {
    foreach ($stored as $name => $value) {
      if (in_array((string) $name, \YamlCF\Schema\SchemaValidator::RESERVED_NAMES, true)) {
        continue;
      }

      if (!array_key_exists($name, $imported)) {
        $imported[$name] = $value;
      }
    }

    return $imported;
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
   * Whether an entry is for this kind of post
   *
   * Entries without a type come from files written before it was recorded, and
   * are taken as they are.
   *
   * @param array $entry Entry from the file
   * @param \WP_Post $post Post being imported into
   * @return bool
   */
  private function typeMatches($entry, $post) {
    return !isset($entry['type']) || !is_string($entry['type']) || sanitize_key($entry['type']) === $post->post_type;
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
   * Whether a file entry names this post by its address
   *
   * The full path is compared when the file has one, so that an entry for
   * /careers/team is not taken for /about/team.
   *
   * @param array $entry Entry from the file
   * @param \WP_Post $post Post being imported into
   * @return bool
   */
  private function addressMatches($entry, $post) {
    if (isset($entry['path']) && is_string($entry['path']) && $entry['path'] !== '' && is_post_type_hierarchical($post->post_type)) {
      return $this->sanitizePath($entry['path']) === get_page_uri($post);
    }

    return isset($entry['slug']) && is_string($entry['slug']) && $entry['slug'] !== '' && $entry['slug'] === $post->post_name;
  }

  /**
   * Clean a page path from a file: slugs joined by slashes
   *
   * @param string $path Path as written in the file
   * @return string
   */
  private function sanitizePath($path) {
    $parts = array_filter(array_map('sanitize_title', explode('/', $path)), 'strlen');

    return implode('/', $parts);
  }

  /**
   * Find the post an import entry refers to
   *
   * @param string $match_by 'id' or 'slug'
   * @param int $source_id Post ID from the file
   * @param string $source_slug Post slug from the file
   * @param string $source_type Post type from the file
   * @param string $source_path Full path of a hierarchical post, when the file has one
   * @return \WP_Post|null|false Target post, null when there is none, false when the
   *                              slug fits more than one post and nothing tells them apart
   */
  private function findTargetPost($match_by, $source_id, $source_slug, $source_type, $source_path = '') {
    if ($match_by === 'id') {
      $post = $source_id ? get_post($source_id) : null;

      // An ID means nothing on its own in a file from another site: here it can
      // be a menu item, an attachment or a revision. Only a post of the type the
      // file names, and one that can hold fields at all, is written to.
      if (!$post || ($source_type !== '' && $post->post_type !== $source_type)) {
        return null;
      }

      $supported = (new \YamlCF\Data\Repositories\PostDataRepository())->getSupportedPostTypes();

      return in_array($post->post_type, $supported, true) ? $post : null;
    }
    if ($source_path !== '' && $source_type !== '' && is_post_type_hierarchical($source_type)) {
      $post = get_page_by_path($source_path, OBJECT, $source_type);

      return $post instanceof \WP_Post ? $post : null;
    }

    if ($source_slug === '') {
      return null;
    }

    $supported = (new \YamlCF\Data\Repositories\PostDataRepository())->getSupportedPostTypes();

    // Files from before the type was recorded say nothing about it, and those
    // came from pages as often as from posts, so every type that can hold
    // fields is searched.
    $posts = get_posts([
      'name' => $source_slug,
      'post_type' => $source_type !== '' ? $source_type : $supported,
      'post_status' => 'any',
      'posts_per_page' => 2,
      'no_found_rows' => true,
    ]);

    if (count($posts) > 1) {
      return false;
    }

    return !empty($posts) ? $posts[0] : null;
  }
}
