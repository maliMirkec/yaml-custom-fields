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
  /**
   * Block templates that show one post or page, so they can hold page fields.
   *
   * The rest of the block hierarchy (index, archive, search, 404 and so on)
   * shows a list rather than one post, which is what the "site parts" list is
   * for. These are the block-theme counterparts of page.php and single.php.
   */
  const BLOCK_POST_TEMPLATES = ['singular', 'single', 'page', 'attachment', 'privacy-policy'];

  /** Prefixes of block templates that show one post or page, e.g. single-event */
  const BLOCK_POST_PREFIXES = ['single-', 'page-'];

  /** Prefixes of block templates that show a list of posts */
  const BLOCK_ARCHIVE_PREFIXES = ['archive-', 'category-', 'tag-', 'taxonomy-', 'author-', 'date-'];

  /** Block templates that show a list of posts rather than one of them */
  const BLOCK_ARCHIVE_TEMPLATES = [
    'index',
    'home',
    'front-page',
    'archive',
    'author',
    'category',
    'taxonomy',
    'date',
    'tag',
    'search',
    '404',
  ];

  private $formatter;

  public function __construct() {
    $this->formatter = new TemplateNameFormatter();
  }

  /**
   * Scan theme for templates and partials
   *
   * Both kinds of theme are scanned: the .php files of a classic theme and the
   * block templates of a block theme. A theme can have both, and a child theme
   * is scanned together with the parent it builds on.
   *
   * @return array Array with 'templates' and 'partials' keys
   */
  public function scan() {
    $php = $this->scanPhpTemplates();
    $block = $this->scanBlockTemplates();

    return [
      'templates' => $this->mergeByFile($php['templates'], $block['templates']),
      'partials' => $this->mergeByFile($php['partials'], $block['partials']),
    ];
  }

  /**
   * Join two lists of templates, keeping the first entry for any repeated file
   *
   * @param array $first Entries that win
   * @param array $second Entries added where the file is not already listed
   * @return array
   */
  private function mergeByFile($first, $second) {
    $seen = [];
    foreach ($first as $entry) {
      $seen[$entry['file']] = true;
    }

    foreach ($second as $entry) {
      if (!isset($seen[$entry['file']])) {
        $first[] = $entry;
        $seen[$entry['file']] = true;
      }
    }

    return $first;
  }

  /**
   * Where a theme file sits, relative to the theme it belongs to
   *
   * A child theme's files live in the stylesheet directory and the parent's in
   * the template directory. Measuring against only one of them left the other's
   * files with an absolute path, which then became the key a schema was stored
   * under.
   *
   * @param string $path Absolute path to a theme file
   * @return string Path relative to its own theme directory
   */
  private function relativeThemePath($path) {
    foreach ([get_stylesheet_directory(), get_template_directory()] as $directory) {
      $directory = trailingslashit($directory);
      if (strpos($path, $directory) === 0) {
        return substr($path, strlen($directory));
      }
    }

    return basename($path);
  }

  /**
   * The first of the active theme and its parent that holds a file
   *
   * @param string $relative Path relative to a theme directory
   * @return string Absolute path, or '' when neither theme has the file
   */
  private function locateThemeFile($relative) {
    foreach ([get_stylesheet_directory(), get_template_directory()] as $directory) {
      $candidate = trailingslashit($directory) . $relative;
      if (file_exists($candidate)) {
        return $candidate;
      }
    }

    return '';
  }

  /**
   * Scan the theme's .php templates
   *
   * @return array Array with 'templates' and 'partials' keys
   */
  private function scanPhpTemplates() {
    $templates = [];
    $partials = [];
    $theme = wp_get_theme();

    // Get all template files. The third argument includes the parent theme's
    // files: without it a child theme that only overrides style.css reported no
    // templates at all, although the parent had page.php and single.php.
    $template_files = $theme->get_files('php', -1, true); // -1 for unlimited depth

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
      $relative_path = $this->relativeThemePath($path);

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

    // Add custom page templates (templates with Template Name header).
    // get_page_templates() searches the parent theme itself, and on a block
    // theme it also returns block template slugs; those have no .php and are
    // picked up by scanBlockTemplates() instead.
    $page_templates = get_page_templates();
    foreach ($page_templates as $name => $file) {
      if (substr($file, -4) !== '.php') {
        continue;
      }

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
            // The file may live in the child theme or in the parent
            'path' => $this->locateThemeFile($file),
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
   * Scan a block theme's templates
   *
   * A block theme keeps its templates as .html files rather than .php, so the
   * scan above finds nothing in one. Schemas attach to these the same way, by
   * the template's slug.
   *
   * @return array Array with 'templates' and 'partials' keys
   */
  private function scanBlockTemplates() {
    $templates = [];
    $partials = [];

    // get_block_templates() arrived in WordPress 5.8; the plugin still runs on 5.0
    if (!function_exists('get_block_templates') || !$this->themeIsBlockTheme()) {
      return ['templates' => $templates, 'partials' => $partials];
    }

    $block_templates = get_block_templates([], 'wp_template');
    if (!is_array($block_templates)) {
      return ['templates' => $templates, 'partials' => $partials];
    }

    foreach ($block_templates as $block_template) {
      if (!isset($block_template->slug) || $block_template->slug === '') {
        continue;
      }

      $slug = $block_template->slug;
      $entry = [
        'file' => $slug,
        // Only used to look for the @ycf marker, which a block template cannot carry
        'path' => '',
        'name' => !empty($block_template->title) ? $block_template->title : $this->formatter->format($slug),
      ];

      if ($this->blockTemplateShowsOnePost($slug)) {
        $templates[] = $entry;
      } else {
        $partials[] = $entry;
      }
    }

    return ['templates' => $templates, 'partials' => $partials];
  }

  /**
   * Whether a block template slug shows one post or page
   *
   * Anything else shows a list, which is where shared content belongs rather
   * than per-page fields. A slug that is in neither list is a template someone
   * made by hand, and those are assigned to a single page.
   *
   * @param string $slug Block template slug
   * @return bool
   */
  private function blockTemplateShowsOnePost($slug) {
    if (in_array($slug, self::BLOCK_POST_TEMPLATES, true)) {
      return true;
    }

    if (in_array($slug, self::BLOCK_ARCHIVE_TEMPLATES, true)) {
      return false;
    }

    foreach (self::BLOCK_POST_PREFIXES as $prefix) {
      if (strpos($slug, $prefix) === 0) {
        return true;
      }
    }

    foreach (self::BLOCK_ARCHIVE_PREFIXES as $prefix) {
      if (strpos($slug, $prefix) === 0) {
        return false;
      }
    }

    // A template of someone's own, which is assigned to one page at a time
    return true;
  }

  /**
   * Whether the active theme builds its pages out of blocks
   *
   * @return bool
   */
  private function themeIsBlockTheme() {
    if (function_exists('wp_is_block_theme')) {
      return (bool) wp_is_block_theme();
    }

    $theme = wp_get_theme();

    return method_exists($theme, 'is_block_theme') && $theme->is_block_theme();
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
