<?php

/**
 * Template Sorter
 * Orders template lists for display: the most used templates first, then A–Z
 */

namespace YamlCF\Template;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TemplateSorter {
  /**
   * Page and post templates shown first, in this order
   */
  const TEMPLATE_PRIORITY = ['page.php', 'single.php'];

  /**
   * Site parts shown first, in this order
   */
  const PARTIAL_PRIORITY = ['header.php', 'footer.php', 'sidebar.php'];

  /**
   * Sort page and post templates: standard page, blog post, then the rest A–Z
   *
   * @param array $templates Items with 'file' and 'name' keys
   * @return array Sorted items
   */
  public function sortTemplates(array $templates) {
    return $this->sort($templates, self::TEMPLATE_PRIORITY);
  }

  /**
   * Sort site parts: header, footer, sidebar, then the rest A–Z
   *
   * @param array $partials Items with 'file' and 'name' keys
   * @return array Sorted items
   */
  public function sortPartials(array $partials) {
    return $this->sort($partials, self::PARTIAL_PRIORITY);
  }

  /**
   * Sort by priority file list first, then by name (natural, case-insensitive), then by file
   *
   * @param array $items Items with 'file' and 'name' keys
   * @param string[] $priority Files that come first, in order
   * @return array Sorted items
   */
  private function sort(array $items, array $priority) {
    $rank = array_flip($priority);

    usort($items, function ($a, $b) use ($rank) {
      $rank_a = isset($rank[$a['file']]) ? $rank[$a['file']] : PHP_INT_MAX;
      $rank_b = isset($rank[$b['file']]) ? $rank[$b['file']] : PHP_INT_MAX;
      if ($rank_a !== $rank_b) {
        return $rank_a <=> $rank_b;
      }

      $by_name = strnatcasecmp((string) $a['name'], (string) $b['name']);
      return $by_name !== 0 ? $by_name : strnatcasecmp((string) $a['file'], (string) $b['file']);
    });

    return $items;
  }
}
