<?php
/**
 * Data Validation Template
 */

if (!defined('ABSPATH')) {
  exit;
}

// Get all posts with custom field data and validate them
// Try to get from cache first
$yaml_cf_cache_key = 'yaml_cf_validation_posts';
$yaml_cf_results = wp_cache_get($yaml_cf_cache_key, 'yaml-custom-fields');

if (false === $yaml_cf_results) {
  // Query all posts without meta_key to avoid slow query warnings
  // Filter in PHP using metadata_exists() which uses WordPress's cached get_post_meta()
  $yaml_cf_all_posts = get_posts([
    'post_type' => ['page', 'post'],
    'post_status' => ['publish', 'draft', 'pending', 'private'],
    'posts_per_page' => -1,
    'orderby' => ['post_type' => 'ASC', 'title' => 'ASC'],
    'fields' => 'all',
    'no_found_rows' => true,
    'update_post_term_cache' => false,
  ]);

  // Filter to only include posts with both required meta keys
  // This is faster than meta_query because WordPress caches post meta
  $yaml_cf_results = [];
  foreach ($yaml_cf_all_posts as $yaml_cf_post) {
    // Check if post has both required meta keys
    if (metadata_exists('post', $yaml_cf_post->ID, '_yaml_cf_imported') &&
        metadata_exists('post', $yaml_cf_post->ID, '_yaml_cf_data')) {
      $yaml_cf_results[] = $yaml_cf_post;
    }
  }

  // Cache for 5 minutes
  wp_cache_set($yaml_cf_cache_key, $yaml_cf_results, 'yaml-custom-fields', 300);
}

$yaml_cf_validation_results = [];
$yaml_cf_total_posts = count($yaml_cf_results);
$yaml_cf_posts_with_issues = 0;
$yaml_cf_total_missing_attachments = 0;

foreach ($yaml_cf_results as $yaml_cf_post) {
  $yaml_cf_data = get_post_meta($yaml_cf_post->ID, '_yaml_cf_data', true);
  if (empty($yaml_cf_data)) {
    continue;
  }

  // Get the schema for this post
  $yaml_cf_schema = get_post_meta($yaml_cf_post->ID, '_yaml_cf_schema', true);

  $yaml_cf_missing_attachments = yaml_cf_validate_yaml_cf_attachments($yaml_cf_data, '', $yaml_cf_schema);

  if (!empty($yaml_cf_missing_attachments)) {
    $yaml_cf_posts_with_issues++;
    $yaml_cf_total_missing_attachments += count($yaml_cf_missing_attachments);
  }

  $yaml_cf_validation_results[] = [
    'post' => $yaml_cf_post,
    'missing_attachments' => $yaml_cf_missing_attachments
  ];
}

// Helper function to recursively find attachment IDs using schema
function yaml_cf_validate_yaml_cf_attachments($yaml_cf_data, $yaml_cf_path = '', $yaml_cf_schema = null) {
  $yaml_cf_missing = [];

  if (!is_array($yaml_cf_data)) {
    return $yaml_cf_missing;
  }

  // If no schema provided, skip validation (can't determine which fields are attachments)
  if (empty($yaml_cf_schema)) {
    return $yaml_cf_missing;
  }

  foreach ($yaml_cf_data as $yaml_cf_key => $yaml_cf_value) {
    $yaml_cf_current_path = $yaml_cf_path ? $yaml_cf_path . ' > ' . $yaml_cf_key : $yaml_cf_key;

    // Find the field definition in the schema
    $yaml_cf_field_schema = yaml_cf_find_field_in_schema($yaml_cf_schema, $yaml_cf_key);

    if (is_array($yaml_cf_value)) {
      // For list fields (arrays), check each item
      if ($yaml_cf_field_schema && isset($yaml_cf_field_schema['list']) && $yaml_cf_field_schema['list']) {
        // This is a list field, validate each item
        foreach ($yaml_cf_value as $yaml_cf_index => $yaml_cf_item) {
          $yaml_cf_item_path = $yaml_cf_current_path . ' > ' . $yaml_cf_index;
          if (is_array($yaml_cf_item)) {
            // Get the schema for list items (could be blocks)
            $yaml_cf_item_schema = $yaml_cf_field_schema;
            if (isset($yaml_cf_field_schema['fields'])) {
              $yaml_cf_item_schema = $yaml_cf_field_schema['fields'];
            } elseif (isset($yaml_cf_field_schema['blocks'])) {
              // For block fields, find the matching block type
              if (isset($yaml_cf_item['type']) && isset($yaml_cf_field_schema['blocks'])) {
                foreach ($yaml_cf_field_schema['blocks'] as $yaml_cf_block) {
                  if (isset($yaml_cf_block['name']) && $yaml_cf_block['name'] === $yaml_cf_item['type']) {
                    $yaml_cf_item_schema = isset($yaml_cf_block['fields']) ? $yaml_cf_block['fields'] : [];
                    break;
                  }
                }
              }
            }
            $yaml_cf_nested_missing = yaml_cf_validate_yaml_cf_attachments($yaml_cf_item, $yaml_cf_item_path, $yaml_cf_item_schema);
            $yaml_cf_missing = array_merge($yaml_cf_missing, $yaml_cf_nested_missing);
          }
        }
      } else {
        // Regular nested object, pass the nested schema
        $yaml_cf_nested_schema = null;
        if ($yaml_cf_field_schema && isset($yaml_cf_field_schema['fields'])) {
          $yaml_cf_nested_schema = $yaml_cf_field_schema['fields'];
        }
        $yaml_cf_nested_missing = yaml_cf_validate_yaml_cf_attachments($yaml_cf_value, $yaml_cf_current_path, $yaml_cf_nested_schema);
        $yaml_cf_missing = array_merge($yaml_cf_missing, $yaml_cf_nested_missing);
      }
    } elseif ($yaml_cf_field_schema && in_array($yaml_cf_field_schema['type'], ['image', 'file'], true)) {
      // Only validate if this field is defined as image or file type in schema
      if (is_numeric($yaml_cf_value) && intval($yaml_cf_value) > 0) {
        $yaml_cf_attachment = get_post(intval($yaml_cf_value));
        if (!$yaml_cf_attachment || $yaml_cf_attachment->post_type !== 'attachment') {
          $yaml_cf_missing[] = [
            'field' => $yaml_cf_current_path,
            'id' => intval($yaml_cf_value)
          ];
        }
      }
    }
  }

  return $yaml_cf_missing;
}

