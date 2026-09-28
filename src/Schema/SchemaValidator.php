<?php

/**
 * Schema Validator
 * Validate YAML schema structure and field definitions
 */

namespace YamlCF\Schema;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
use YamlCF\Vendor\Symfony\Component\Yaml\Yaml;
use YamlCF\Vendor\Symfony\Component\Yaml\Exception\ParseException;

class SchemaValidator {
  /**
   * The longest schema that will be accepted, in bytes.
   *
   * A schema is a short list of fields written by hand; a megabyte of it is
   * never that. Symfony's parser builds the whole document in memory and
   * recurses as it goes, so there has to be a limit somewhere.
   */
  const MAX_SCHEMA_BYTES = 262144;

  /**
   * Field names the plugin uses for its own bookkeeping.
   *
   * _template_global_override was written into post data by versions before
   * 2.0.0 to hold per-page overrides of shared fields. It is still read, so a
   * schema must not be able to claim the name.
   */
  const RESERVED_NAMES = ['_template_global_override'];

  /**
   * Every field type the editor can render.
   *
   * This is the same list as the renderers in YamlCF\Rendering\FieldRenderer.
   * A type outside it renders no input at all, so the field would look present in
   * the schema and be missing from the editor; validating here turns that into a
   * message at the moment the schema is saved.
   *
   * 'break' is left out on purpose: FieldNormalizer turns it into 'section'
   * before validation runs.
   */
  const FIELD_TYPES = [
    'block',
    'boolean',
    'code',
    'data_object',
    'date',
    'file',
    'image',
    'info',
    'number',
    'object',
    'post_type',
    'rich-text',
    'section',
    'select',
    'string',
    'taxonomy',
    'text',
  ];

  private $normalizer;

  /**
   * Problems found while not being strict, collected for the caller to report
   *
   * @var string[]
   */
  private $warnings = [];

  public function __construct() {
    $this->normalizer = new FieldNormalizer();
  }

  /**
   * Validate YAML schema
   *
   * @param string $yaml YAML schema string
   * @param string|null $template Template name (for info field validation)
   * @param bool $strict False to report unknown field types as warnings rather
   *                     than refusing the schema. Importing uses this: dropping
   *                     a whole template because one field has a bad type loses
   *                     far more than it protects.
   * @return array Array with 'valid' => bool, 'message' => string, 'warnings' => string[]
   */
  public function validate($yaml, $template = null, $strict = true) {
    $this->warnings = [];

    if (is_string($yaml) && strlen($yaml) > self::MAX_SCHEMA_BYTES) {
      return [
        'valid' => false,
        'message' => sprintf(
          /* translators: %d: size limit in kilobytes */
          __('The schema is too long. Schemas are lists of fields and have to stay under %d KB.', 'yaml-custom-fields'),
          (int) (self::MAX_SCHEMA_BYTES / 1024)
        ),
        'warnings' => [],
      ];
    }

    try {
      $parsed = Yaml::parse($yaml);

      // Check if parsed successfully
      if ($parsed === null) {
        return [
          'valid' => false,
          'message' => __('The schema is empty, or it is not written in YAML.', 'yaml-custom-fields'),
          'warnings' => [],
        ];
      }

      // Check for required 'fields' key
      if (!isset($parsed['fields']) || !is_array($parsed['fields'])) {
        return [
          'valid' => false,
          'message' => __('The schema must start with "fields:", followed by the list of fields.', 'yaml-custom-fields'),
          'warnings' => [],
        ];
      }

      $error = $this->validateFieldList($parsed['fields'], $template, '', $strict);
      if ($error !== null) {
        return ['valid' => false, 'message' => $error, 'warnings' => $this->warnings];
      }

      return [
        'valid' => true,
        'message' => __('The schema looks good.', 'yaml-custom-fields'),
        'warnings' => $this->warnings,
      ];
    } catch (ParseException $e) {
      return [
        'valid' => false,
        /* translators: %s: YAML parser error */
        'message' => sprintf(__('The schema has a typing mistake. %s', 'yaml-custom-fields'), $e->getMessage()),
        'warnings' => $this->warnings,
      ];
    } catch (\Throwable $e) {
      // Anything else the parser raises on a document it cannot cope with, such
      // as running out of nesting depth. The schema is refused, not the request.
      return [
        'valid' => false,
        'message' => __('The schema could not be read. Check that it is a plain list of fields.', 'yaml-custom-fields'),
        'warnings' => $this->warnings,
      ];
    }
  }

