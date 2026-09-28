<?php

/**
 * Post Schema Locator
 * Work out which schema applies to a post
 */

namespace YamlCF\Schema;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Finds the schema a post's fields were described by.
 *
 * Until 2.0.0 a full copy of the parsed schema was written into every post as
 * _yaml_cf_schema on every save. It was only ever read in two places, it could
 * be worked out from the post's template at any time, and it went stale the
 * moment the schema was edited, so the Content Check screen kept checking old
 * posts against a frozen copy. It is worked out here instead.
 *
 * The stored copy is still read as a fallback, for posts saved by an older
 * version on a site where the one-off cleanup has not run yet.
 */
class PostSchemaLocator {
  private $templateResolver;
  private $schemaParser;

  /**
   * Parsed schemas, keyed by the YAML they were parsed from
   *
   * Keyed by content rather than by template on purpose: a schema saved during
   * this request has to be picked up by the next read, and content as the key
   * means an edited schema can never come back from here as its old self.
   *
   * @var array<string, array|null>
   */
  private $parsed = [];

  private $normalizer;

  public function __construct($templateResolver, $schemaParser, $normalizer = null) {
    $this->templateResolver = $templateResolver;
    $this->schemaParser = $schemaParser;
    $this->normalizer = $normalizer ?: new FieldNormalizer();
  }

  /**
   * The schema that applies to a post right now
   *
   * @param \WP_Post $post Post to look at
   * @return array|null Parsed schema, or null when the post's template has none
   */
  public function forPost($post) {
    if (!($post instanceof \WP_Post)) {
      return null;
    }

    $template = $this->templateResolver->getTemplateForPost($post);
    $schema = $this->forTemplate($template);

    if ($schema !== null) {
      return $schema;
    }

    // Saved by a version that kept a copy, on a site the cleanup has not reached
    $stored = get_post_meta($post->ID, '_yaml_cf_schema', true);

    return is_array($stored) ? $stored : null;
  }

  /**
   * The parsed schema stored for a template
   *
   * @param string $template Template file name or block template slug
   * @return array|null Parsed schema, or null when there is none
   */
  public function forTemplate($template) {
    $schemas = get_option('yaml_cf_schemas', []);
    $yaml = isset($schemas[$template]) && is_string($schemas[$template]) ? $schemas[$template] : '';

    if (trim($yaml) === '') {
      return null;
    }

    $key = md5($yaml);
    if (!array_key_exists($key, $this->parsed)) {
      $parsed = $this->schemaParser->parse($yaml);

      // Expand shorthand here, so that every caller sees the same shape the
      // editor renders: "- info:" and "- section:" become fields with a name.
      if (is_array($parsed) && isset($parsed['fields']) && is_array($parsed['fields'])) {
        $parsed['fields'] = $this->normalizer->normalizeAll($parsed['fields']);
      }

      $this->parsed[$key] = is_array($parsed) ? $parsed : null;
    }

    return $this->parsed[$key];
  }
}
