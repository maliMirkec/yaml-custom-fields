<?php

/**
 * Template Scanner
 * Scan theme for templates and partials
 */

namespace YamlCF\Template;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class TemplateScanner {
  private $formatter;

  public function __construct() {
    $this->formatter = new TemplateNameFormatter();
  }

  /**
   * Scan theme for templates and partials
   *
   * @return array Array with 'templates' and 'partials' keys
   */
  public function scan() {
    $templates = [];
    $partials = [];
    $theme = wp_get_theme();

    // Get all template files
    $template_files = $theme->get_files('php', -1); // -1 for unlimited depth

    // WordPress template hierarchy - only templates tied to specific posts/pages
    $valid_template_patterns = [
      'page.php',
      'single.php',
      'attachment.php',
      // Specific templates with prefixes
      'page-*.php',
      'single-*.php',
    ];

    // Partial patterns (automatic detection)
    // These are templates with global data (not post-specific)
    $partial_patterns = [
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

    foreach ($template_files as $file => $path) {
      $basename = basename($file);
      $relative_path = str_replace(get_template_directory() . '/', '', $path);

      // Check if it's a root-level main template
      if (dirname($file) === '.') {
        $is_valid_template = false;
        foreach ($valid_template_patterns as $pattern) {
          if ($pattern === $basename || fnmatch($pattern, $basename)) {
            $is_valid_template = true;
            break;
          }
        }

        if ($is_valid_template) {
          $templates[] = [
            'file' => $basename,
            'path' => $path,
            'name' => $this->formatter->format($basename)
          ];
        }
      }

      // Check if it's a partial (automatic detection by pattern)
      $is_partial = false;
      foreach ($partial_patterns as $pattern) {
        if ($pattern === $basename || fnmatch($pattern, $basename)) {
          $is_partial = true;
          break;
        }
      }

      // Also check for @ycf marker in file header (custom partials)
      if (!$is_partial && $this->hasYcfMarker($path)) {
        $is_partial = true;
      }

      if ($is_partial) {
        $partials[] = [
          'file' => $relative_path,
          'path' => $path,
          'name' => $this->formatter->format($basename)
        ];
      }
    }

    // Add custom page templates (templates with Template Name header)
    $page_templates = get_page_templates();
    foreach ($page_templates as $name => $file) {
      // Only include templates in the root directory
      if (strpos($file, '/') === false) {
        // Avoid duplicates
        $already_added = false;
        foreach ($templates as $existing) {
          if ($existing['file'] === $file) {
            $already_added = true;
            break;
          }
        }

        if (!$already_added) {
          $templates[] = [
            'file' => $file,
            'path' => get_template_directory() . '/' . $file,
            'name' => $name
          ];
        }
      }
    }

    return [
      'templates' => $templates,
      'partials' => $partials
    ];
  }

  /**
   * Check if a file has the @ycf marker in its header
   * Only reads first 30 lines for performance
   *
   * @param string $file_path File path
   * @return bool True if marker found
   */
  public function hasYcfMarker($file_path) {
    if (!file_exists($file_path) || !is_readable($file_path)) {
      return false;
    }

    $content = file_get_contents($file_path, false, null, 0, 8192); // Read first 8KB
    if ($content === false) {
      return false;
    }

    // Check first 30 lines for @ycf marker
    $lines = explode("\n", $content);
    $lines_to_check = min(30, count($lines));

    for ($i = 0; $i < $lines_to_check; $i++) {
      if (preg_match('/@ycf/i', $lines[$i])) {
        return true;
      }
    }

    return false;
  }
}