  /**
   * Check one level of fields, then the levels inside it
   *
   * Groups and sections hold fields of their own, and those are rendered by the
   * same code as the top level, so they are held to the same rules.
   *
   * @param array $fields Fields at this level
   * @param string|null $template Template name (for info field validation)
   * @param string $path Field names leading here, for the message ('' at the top)
   * @param bool $strict False to collect unknown types as warnings instead of refusing
   * @return string|null Error message, or null when this level and everything inside it is fine
   */
  private function validateFieldList($fields, $template, $path, $strict = true) {
    // Shorthand (- info: "…", - section: "…") is expanded at every level when
    // fields are rendered, so it is expanded here too before anything is checked.
    $fields = $this->normalizer->normalizeAll($fields);

    $seen_names = [];

    foreach ($fields as $index => $field) {
      if (!is_array($field)) {
        return $this->atPath(
          $path,
          /* translators: %s: field index */
          sprintf(__('Field number %s is not written correctly. Start it with "- name:".', 'yaml-custom-fields'), $index + 1)
        );
      }

      if (!isset($field['name'])) {
        return $this->atPath(
          $path,
          /* translators: %s: field index */
          sprintf(__('Field number %s needs a name. Add a line like "name: title".', 'yaml-custom-fields'), $index + 1)
        );
      }

      if (!isset($field['type'])) {
        return $this->atPath(
          $path,
          /* translators: %s: field name */
          sprintf(__('The field "%s" needs a type. Add a line like "type: string".', 'yaml-custom-fields'), $field['name'])
        );
      }

      $name = (string) $field['name'];

      // Two fields of the same name means one quietly replaces the other: they
      // share a key in the stored data, and only the second one is ever read.
      if (isset($seen_names[$name])) {
        return $this->atPath(
          $path,
          /* translators: %s: field name */
          sprintf(__('There are two fields called "%s". Field names have to be different from one another.', 'yaml-custom-fields'), $name)
        );
      }
      $seen_names[$name] = true;

      if (in_array($name, self::RESERVED_NAMES, true)) {
        return $this->atPath(
          $path,
          /* translators: %s: field name */
          sprintf(__('"%s" is a name the plugin uses itself. Choose another name for the field.', 'yaml-custom-fields'), $name)
        );
      }

      $type = $field['type'];

      if (!is_string($type) || !in_array($type, self::FIELD_TYPES, true)) {
        $message = $this->atPath($path, $this->unknownTypeMessage($field['name'], $type));
        if ($strict) {
          return $message;
        }
        $this->warnings[] = $message;
      }

      // Validate info field template restrictions
      if ($type === 'info' && $template !== null && !$this->isTemplateAllowedForInfoField($template)) {
        /* translators: %s: template file name */
        return sprintf(__('Info boxes only work on page and post templates, not on %s.', 'yaml-custom-fields'), $template);
      }

      $nested_path = $path === '' ? (string) $field['name'] : $path . ' → ' . $field['name'];

      // A group holds fields directly
      if ($type === 'object' && isset($field['fields'])) {
        if (!is_array($field['fields'])) {
          /* translators: %s: field name */
          return $this->atPath($path, sprintf(__('The group "%s" needs a list of fields under "fields:".', 'yaml-custom-fields'), $field['name']));
        }
        $error = $this->validateFieldList($field['fields'], $template, $nested_path, $strict);
        if ($error !== null) {
          return $error;
        }
      }

      // A section field holds one list of fields per section type
      if ($type === 'block' && isset($field['blocks'])) {
        if (!is_array($field['blocks'])) {
          /* translators: %s: field name */
          return $this->atPath($path, sprintf(__('The sections field "%s" needs a list under "blocks:".', 'yaml-custom-fields'), $field['name']));
        }

        foreach ($field['blocks'] as $block_index => $block) {
          if (!is_array($block)) {
            /* translators: 1: field name, 2: section number */
            return $this->atPath($path, sprintf(__('Section number %2$s of "%1$s" is not written correctly. Start it with "- name:".', 'yaml-custom-fields'), $field['name'], $block_index + 1));
          }

          if (!isset($block['fields'])) {
            continue;
          }

          if (!is_array($block['fields'])) {
            /* translators: 1: field name, 2: section name */
            return $this->atPath($path, sprintf(__('Section "%2$s" of "%1$s" needs a list of fields under "fields:".', 'yaml-custom-fields'), $field['name'], isset($block['name']) ? $block['name'] : ($block_index + 1)));
          }

          $block_name = isset($block['name']) ? $block['name'] : ($block_index + 1);
          $error = $this->validateFieldList($block['fields'], $template, $nested_path . ' → ' . $block_name, $strict);
          if ($error !== null) {
            return $error;
          }
        }
      }
    }

    return null;
  }

