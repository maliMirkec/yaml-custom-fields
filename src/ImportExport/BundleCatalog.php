<?php

namespace YamlCF\ImportExport;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lists what an export or an import file holds, item by item
 *
 * Both sides of the screen render the same item shape: the export wizard lists
 * what this site can send, the import preview lists what a file brings and
 * whether it would land on something that is already here.
 *
 * Item shape:
 *   id     string  Stable key used to select the item ("schema:page.php")
 *   group  string  'setup' | 'posts' | 'lists'
 *   label  string  What to show
 *   meta   string  Second line: field counts, web address, item counts
 *   exists bool    Only set for a file: this site already has it
 */
class BundleCatalog {
  /**
   * Schemas bigger than this are listed without being parsed
   *
   * The preview only needs a field count, and a file from elsewhere should not
   * get to spend the whole request inside the YAML parser.
   */
  const MAX_PARSE_BYTES = 131072;

  private $parser;
  private $nameFormatter;

  public function __construct($parser = null, $nameFormatter = null) {
    $this->parser = $parser ?: new \YamlCF\Schema\SchemaParser();
    $this->nameFormatter = $nameFormatter ?: new \YamlCF\Template\TemplateNameFormatter();
  }

  /**
   * List what this site can export
   *
   * Posts are left out: they are chosen from their own table, which pages and
   * filters separately.
   *
   * @return array ['setup' => group[], 'lists' => item[]]
   */
  public function fromSite() {
    return [
      'setup' => $this->setupGroups($this->siteSettings()),
      'lists' => $this->listItems($this->siteTypes()),
    ];
  }

  /**
   * List what an import file holds, flagging what already exists here
   *
   * @param array $data Decoded import file
   * @return array ['setup' => group[], 'posts' => item[], 'lists' => item[]]
   */
  public function fromBundle($data) {
    $settings = (isset($data['settings']) && is_array($data['settings'])) ? $data['settings'] : [];

    $types = [];
    if (isset($data['types']) && is_array($data['types'])) {
      $types = $data['types'];
    } elseif (isset($data['data_objects']['types']) && is_array($data['data_objects']['types'])) {
      $types = $data['data_objects']['types'];
    }

    $here = $this->siteSettings();
    $here_types = $this->siteTypes();

    $setup = $this->setupGroups($settings);
    foreach ($setup as &$group) {
      foreach ($group['children'] as &$child) {
        $child['exists'] = $this->existsInSettings($child['id'], $here);
      }
      unset($child);
    }
    unset($group);

    $lists = $this->listItems($types);
    foreach ($lists as &$item) {
      $slug = substr($item['id'], strlen('list:'));
      $item['exists'] = isset($here_types[$slug]);
    }
    unset($item);

    return [
      'setup' => $setup,
      'posts' => $this->postItems($data),
      'lists' => $lists,
    ];
  }

  /**
   * Build the post items in an import file
   *
   * @param array $data Decoded import file
   * @return array[]
   */
  private function postItems($data) {
    $entries = [];
    if (isset($data['type']) && $data['type'] === 'single-post' && isset($data['post'])) {
      $entries = [$data['post']];
    } elseif (isset($data['posts']) && is_array($data['posts'])) {
      $entries = $data['posts'];
    }

    $match_by = (isset($data['match_by']) && $data['match_by'] === 'id') ? 'id' : 'slug';
    $found = $this->findPosts($match_by, $entries);

    $items = [];
    foreach ($entries as $index => $entry) {
      if (!is_array($entry)) {
        continue;
      }

      $id = isset($entry['id']) ? (int) $entry['id'] : 0;
      $slug = isset($entry['slug']) ? (string) $entry['slug'] : '';
      $title = isset($entry['title']) && $entry['title'] !== '' ? $entry['title'] : $slug;
      $key = $id > 0 ? (string) $id : 'i' . $index;

      $lookup = $this->lookupKey($match_by, $entry);
      $exists = $lookup !== '' && isset($found[$lookup]);

      $items[] = [
        'id' => 'post:' . $key,
        'group' => 'posts',
        'label' => $title !== '' ? $title : __('(no title)', 'yaml-custom-fields'),
        'meta' => $slug !== '' ? '/' . $slug : '',
        'exists' => $exists,
        'missing' => !$exists,
      ];
    }

    return $items;
  }

