<?php

/**
 * Markdown Parser
 * Basic markdown parser for info fields (bold, italic, links only)
 */

namespace YamlCF\Helpers;


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
class MarkdownParser {
  /**
   * Parse basic markdown
   * Supports: **bold**, _italic_, [links](url)
   *
   * @param string $text Text to parse
   * @return string Parsed HTML
   */
  public static function parse($text) {
    if (empty($text)) {
      return '';
    }

    // Step 1: Escape all HTML first to prevent XSS
    $text = esc_html($text);

    // Step 2: Parse **bold** syntax to <strong>
    $text = preg_replace_callback(
      '/\*\*([^\*]+)\*\*/',
      function($matches) {
        return '<strong>' . $matches[1] . '</strong>';
      },
      $text
    );

    // Step 3: Parse _italic_ syntax to <em>
    $text = preg_replace_callback(
      '/_([^_]+)_/',
      function($matches) {
        return '<em>' . $matches[1] . '</em>';
      },
      $text
    );

    // Step 4: Parse [text](url) syntax to <a href="url">text</a>
    $text = preg_replace_callback(
      '/\[([^\]]+)\]\(([^\)]+)\)/',
      function($matches) {
        $link_text = $matches[1];
        $url = $matches[2];

        // Sanitize URL - this strips javascript:, data:, and other dangerous protocols
        $safe_url = esc_url($url, ['http', 'https', 'mailto']);

        // If URL was deemed unsafe, esc_url returns empty string
        if (empty($safe_url)) {
          return $link_text; // Just return the text without a link
        }

        return '<a href="' . $safe_url . '" target="_blank" rel="noopener noreferrer">' . $link_text . '</a>';
      },
      $text
    );

    // Step 5: Apply final security filter to only allow specific tags
    $allowed_tags = [
      'strong' => [],
      'em' => [],
      'a' => [
        'href' => [],
        'target' => [],
        'rel' => []
      ]
    ];

    return wp_kses($text, $allowed_tags);
  }
}
