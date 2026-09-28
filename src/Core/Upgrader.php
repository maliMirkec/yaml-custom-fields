<?php

/**
 * Upgrader
 * Brings an existing install up to date with the current version
 */

namespace YamlCF\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Upgrader {
  /**
   * Where the schema version of the stored data is kept
   */
  const VERSION_OPTION = 'yaml_cf_db_version';

  /**
   * Set while an upgrade runs, so a second page load does not start another
   */
  const RUNNING_TRANSIENT = 'yaml_cf_upgrading';

  /**
   * The version the current code expects.
   *
   * 1: everything up to 1.2.9.
   * 2: plugin options are no longer loaded on every page view.
   * 3: the copy of the schema kept in every post is gone.
   * 4: site parts of a child theme are keyed by name, not by full path.
   * 5: each item of a collection has an option of its own.
   */
  const CURRENT_VERSION = 5;

  /**
   * Options the plugin keeps under a fixed name
   */
  const FIXED_OPTIONS = [
    'yaml_cf_schemas',
    'yaml_cf_global_schema',
    'yaml_cf_global_data',
    'yaml_cf_partial_data',
    'yaml_cf_template_settings',
    'yaml_cf_template_global_schemas',
    'yaml_cf_template_global_data',
    'yaml_cf_data_object_types',
    'yaml_cf_tracked_posts',
  ];

  /**
   * Run whatever this install still needs
   *
   * Runs in the admin only: the work is a one-off, and a visitor should never
   * pay for it. Until an administrator opens a page, the site behaves as it did
   * before the update.
   *
   * @return void
   */
  public function maybeUpgrade() {
    $installed = (int) get_option(self::VERSION_OPTION, 0);

    if ($installed >= self::CURRENT_VERSION) {
      return;
    }

    // Another page load is already doing it: two administrators, or two tabs.
    // Every step is safe to repeat, so this only saves doing it twice at once.
    if (get_transient(self::RUNNING_TRANSIENT)) {
      return;
    }
    set_transient(self::RUNNING_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS);

    if ($installed < 2) {
      $this->stopAutoloadingOptions();
    }

    if ($installed < 3) {
      $this->dropPerPostSchemaCopies();
    }

    if ($installed < 4) {
      $this->repairChildThemePaths();
    }

    if ($installed < 5) {
      $this->splitCollectionEntries();
    }

    update_option(self::VERSION_OPTION, self::CURRENT_VERSION, false);
    delete_transient(self::RUNNING_TRANSIENT);
  }

  /**
   * Give every item of a collection an option of its own
   *
   * A collection used to keep all of its items in one option, read and written
   * whole. Showing one item, searching for one, or saving one meant unserialising
   * every item in the collection. Each now has an option to itself, beside a
   * small index of id => label that listing and searching read instead.
   *
   * YamlCF\Data\EntryStore falls back to the old option until this has run, so
   * a site works either way and nothing depends on when it happens.
   *
   * @return void
   */
  private function splitCollectionEntries() {
    $types = get_option('yaml_cf_data_object_types', []);
    if (!is_array($types)) {
      return;
    }

    $store = new \YamlCF\Data\EntryStore();
    $parser = new \YamlCF\Schema\SchemaParser();

    foreach ($types as $type_slug => $type) {
      // The first field labels an item, matching what the pickers have shown
      $label_field = '';
      if (is_array($type) && !empty($type['schema']) && is_string($type['schema'])) {
        $schema = $parser->parse($type['schema']);
        if (!empty($schema['fields'][0]['name'])) {
          $label_field = (string) $schema['fields'][0]['name'];
        }
      }

      $store->ensureMoved((string) $type_slug, $label_field);
    }
  }