  /**
   * The key an entry is looked up under: its ID, or its post type and slug
   *
   * Matching follows the importer (sanitize_title() on the slug, sanitize_key()
   * on the type), so the preview says what the import will actually do.
   *
   * @param string $match_by 'id' or 'slug'
   * @param array $entry Import entry
   * @return string Lookup key, empty when the entry cannot be matched at all
   */
  private function lookupKey($match_by, $entry) {
    if ($match_by === 'id') {
      $id = isset($entry['id']) ? absint($entry['id']) : 0;
      return $id > 0 ? 'id:' . $id : '';
    }

    $slug = isset($entry['slug']) && is_string($entry['slug']) ? sanitize_title($entry['slug']) : '';
    if ($slug === '') {
      return '';
    }

    $type = isset($entry['type']) && is_string($entry['type']) ? sanitize_key($entry['type']) : 'post';
    return ($type === '' ? 'post' : $type) . ':' . $slug;
  }

  /**
   * Look up every entry's target in as few queries as possible
   *
   * One query for all the IDs, or one per post type for the slugs. A file
   * holding thousands of entries must not turn into thousands of queries.
   *
   * @param string $match_by 'id' or 'slug'
   * @param array $entries Import entries
   * @return array Lookup keys that exist on this site
   */
  private function findPosts($match_by, $entries) {
    $ids = [];
    $slugs = [];

    foreach ($entries as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      $key = $this->lookupKey($match_by, $entry);
      if ($key === '') {
        continue;
      }
      list($prefix, $value) = explode(':', $key, 2);
      if ($match_by === 'id') {
        $ids[(int) $value] = true;
      } else {
        $slugs[$prefix][$value] = true;
      }
    }

    $found = [];

    if (!empty($ids)) {
      // Every registered type, not 'any': the importer matches an ID with get_post(),
      // which also finds types left out of 'any' such as attachments
      foreach ($this->queryPosts(['post__in' => array_keys($ids), 'post_type' => array_values(get_post_types())]) as $post) {
        $found['id:' . $post->ID] = true;
      }
    }

    foreach ($slugs as $type => $names) {
      foreach ($this->queryPosts(['post_name__in' => array_keys($names), 'post_type' => $type]) as $post) {
        $found[$type . ':' . $post->post_name] = true;
      }
    }

    return $found;
  }

  /**
   * Run one post lookup, asking for no more than the fields this needs
   *
   * @param array $args Query arguments
   * @return \WP_Post[]
   */
  private function queryPosts($args) {
    return get_posts(array_merge([
      'post_status' => 'any',
      'numberposts' => -1,
      'no_found_rows' => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
    ], $args));
  }