  /**
   * Say which type was written and what was probably meant
   *
   * @param string $field_name Field the type belongs to
   * @param mixed $type Type as written in the schema
   * @return string Message
   */
  private function unknownTypeMessage($field_name, $type) {
    $written = is_string($type) ? $type : wp_json_encode($type);
    $suggestion = is_string($type) ? $this->closestType($type) : null;

    if ($suggestion !== null) {
      return sprintf(
        /* translators: 1: field name, 2: type as written in the schema, 3: suggested type */
        __('The field "%1$s" has a type this plugin does not know: "%2$s". Did you mean "%3$s"?', 'yaml-custom-fields'),
        $field_name,
        $written,
        $suggestion
      );
    }

    return sprintf(
      /* translators: 1: field name, 2: type as written in the schema, 3: comma-separated list of the types that work */
      __('The field "%1$s" has a type this plugin does not know: "%2$s". The types that work are: %3$s.', 'yaml-custom-fields'),
      $field_name,
      $written,
      implode(', ', self::FIELD_TYPES)
    );
  }

  /**
   * The known type nearest to what was written, when one is near enough to suggest
   *
   * @param string $type Type as written
   * @return string|null Suggested type, or null when nothing is close
   */
  private function closestType($type) {
    $written = strtolower($type);
    $best = null;
    $best_distance = PHP_INT_MAX;

    foreach (self::FIELD_TYPES as $known) {
      $distance = levenshtein($written, $known);
      if ($distance < $best_distance) {
        $best_distance = $distance;
        $best = $known;
      }
    }

    // Far enough away and a guess would only mislead
    $limit = max(2, (int) floor(strlen($written) / 3));

    return $best_distance <= $limit ? $best : null;
  }

  /**
   * Say where in the schema a problem is, when it is not at the top level
   *
   * @param string $path Field names leading to this level
   * @param string $message The problem
   * @return string Message, prefixed with the path when there is one
   */
  private function atPath($path, $message) {
    if ($path === '') {
      return $message;
    }

    /* translators: 1: field names leading to the problem, 2: the problem */
    return sprintf(__('Inside "%1$s": %2$s', 'yaml-custom-fields'), $path, $message);
  }

  /**
   * Check if a template is allowed to use info fields
   * Info fields are NOT allowed for partials and archives (global data templates)
   *
   * @param string $template The template name
   * @return bool True if allowed, false otherwise
   */
  private function isTemplateAllowedForInfoField($template) {
    // Get just the basename if path is provided
    $basename = basename($template);

    // Partial/Archive patterns that should NOT have info fields
    // These match the partial patterns in TemplateScanner::scanPhpTemplates()
    $disallowed_patterns = [
      // Traditional partials
      'header.php',
      'footer.php',
      'sidebar.php',
      'header-*.php',
      'footer-*.php',
      'sidebar-*.php',
      'content.php',
      'content-*.php',
      'comments.php',
      'searchform.php',
      // Archive/listing templates (global data)
      'index.php',
      'front-page.php',
      'home.php',
      'archive.php',
      'archive-*.php',
      'category.php',
      'category-*.php',
      'tag.php',
      'tag-*.php',
      'taxonomy.php',
      'taxonomy-*.php',
      'author.php',
      'author-*.php',
      'date.php',
      'search.php',
      '404.php',
    ];

    // A block theme addresses its templates by slug, with no .php on the end,
    // so "index" has to be caught by the same rule that catches "index.php".
    $candidates = [$basename];
    if (substr($basename, -4) !== '.php') {
      $candidates[] = $basename . '.php';
    }

    // Check if template matches any disallowed pattern
    foreach ($disallowed_patterns as $pattern) {
      foreach ($candidates as $candidate) {
        if ($pattern === $candidate || fnmatch($pattern, $candidate)) {
          return false; // This is a partial/archive, not allowed
        }
      }
    }

    // All other templates are allowed (page.php, single.php, custom page templates, etc.)
    return true;
  }
}