  /**
   * Key a child theme's site parts by name rather than by their full path
   *
   * The scan used to measure every file against the parent theme's directory.
   * A child theme's own files are not in it, so nothing was stripped and the
   * whole absolute path became the key its schema and content were stored
   * under. The scan is right now, which would leave those entries stranded
   * under a key nothing looks for, so they are moved across here.
   *
   * @return void
   */
  private function repairChildThemePaths() {
    $directories = [get_stylesheet_directory(), get_template_directory()];

    foreach (['yaml_cf_schemas', 'yaml_cf_partial_data', 'yaml_cf_template_settings'] as $option) {
      $stored = get_option($option, null);
      if (!is_array($stored) || empty($stored)) {
        continue;
      }

      $repaired = [];
      $changed = false;

      foreach ($stored as $key => $value) {
        $new_key = $this->themeRelativeKey((string) $key, $directories);

        if ($new_key !== (string) $key) {
          $changed = true;
        }

        // An entry already saved under the plain name is the newer one and wins
        if ($new_key === (string) $key || !array_key_exists($new_key, $stored)) {
          $repaired[$new_key] = $value;
        }
      }

      if ($changed) {
        update_option($option, $repaired, false);
      }
    }
  }

  /**
   * Turn a full path to a theme file into the name the scan uses now
   *
   * Keys that are already relative are left exactly as they are: a site part in
   * a sub-folder is legitimately stored as "parts/header.php".
   *
   * @param string $key Key as stored
   * @param string[] $directories Theme directories to measure against
   * @return string Key to store it under
   */
  private function themeRelativeKey($key, $directories) {
    // Template settings hold a second key per template for the site details flag
    $suffix = '';
    if (substr($key, -11) === '_use_global') {
      $suffix = '_use_global';
      $key = substr($key, 0, -11);
    }

    $is_absolute = strpos($key, '/') === 0 || preg_match('#^[A-Za-z]:[\\/]#', $key) === 1;
    if (!$is_absolute) {
      return $key . $suffix;
    }

    foreach ($directories as $directory) {
      $directory = trailingslashit($directory);
      if (strpos($key, $directory) === 0) {
        return substr($key, strlen($directory)) . $suffix;
      }
    }

    // From a theme that is no longer installed: the file name is the best guess
    return basename($key) . $suffix;
  }

  /**
   * Remove the copy of the schema that used to be kept in every post
   *
   * _yaml_cf_schema held the whole parsed schema, rewritten on every save of
   * every post, although it could be worked out from the post's template at any
   * time. On a site with a couple of thousand pages that is thousands of copies
   * of the same thing, and each one froze the schema as it was when that post
   * was last saved. YamlCF\Schema\PostSchemaLocator works it out instead.
   *
   * @return void
   */
  private function dropPerPostSchemaCopies() {
    delete_post_meta_by_key('_yaml_cf_schema');
  }

  /**
   * Stop WordPress loading this plugin's options on every page view
   *
   * Schemas and the content of shared fields and site details are only read
   * when something asks for them, but they were stored as autoloaded options,
   * so WordPress read all of them into memory on every request, including every
   * visit to the front end. On a site with a few templates that is tens of
   * kilobytes of YAML and page content per page view.
   *
   * Saving an option now records this (every update_option() call passes false),
   * so this is only for the options already in the database.
   *
   * @return void
   */
  private function stopAutoloadingOptions() {
    $options = self::FIXED_OPTIONS;

    // One option per collection, named after the collection
    $types = get_option('yaml_cf_data_object_types', []);
    if (is_array($types)) {
      foreach (array_keys($types) as $type_slug) {
        $options[] = 'yaml_cf_data_object_entries_' . $type_slug;
        $options[] = \YamlCF\Data\EntryStore::INDEX_PREFIX . $type_slug;
      }
    }

    // Only touch what is really there, so nothing is created as a side effect
    $existing = [];
    foreach ($options as $option) {
      if (get_option($option, null) !== null) {
        $existing[] = $option;
      }
    }

    if (empty($existing)) {
      return;
    }

    // In one go, with the API WordPress 6.4 added. Earlier versions needed
    // each option deleted and added back, which lost the option if the
    // request died in between.
    wp_set_option_autoload_values(array_fill_keys($existing, false));
  }
}
