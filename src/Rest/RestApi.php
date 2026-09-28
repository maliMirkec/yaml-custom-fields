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
 * **Reading the last two needs a logged-in user who can edit posts, unless an
 * administrator makes them public** on the plugin's Settings screen. That is how
 * WordPress treats data it did not create itself: custom post types and
 * registered meta stay out of the API until somebody chooses to publish them.
 * Not everything in site details or a collection is shown on the website (an
 * internal email address, a tracking ID in a code field, items no template
 * lists), and the collections route would hand out every item.
 *
 * Code can decide either way with the `yaml_cf_rest_public_read` filter, which
 * wins over the setting:
 *
 *     add_filter('yaml_cf_rest_public_read', '__return_true');
 */
class RestApi {
  const NAMESPACE_V1 = 'yaml-cf/v1';

  /** The field added to each public post type */
  const POST_FIELD = 'yaml_cf';

  /** Most items one request may ask for */
  const MAX_PER_PAGE = 100;

  /**
   * Whether site details and collections can be read without logging in
   *
   * Stored as '1' or '0'. Missing means no: publishing is a choice.
   */
  const PUBLIC_READ_OPTION = 'yaml_cf_rest_public_read';

  /**
   * The choice made on the plugin's Settings screen
   *
   * @return bool
   */
  public static function publicReadEnabled() {
    return (string) get_option(self::PUBLIC_READ_OPTION, '0') === '1';
  }

  private $postRepository;
  private $dataObjectRepository;
  private $schemaLocator;
  private $resolver;
  private $siteDetails;

  /**
   * @param \YamlCF\Data\Repositories\PostDataRepository $postRepository Post field storage
   * @param \YamlCF\Data\Repositories\DataObjectRepository $dataObjectRepository Collections
   * @param \YamlCF\Schema\PostSchemaLocator $schemaLocator Finds a post's schema
   * @param \YamlCF\PublicAPI\FieldResolver|null $resolver Works out a post's values
   * @param \YamlCF\PublicAPI\GlobalFieldAccessor|null $siteDetails Reads site details
   */
  public function __construct($postRepository, $dataObjectRepository, $schemaLocator, $resolver = null, $siteDetails = null) {
    $plugin = \YamlCF\Core\Plugin::getInstance();
    $this->postRepository = $postRepository;
    $this->dataObjectRepository = $dataObjectRepository;
    $this->schemaLocator = $schemaLocator;
    $this->resolver = $resolver ?: $plugin->get('field_resolver');
    $this->siteDetails = $siteDetails ?: $plugin->get('global_field_accessor');
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
   * request may see the post before a field callback runs. A password-protected
   * post is the exception. WordPress still returns it, with its content blanked,
   * so the fields are blanked the same way unless the request could see the
   * content too.
   *
   * @param array $post Post as the REST API represents it
   * @param string $field_name Name of this field (unused)
   * @param \WP_REST_Request|null $request The request
   * @return array
   */
  public function readPostFields($post, $field_name = '', $request = null) {
    if (!isset($post['id'])) {
      return [];
    }

    $wp_post = get_post((int) $post['id']);
    if ($wp_post && !$this->mayReadProtectedContent($wp_post, $request)) {
      return [];
    }

    $values = $this->resolver->resolvePostFields((int) $post['id']);

    // A template that uses site details fills names in from them, and those
    // would reach anyone who can read the page, although the site details route
    // itself is closed to them. They follow that route's rule here too.
    if (!$this->mayRead()) {
      foreach ($this->resolver->siteDetailNames((int) $post['id']) as $name) {
        unset($values[$name]);
      }
    }

    return $values;
  }

  /**
   * Whether this request may see what a password protects
   *
   * The same rule WordPress's posts endpoint applies to the content: anyone who
   * can edit the post, or a request that sends the right password.
   *
   * @param \WP_Post $post The post
   * @param \WP_REST_Request|null $request The request
   * @return bool
   */
  private function mayReadProtectedContent($post, $request) {
    if ((string) $post->post_password === '') {
      return true;
    }

    if (current_user_can('edit_post', $post->ID)) {
      return true;
    }

    $password = $request instanceof \WP_REST_Request ? $request->get_param('password') : null;

    return is_string($password) && $password !== '' && hash_equals((string) $post->post_password, $password);
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

    // What a GET returns is resolved: shared values and site details are mixed
    // in. A client that edits one field and sends the whole object back would
    // otherwise copy every one of those into the page, where it would then
    // shadow later changes to the shared value or the site details. So only
    // the page's own fields can be written, and a field sent back exactly as a
    // GET reported it is left as it is.
    $value = array_intersect_key($value, array_flip($this->writableFieldNames($schema)));
    $resolved = $this->resolver->resolvePostFields($post->ID);
    foreach ($value as $name => $sent_value) {
      if (array_key_exists($name, $resolved) && $resolved[$name] === $sent_value) {
        unset($value[$name]);
      }
    }

    $sent = \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer')->sanitize($value, $schema);

    // A request changes the fields it names and leaves the rest alone. The
    // editor's form is different: it renders every field, so it can post every
    // field, and anything missing from it really has been cleared. Over HTTP a
    // caller sends the one field it cares about, and treating the others as
    // deleted would empty a page on a partial update.
    // array_replace(), not array_merge(): a field named with digits only has an
    // integer key, which array_merge() would renumber onto a different field.
    $data = array_replace($this->postRepository->getData($post->ID), $sent);

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
   * Top-level names a request may write for a post
   *
   * Section breaks and info boxes hold no value, and the reserved names are the
   * plugin's own bookkeeping.
   *
   * @param array|null $schema The post's parsed schema
   * @return string[]
   */
  private function writableFieldNames($schema) {
    if (!is_array($schema) || empty($schema['fields']) || !is_array($schema['fields'])) {
      return [];
    }

    $names = [];
    foreach ($schema['fields'] as $field) {
      if (!is_array($field) || !isset($field['name']) || !is_scalar($field['name'])) {
        continue;
      }

      if (isset($field['type']) && in_array($field['type'], ['section', 'info'], true)) {
        continue;
      }

      $name = (string) $field['name'];
      if (!in_array($name, \YamlCF\Schema\SchemaValidator::RESERVED_NAMES, true)) {
        $names[] = $name;
      }
    }

    return $names;
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
     * Defaults to the choice on the plugin's Settings screen, which is off. When
     * reading is not public, it needs a logged-in user who can edit posts.
     *
     * @param bool $public Whether reading is public.
     */
    if (apply_filters('yaml_cf_rest_public_read', self::publicReadEnabled())) {
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
    return rest_ensure_response($this->siteDetails->getFields());
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
      $found = $this->dataObjectRepository->searchEntries($slug, $search, $per_page, ($page - 1) * $per_page);
      $items = $found['entries'];
      $total = $found['total'];
      $total_pages = max(1, (int) ceil($total / $per_page));
    } else {
      $result = $this->dataObjectRepository->getEntryPage($slug, $page, $per_page);
      $items = $result['entries'];
      $total = $result['total'];
      $total_pages = $result['total_pages'];
      $page = $result['paged'];
    }

    // Decoded the way the template functions decode them
    $accessor = \YamlCF\Core\Plugin::getInstance()->get('data_object_accessor');
    foreach ($items as $entry_id => $entry) {
      $items[$entry_id] = $accessor->decodeEntry($slug, $entry);
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