  /**
   * Build the setup groups held in a settings array
   *
   * One group per template, holding whichever of its pieces exist: its schema,
   * its shared fields, its shared content, its site-part content. Site details
   * and the on/off switches follow as their own groups. Grouping this way keeps
   * everything belonging to one template together instead of scattering it
   * across a list sorted by kind.
   *
   * @param array $settings Settings array, from this site or from a file
   * @return array[] Groups of ['key', 'label', 'summary', 'children' => item[]]
   */
  private function setupGroups($settings) {
    $schemas = $this->arrayValue($settings, 'schemas');
    $shared_schemas = $this->arrayValue($settings, 'template_global_schemas');
    $shared_data = $this->arrayValue($settings, 'template_global_data');
    $partial_data = $this->arrayValue($settings, 'partial_data');

    // One entry per template, in the order the templates are first seen
    $pieces = [
      ['bucket' => $schemas, 'prefix' => 'schema:', 'label' => __('Schema', 'yaml-custom-fields'), 'short' => __('schema', 'yaml-custom-fields'), 'count' => 'fields'],
      ['bucket' => $shared_schemas, 'prefix' => 'tglobal_schema:', 'label' => __('Shared fields', 'yaml-custom-fields'), 'short' => __('shared fields', 'yaml-custom-fields'), 'count' => 'fields'],
      ['bucket' => $shared_data, 'prefix' => 'tglobal_data:', 'label' => __('Shared content', 'yaml-custom-fields'), 'short' => __('shared content', 'yaml-custom-fields'), 'count' => 'values'],
      ['bucket' => $partial_data, 'prefix' => 'partial_data:', 'label' => __('Content', 'yaml-custom-fields'), 'short' => __('content', 'yaml-custom-fields'), 'count' => 'values'],
    ];

    $groups = [];

    foreach ($pieces as $piece) {
      foreach ($piece['bucket'] as $template => $value) {
        if ($this->isEmptyPiece($value)) {
          continue;
        }

        if (!isset($groups[$template])) {
          $groups[$template] = [
            'key' => 'tpl:' . $template,
            'label' => $this->templateLabel($template),
            'summary' => [],
            'children' => [],
          ];
        }

        $groups[$template]['children'][] = [
          'id' => $piece['prefix'] . $template,
          'group' => 'setup',
          'label' => $piece['label'],
          'meta' => $piece['count'] === 'fields' ? $this->fieldCountLabel($value) : $this->valueCountLabel($value),
        ];
        $groups[$template]['summary'][] = $piece['short'];
      }
    }

    $groups = array_values($groups);
    foreach ($groups as &$group) {
      // With a single piece there is nothing to open, so name what it holds
      // ("29 fields") rather than repeat the kind ("schema")
      $group['summary'] = count($group['children']) === 1
        ? $group['children'][0]['meta']
        : implode(' · ', $group['summary']);
    }
    unset($group);

    // Site details: its schema and its content belong together
    $site_children = [];
    if (isset($settings['global_schema']) && is_string($settings['global_schema']) && trim($settings['global_schema']) !== '') {
      $site_children[] = [
        'id' => 'global_schema',
        'group' => 'setup',
        'label' => __('Schema', 'yaml-custom-fields'),
        'meta' => $this->fieldCountLabel($settings['global_schema']),
      ];
    }
    $global_data = $this->arrayValue($settings, 'global_data');
    if (!empty($global_data)) {
      $site_children[] = [
        'id' => 'global_data',
        'group' => 'setup',
        'label' => __('Content', 'yaml-custom-fields'),
        'meta' => $this->valueCountLabel($global_data),
      ];
    }
    if (!empty($site_children)) {
      $groups[] = [
        'key' => 'site-details',
        'label' => __('Site details', 'yaml-custom-fields'),
        'summary' => count($site_children) === 1
          ? $site_children[0]['meta']
          : __('schema · content', 'yaml-custom-fields'),
        'children' => $site_children,
      ];
    }

    // The on/off switches are one thing, so the group holds a single child
    $template_settings = $this->arrayValue($settings, 'template_settings');
    if (!empty($template_settings)) {
      $on = count(array_filter($template_settings));
      $groups[] = [
        'key' => 'switches',
        'label' => __('Which templates are switched on', 'yaml-custom-fields'),
        /* translators: %d: number of templates that are on */
        'summary' => sprintf(_n('%d template on', '%d templates on', $on, 'yaml-custom-fields'), $on),
        'children' => [[
          'id' => 'template_settings',
          'group' => 'setup',
          'label' => __('Which templates are switched on', 'yaml-custom-fields'),
          'meta' => '',
        ]],
      ];
    }

    return $groups;
  }

  /**
   * Check whether a stored piece holds nothing worth exporting
   *
   * A template whose schema was never written keeps an empty string in the
   * option; listing it would only add a blank row.
   *
   * @param mixed $value Stored schema or data
   * @return bool
   */
  private function isEmptyPiece($value) {
    if (is_string($value)) {
      return trim($value) === '';
    }
    if (is_array($value)) {
      return empty($value);
    }
    return $value === null;
  }

