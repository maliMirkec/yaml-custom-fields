<?php

/**
 * REST API
 * Field content over the REST API, for front ends and other software
 */

namespace YamlCF\Rest;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Until now the field content was invisible to anything but a PHP theme: no
 * register_meta(), no routes. A headless front end, a mobile app or another
 * plugin could not read a single value.
 *
 * Three things are exposed:
 *
 *   - a `yaml_cf` field on every public post type, holding that post's resolved
 *     values, readable wherever the post itself is readable and writable by
 *     whoever may edit the post;
 *   - GET /yaml-cf/v1/site-details, the values filled in under Site details;
 *   - GET /yaml-cf/v1/collections/<slug>, a page of a collection's items.
 *
 * **Reading the last two is public by default**, because both are content this
 * plugin exists to render into public pages: an API stricter than the website
 * itself would be inconsistent, and would defeat the point of having one. A site
 * that keeps something private in them can close them with the
 * `yaml_cf_rest_public_read` filter:
 *
 *     add_filter('yaml_cf_rest_public_read', '__return_false');
 *
 * which then requires a logged-in user who can edit posts.
 */
class RestApi {
  const NAMESPACE_V1 = 'yaml-cf/v1';

  /** The field added to each public post type */
  const POST_FIELD = 'yaml_cf';

  /** Most items one request may ask for */
  const MAX_PER_PAGE = 100;

  private $postRepository;
  private $dataObjectRepository;
  private $schemaLocator;

  public function __construct($postRepository, $dataObjectRepository, $schemaLocator) {
    $this->postRepository = $postRepository;
    $this->dataObjectRepository = $dataObjectRepository;
    $this->schemaLocator = $schemaLocator;
  }

  /**
   * Add the field and the routes
   *
   * @return void
   */
  public function register() {
    $this->registerPostField();
    $this->registerRoutes();
  }

  /**
   * A `yaml_cf` field on every public post type
   *
   * @return void
   */
  private function registerPostField() {
    foreach ($this->postRepository->getSupportedPostTypes() as $post_type) {
      register_rest_field($post_type, self::POST_FIELD, [
        'get_callback' => [$this, 'readPostFields'],
        'update_callback' => [$this, 'writePostFields'],
        'schema' => [
          'description' => __('Content filled into this page\'s custom fields.', 'yaml-custom-fields'),
          'type' => 'object',
          'context' => ['view', 'edit'],
          // The shape is whatever the page's schema says, which is different per
          // template and settable by an administrator, so it cannot be declared
          // here. Values are sanitised against that schema on the way in.
          'additionalProperties' => true,
        ],
      ]);
    }
  }

  /**
   * The values of a post, as a theme would see them
   *
   * Readable wherever the post is: WordPress has already decided whether this
   * request may see the post before a field callback runs.
   *
   * @param array $post Post as the REST API represents it
   * @return array
   */
  public function readPostFields($post) {
    if (!isset($post['id'])) {
      return [];
    }

    return yaml_cf_resolve_post_fields((int) $post['id']);
  }

  /**
   * Save values sent for a post
   *
   * Goes through exactly the same sanitising as the editor, deliberately: the
   * plugin's safety rests on schema-aware sanitising at a small number of write
   * points, and a second implementation here would be a second thing to get
   * wrong.
   *
   * @param mixed $value Values sent
   * @param \WP_Post $post Post being written to
   * @return true|\WP_Error
   */
  public function writePostFields($value, $post) {
    if (!is_array($value)) {
      return new \WP_Error(
        'yaml_cf_invalid_fields',
        __('Custom fields must be sent as an object of field names and values.', 'yaml-custom-fields'),
        ['status' => 400]
      );
    }

    if (!($post instanceof \WP_Post) || !current_user_can('edit_post', $post->ID)) {
      return new \WP_Error(
        'yaml_cf_cannot_edit',
        __('You are not allowed to change the custom fields of this page.', 'yaml-custom-fields'),
        ['status' => rest_authorization_required_code()]
      );
    }

    $schema = $this->schemaLocator->forPost($post);

    $plugin = \YAML_Custom_Fields::get_instance();
    $sent = $plugin->sanitize_field_data($value, $schema);

    // A request changes the fields it names and leaves the rest alone. The
    // editor's form is different: it renders every field, so it can post every
    // field, and anything missing from it really has been cleared. Over HTTP a
    // caller sends the one field it cares about, and treating the others as
    // deleted would empty a page on a partial update.
    $data = array_merge($this->postRepository->getData($post->ID), $sent);

    $this->postRepository->saveData($post->ID, $data);

    if (empty($data)) {
      \YamlCF\Cache\PostTracker::untrack($post->ID);
    } else {
      \YamlCF\Cache\PostTracker::track($post->ID);
    }

    \YamlCF\Core\Plugin::getInstance()->get('cache_manager')->clearDataCaches($post->ID);

    return true;
  }

