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
   * The version the current code expects.
   *
   * 1: everything up to 1.2.9.
   * 2: plugin options are no longer loaded on every page view.
   * 3: the copy of the schema kept in every post is gone.
   * 4: site parts of a child theme are keyed by name, not by full path.
   */
  const CURRENT_VERSION = 4;

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

    if ($installed < 2) {
      $this->stopAutoloadingOptions();
    }

    if ($installed < 3) {
      $this->dropPerPostSchemaCopies();
    }

    if ($installed < 4) {
      $this->repairChildThemePaths();
    }

    update_option(self::VERSION_OPTION, self::CURRENT_VERSION, false);
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

    // WordPress 6.4 and newer can set them all in one go
    if (function_exists('wp_set_option_autoload_values')) {
      wp_set_option_autoload_values(array_fill_keys($existing, false));
      return;
    }

    foreach ($existing as $option) {
      $this->stopAutoloadingOption($option);
    }
  }

  /**
   * Stop one option being autoloaded, on WordPress versions with no API for it
   *
   * Writing to the options table directly is not allowed in this plugin, so the
   * value is read, removed and put back with autoloading switched off. It is
   * read back afterwards, and put back as it was if anything went wrong, so a
   * failure cannot leave the option missing.
   *
   * @param string $option Option name
   * @return void
   */
  private function stopAutoloadingOption($option) {
    if (function_exists('wp_set_option_autoload')) {
      wp_set_option_autoload($option, false);
      return;
    }

    $value = get_option($option, null);
    if ($value === null) {
      return;
    }

    if (!delete_option($option)) {
      return;
    }

    if (!add_option($option, $value, '', false)) {
      // Put it back rather than lose it
      add_option($option, $value);
    }
  }
}