  /**
   * Build the collection items
   *
   * @param array $types Data object types, from this site or from a file
   * @return array[]
   */
  private function listItems($types) {
    $items = [];

    foreach ($types as $slug => $type) {
      if (!is_array($type)) {
        continue;
      }

      if (isset($type['entries']) && is_array($type['entries'])) {
        $count = count($type['entries']);
      } else {
        $count = (new \YamlCF\Data\Repositories\DataObjectRepository())->countEntries($slug);
      }

      $items[] = [
        'id' => 'list:' . $slug,
        'group' => 'lists',
        'label' => isset($type['name']) && $type['name'] !== '' ? $type['name'] : $slug,
        /* translators: %d: number of items in the list */
        'meta' => sprintf(_n('%d item', '%d items', $count, 'yaml-custom-fields'), $count),
      ];
    }

    return $items;
  }

  /**
   * Check whether this site already holds the thing an item id points at
   *
   * @param string $id Item id
   * @param array $here This site's settings
   * @return bool
   */
  private function existsInSettings($id, $here) {
    $map = [
      'schema:' => 'schemas',
      'tglobal_schema:' => 'template_global_schemas',
      'tglobal_data:' => 'template_global_data',
      'partial_data:' => 'partial_data',
    ];

    foreach ($map as $prefix => $key) {
      if (strpos($id, $prefix) === 0) {
        $template = substr($id, strlen($prefix));
        $bucket = $this->arrayValue($here, $key);
        return isset($bucket[$template]) && !empty($bucket[$template]);
      }
    }

    if ($id === 'global_schema') {
      return isset($here['global_schema']) && $here['global_schema'] !== '';
    }
    if ($id === 'global_data') {
      return !empty($this->arrayValue($here, 'global_data'));
    }
    if ($id === 'template_settings') {
      return !empty($this->arrayValue($here, 'template_settings'));
    }

    return false;
  }

  /**
   * This site's settings, in the same shape an export file uses
   *
   * @return array
   */
  private function siteSettings() {
    return [
      'template_settings' => get_option('yaml_cf_template_settings', []),
      'schemas' => get_option('yaml_cf_schemas', []),
      'partial_data' => get_option('yaml_cf_partial_data', []),
      'template_global_schemas' => get_option('yaml_cf_template_global_schemas', []),
      'template_global_data' => get_option('yaml_cf_template_global_data', []),
      'global_schema' => get_option('yaml_cf_global_schema', ''),
      'global_data' => get_option('yaml_cf_global_data', []),
    ];
  }

  /**
   * This site's data object types
   *
   * @return array
   */
  private function siteTypes() {
    $types = get_option('yaml_cf_data_object_types', []);
    return is_array($types) ? $types : [];
  }

  /**
   * Read an array value, tolerating anything else stored under the key
   *
   * @param array $source Source array
   * @param string $key Key to read
   * @return array
   */
  private function arrayValue($source, $key) {
    return (isset($source[$key]) && is_array($source[$key])) ? $source[$key] : [];
  }

  /**
   * Human name for a template file
   *
   * @param string $template Template file name
   * @return string
   */
  private function templateLabel($template) {
    $name = $this->nameFormatter->format($template);
    return $name !== '' ? $name : $template;
  }

  /**
   * "6 fields" for a schema, empty when it cannot be read
   *
   * @param mixed $yaml Schema YAML
   * @return string
   */
  private function fieldCountLabel($yaml) {
    if (!is_string($yaml) || trim($yaml) === '') {
      return '';
    }

    if (strlen($yaml) > self::MAX_PARSE_BYTES) {
      return __('schema', 'yaml-custom-fields');
    }

    $parsed = $this->parser->parse($yaml);
    if (!is_array($parsed) || !isset($parsed['fields']) || !is_array($parsed['fields'])) {
      return __('schema', 'yaml-custom-fields');
    }

    $count = count($parsed['fields']);
    /* translators: %d: number of fields */
    return sprintf(_n('%d field', '%d fields', $count, 'yaml-custom-fields'), $count);
  }

  /**
   * "4 values" for a saved data array
   *
   * @param mixed $values Saved values
   * @return string
   */
  private function valueCountLabel($values) {
    $count = is_array($values) ? count($values) : 0;
    /* translators: %d: number of saved values */
    return sprintf(_n('%d value', '%d values', $count, 'yaml-custom-fields'), $count);
  }
}