  /**
   * The two read-only routes
   *
   * @return void
   */
  private function registerRoutes() {
    register_rest_route(self::NAMESPACE_V1, '/site-details', [
      'methods' => \WP_REST_Server::READABLE,
      'callback' => [$this, 'readSiteDetails'],
      'permission_callback' => [$this, 'mayRead'],
    ]);

    register_rest_route(self::NAMESPACE_V1, '/collections/(?P<slug>[A-Za-z0-9_-]+)', [
      'methods' => \WP_REST_Server::READABLE,
      'callback' => [$this, 'readCollection'],
      'permission_callback' => [$this, 'mayRead'],
      'args' => [
        'slug' => [
          'type' => 'string',
          'sanitize_callback' => 'sanitize_key',
        ],
        'page' => [
          'type' => 'integer',
          'default' => 1,
          'minimum' => 1,
          'sanitize_callback' => 'absint',
        ],
        'per_page' => [
          'type' => 'integer',
          'default' => 50,
          'minimum' => 1,
          'maximum' => self::MAX_PER_PAGE,
          'sanitize_callback' => 'absint',
        ],
        'search' => [
          'type' => 'string',
          'default' => '',
          'sanitize_callback' => 'sanitize_text_field',
        ],
      ],
    ]);
  }

  /**
   * Whether this request may read site-wide content
   *
   * @return bool
   */
  public function mayRead() {
    /**
     * Whether site details and collections can be read without logging in.
     *
     * Both hold content this plugin renders into public pages, so the default
     * is true. Return false to require a logged-in user who can edit posts.
     *
     * @param bool $public Whether reading is public.
     */
    if (apply_filters('yaml_cf_rest_public_read', true)) {
      return true;
    }

    return current_user_can('edit_posts');
  }

  /**
   * GET /yaml-cf/v1/site-details
   *
   * @return \WP_REST_Response
   */
  public function readSiteDetails() {
    return rest_ensure_response(yaml_cf_get_global_fields());
  }

  /**
   * GET /yaml-cf/v1/collections/<slug>
   *
   * @param \WP_REST_Request $request The request
   * @return \WP_REST_Response|\WP_Error
   */
  public function readCollection($request) {
    $slug = (string) $request['slug'];

    if ($this->dataObjectRepository->getType($slug) === null) {
      return new \WP_Error(
        'yaml_cf_no_such_collection',
        __('There is no collection with that short name.', 'yaml-custom-fields'),
        ['status' => 404]
      );
    }

    // Clamped here as well as declared in the route: a page size is a promise
    // about how much work one request can ask for.
    $per_page = min(max(1, (int) $request['per_page']), self::MAX_PER_PAGE);
    $page = (int) $request['page'];
    $search = (string) $request['search'];

    if ($search !== '') {
      // Matched against the collection's index of names, so a search reads that
      // and the items it is about rather than the whole collection.
      $found = $this->dataObjectRepository->searchEntries($slug, $search, $per_page);
      $items = $found['entries'];
      $total = $found['total'];
      $total_pages = 1;
      $page = 1;
    } else {
      $result = $this->dataObjectRepository->getEntryPage($slug, $page, $per_page);
      $items = $result['entries'];
      $total = $result['total'];
      $total_pages = $result['total_pages'];
      $page = $result['paged'];
    }

    $response = rest_ensure_response([
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
      'total_pages' => $total_pages,
    ]);

    // The headers WordPress's own listing routes send, so existing clients can
    // page through this the way they page through posts
    $response->header('X-WP-Total', (string) $total);
    $response->header('X-WP-TotalPages', (string) $total_pages);

    return $response;
  }
}