// Helper function to find a field definition in schema
function yaml_cf_find_field_in_schema($yaml_cf_schema, $yaml_cf_field_name) {
  if (!is_array($yaml_cf_schema)) {
    return null;
  }

  foreach ($yaml_cf_schema as $yaml_cf_field) {
    if (isset($yaml_cf_field['name']) && $yaml_cf_field['name'] === $yaml_cf_field_name) {
      return $yaml_cf_field;
    }
  }

  return null;
}
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Data Validation', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Validate custom field data and detect missing attachments', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-validation-container">
    <!-- Summary Card -->
    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h2><?php esc_html_e('Validation Summary', 'yaml-custom-fields'); ?></h2>

      <div class="yaml-cf-summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($yaml_cf_total_posts); ?></div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Total Posts', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: <?php echo $yaml_cf_posts_with_issues > 0 ? '#d63638' : '#00a32a'; ?>;">
            <?php echo esc_html($yaml_cf_posts_with_issues); ?>
          </div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Posts with Issues', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: <?php echo $yaml_cf_total_missing_attachments > 0 ? '#d63638' : '#00a32a'; ?>;">
            <?php echo esc_html($yaml_cf_total_missing_attachments); ?>
          </div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Missing Attachments', 'yaml-custom-fields'); ?></div>
        </div>

        <div class="yaml-cf-stat-box" style="padding: 20px; background: #f0f0f1; border-radius: 4px; text-align: center;">
          <div style="font-size: 32px; font-weight: bold; color: #00a32a;">
            <?php echo esc_html($yaml_cf_total_posts - $yaml_cf_posts_with_issues); ?>
          </div>
          <div style="margin-top: 5px; color: #646970;"><?php esc_html_e('Healthy Posts', 'yaml-custom-fields'); ?></div>
        </div>
      </div>

      <?php if ($yaml_cf_posts_with_issues === 0): ?>
        <div class="notice notice-success inline" style="margin-top: 20px;">
          <p><strong><?php esc_html_e('All data is valid!', 'yaml-custom-fields'); ?></strong> <?php esc_html_e('No missing attachments found.', 'yaml-custom-fields'); ?></p>
        </div>
      <?php else: ?>
        <div class="notice notice-warning inline" style="margin-top: 20px;">
          <p>
            <strong><?php esc_html_e('Issues detected!', 'yaml-custom-fields'); ?></strong>
            <?php
            /* translators: %d: number of posts with issues */
            printf(esc_html__('%d posts have missing attachments. Review the details below.', 'yaml-custom-fields'), absint($yaml_cf_posts_with_issues));
            ?>
          </p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Filter Options -->
    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h3><?php esc_html_e('Filter', 'yaml-custom-fields'); ?></h3>
      <div style="margin-bottom: 15px;">
        <label style="display: inline-flex; align-items: center; margin-right: 20px;">
          <input type="radio" name="filter" value="all" checked style="margin-right: 5px;">
          <?php esc_html_e('Show All', 'yaml-custom-fields'); ?>
        </label>
        <label style="display: inline-flex; align-items: center; margin-right: 20px;">
          <input type="radio" name="filter" value="issues" style="margin-right: 5px;">
          <?php esc_html_e('Only Issues', 'yaml-custom-fields'); ?>
        </label>
        <label style="display: inline-flex; align-items: center;">
          <input type="radio" name="filter" value="healthy" style="margin-right: 5px;">
          <?php esc_html_e('Only Healthy', 'yaml-custom-fields'); ?>
        </label>
      </div>
    </div>

    <!-- Validation Results -->
    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h2><?php esc_html_e('Validation Details', 'yaml-custom-fields'); ?></h2>

      <?php if (empty($yaml_cf_validation_results)): ?>
        <p><?php esc_html_e('No pages or posts with custom field data found.', 'yaml-custom-fields'); ?></p>
      <?php else: ?>
        <table class="wp-list-table widefat striped">
          <thead>
            <tr>
              <th><?php esc_html_e('Title', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Type', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Status', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Validation Status', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Issues', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Actions', 'yaml-custom-fields'); ?></th>
            </tr>
          </thead>
          <tbody id="yaml-cf-validation-tbody">
            <?php foreach ($yaml_cf_validation_results as $yaml_cf_result): ?>
              <?php
              $yaml_cf_post = $yaml_cf_result['post'];
              $yaml_cf_missing = $yaml_cf_result['missing_attachments'];
              $yaml_cf_has_issues = !empty($yaml_cf_missing);
              $yaml_cf_data_status = $yaml_cf_has_issues ? 'issues' : 'healthy';
              ?>
              <tr class="yaml-cf-validation-row" data-status="<?php echo esc_attr($yaml_cf_data_status); ?>">
                <td>
                  <strong>
                    <a href="<?php echo esc_url(get_edit_post_link($yaml_cf_post->ID)); ?>" target="_blank">
                      <?php echo esc_html($yaml_cf_post->post_title); ?>
                    </a>
                  </strong>
                  <br>
                  <small style="color: #646970;"><?php echo esc_html($yaml_cf_post->post_name); ?></small>
                </td>
                <td><?php echo esc_html($yaml_cf_post->post_type); ?></td>
                <td>
                  <span class="post-state"><?php echo esc_html($yaml_cf_post->post_status); ?></span>
                </td>
                <td>
                  <?php if ($yaml_cf_has_issues): ?>
                    <span style="color: #d63638; font-weight: bold;">⚠ <?php esc_html_e('Issues Found', 'yaml-custom-fields'); ?></span>
                  <?php else: ?>
                    <span style="color: #00a32a; font-weight: bold;">✓ <?php esc_html_e('Valid', 'yaml-custom-fields'); ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($yaml_cf_has_issues): ?>
                    <details>
                      <summary style="cursor: pointer; color: #d63638;">
                        <?php
                        /* translators: %d: number of missing attachments */
                        printf(esc_html__('%d missing attachments', 'yaml-custom-fields'), count($yaml_cf_missing));
                        ?>
                      </summary>
                      <ul style="margin: 10px 0 0 20px; list-style: disc;">
                        <?php foreach ($yaml_cf_missing as $yaml_cf_item): ?>
                          <li>
                            <strong><?php echo esc_html($yaml_cf_item['field']); ?>:</strong>
                            <?php
                            /* translators: %d: attachment ID */
                            printf(esc_html__('ID %d (not found)', 'yaml-custom-fields'), absint($yaml_cf_item['id']));
                            ?>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </details>
                  <?php else: ?>
                    <span style="color: #646970;">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?php echo esc_url(get_edit_post_link($yaml_cf_post->ID)); ?>" class="button button-small" target="_blank">
                    <?php esc_html_e('Edit', 'yaml-custom-fields'); ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h3><?php esc_html_e('What to do about missing attachments?', 'yaml-custom-fields'); ?></h3>
      <ul style="margin: 10px 0 0 20px; list-style: disc;">
        <li><?php esc_html_e('Missing attachments may occur after importing data from another site', 'yaml-custom-fields'); ?></li>
        <li><?php esc_html_e('Edit each affected post and re-upload the images/files', 'yaml-custom-fields'); ?></li>
        <li><?php esc_html_e('Alternatively, import the media library from the source site first', 'yaml-custom-fields'); ?></li>
        <li><?php esc_html_e('You can also manually update attachment IDs if you know the mapping', 'yaml-custom-fields'); ?></li>
      </ul>
    </div>
  </div>
  </div>
</div>

<script>
jQuery(document).ready(function($) {
  // Filter functionality
  $('input[name="filter"]').on('change', function() {
    const filter = $(this).val();
    const $rows = $('.yaml-cf-validation-row');

    if (filter === 'all') {
      $rows.show();
    } else if (filter === 'issues') {
      $rows.hide();
      $rows.filter('[data-status="issues"]').show();
    } else if (filter === 'healthy') {
      $rows.hide();
      $rows.filter('[data-status="healthy"]').show();
    }
  });
});
</script>
