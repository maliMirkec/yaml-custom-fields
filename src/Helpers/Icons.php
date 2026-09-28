<?php

/**
 * Icons
 * One icon per idea, everywhere in the plugin
 */

namespace YamlCF\Helpers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Icons help people scan a screen, but only if the same thing always has the
 * same icon: site details looked like one thing on the main screen and another
 * in the post editor, and the same update icon meant both "refresh the list"
 * and "page caches". Every icon the plugin prints comes from this list.
 *
 * Icons are decoration next to text that already says what it is, so they are
 * hidden from screen readers. The notice and status icons (done, note,
 * warning, error) are drawn by the stylesheet from the notice type instead.
 */
class Icons {
  /**
   * Dashicon names, by what they stand for
   */
  const MAP = [
    // Places and ideas
    'plugin' => 'forms',
    'templates' => 'layout',
    'site_parts' => 'welcome-widgets-menus',
    'site_details' => 'admin-site-alt3',
    'shared' => 'networking',
    'page_only' => 'admin-page',
    'post' => 'admin-post',
    'collections' => 'database',
    'export_import' => 'migrate',
    'content_check' => 'search',
    'docs' => 'book-alt',
    'page_caches' => 'performance',
    'rest' => 'rest-api',
    'uninstall' => 'trash',
    'settings' => 'admin-settings',
    'getting_started' => 'lightbulb',
    'summary' => 'chart-bar',
    // Actions
    'schema' => 'admin-generic',
    'edit' => 'edit',
    'add' => 'plus-alt2',
    'remove' => 'no-alt',
    'delete' => 'trash',
    'back' => 'arrow-left-alt2',
    'next' => 'arrow-right-alt2',
    'export' => 'download',
    'import' => 'upload',
    'refresh' => 'update',
    'copy' => 'editor-code',
    'image' => 'format-image',
    'gallery' => 'format-gallery',
    'file' => 'media-default',
    // An eraser: the values go, nothing is deleted until the page is saved
    'empty' => 'editor-removeformatting',
    'move_up' => 'arrow-up-alt',
    'move_down' => 'arrow-down-alt',
    'collapse' => 'arrow-up-alt2',
    'expand' => 'arrow-down-alt2',
    // States, the same four the notices and status lines draw in admin.css
    'status_success' => 'yes-alt',
    'status_info' => 'info',
    'status_warning' => 'warning',
    'status_error' => 'dismiss',
  ];

  /**
   * The dashicon name for an idea
   *
   * @param string $key Key in MAP
   * @return string Dashicon name, without the "dashicons-" prefix
   */
  public static function name($key) {
    return isset(self::MAP[$key]) ? self::MAP[$key] : 'marker';
  }

  /**
   * The markup for an icon, hidden from screen readers
   *
   * @param string $key Key in MAP
   * @param string $class Extra class, for placing or colouring it
   * @return string HTML
   */
  public static function html($key, $class = '') {
    $classes = 'dashicons dashicons-' . self::name($key) . ' yaml-cf-icon' . ($class !== '' ? ' ' . $class : '');

    return '<span class="' . esc_attr($classes) . '" aria-hidden="true"></span>';
  }

  /**
   * Print an icon
   *
   * @param string $key Key in MAP
   * @param string $class Extra class, for placing or colouring it
   * @return void
   */
  public static function render($key, $class = '') {
    echo wp_kses(self::html($key, $class), ['span' => ['class' => true, 'aria-hidden' => true]]);
  }
}
