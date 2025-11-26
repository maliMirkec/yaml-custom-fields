<?php
/**
 * Export Page Data Template
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'icon-256x256.png'); ?>" alt="YAML Custom Fields" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Export & Import', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('Export and import custom field data between sites', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>

    <div class="yaml-cf-export-data-container">

    <!-- Settings Export/Import -->
    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h2><?php esc_html_e('Settings Export/Import', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Backup or restore all schemas and template settings.', 'yaml-custom-fields'); ?></p>

      <div style="margin: 20px 0;">
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?yaml_cf_export_settings=1'), 'yaml_cf_export_settings')); ?>" class="button button-primary">
          <span class="dashicons dashicons-download"></span>
          <?php esc_html_e('Export Settings', 'yaml-custom-fields'); ?>
        </a>
        <button type="button" class="button yaml-cf-import-settings-trigger" style="margin-left: 10px;">
          <span class="dashicons dashicons-upload"></span>
          <?php esc_html_e('Import Settings', 'yaml-custom-fields'); ?>
        </button>
        <input type="file" id="yaml-cf-import-settings-file" accept=".json" style="display: none;" />
      </div>

      <div class="yaml-cf-info-box" style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 12px; margin-top: 20px;">
        <p style="margin: 0;">
          <strong><?php esc_html_e('What gets exported:', 'yaml-custom-fields'); ?></strong>
        </p>
        <ul style="margin: 10px 0 0 20px;">
          <li><?php esc_html_e('All YAML schemas for page templates', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('All YAML schemas for partials', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Template enable/disable settings', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>
    </div>

    <!-- Page Data Export/Import -->
    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h2><?php esc_html_e('Page Data Export/Import', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Export and import custom field data for individual posts and pages.', 'yaml-custom-fields'); ?></p>

      <h3 style="margin-top: 20px;"><?php esc_html_e('Select Pages/Posts to Export', 'yaml-custom-fields'); ?></h3>

      <div class="yaml-cf-info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 20px;">
        <p style="margin: 0;">
          <strong><?php esc_html_e('Note:', 'yaml-custom-fields'); ?></strong>
          <?php esc_html_e('Image and file fields export attachment IDs only. After import, verify all images/files exist on the target site. Missing attachments will be removed from the data.', 'yaml-custom-fields'); ?>
        </p>
      </div>

      <div class="yaml-cf-export-options" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
        <h3><?php esc_html_e('Export Options', 'yaml-custom-fields'); ?></h3>
        <p>
          <label style="display: inline-flex; align-items: center; margin-right: 20px;">
            <input type="radio" name="match_by" value="slug" checked style="margin-right: 5px;">
            <strong><?php esc_html_e('Match by Slug', 'yaml-custom-fields'); ?></strong>
            <span style="margin-left: 5px; color: #666;">(<?php esc_html_e('Recommended - works across different sites', 'yaml-custom-fields'); ?>)</span>
          </label>
          <label style="display: inline-flex; align-items: center;">
            <input type="radio" name="match_by" value="id" style="margin-right: 5px;">
            <strong><?php esc_html_e('Match by Post ID', 'yaml-custom-fields'); ?></strong>
            <span style="margin-left: 5px; color: #666;">(<?php esc_html_e('Only for same site or when IDs match', 'yaml-custom-fields'); ?>)</span>
          </label>
        </p>
      </div>

      <div id="yaml-cf-posts-loading" style="text-align: center; padding: 40px;">
        <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
        <p><?php esc_html_e('Loading posts...', 'yaml-custom-fields'); ?></p>
      </div>

      <form method="post" id="yaml-cf-export-form">
        <?php wp_nonce_field('yaml_cf_export_page_data', 'yaml_cf_export_page_data_nonce'); ?>
        <input type="hidden" name="match_by" id="yaml-cf-export-match-by" value="slug">

      <div id="yaml-cf-posts-list" style="display: none;">
        <div class="yaml-cf-posts-header" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <button type="button" class="button" id="yaml-cf-select-all"><?php esc_html_e('Select All', 'yaml-custom-fields'); ?></button>
            <button type="button" class="button" id="yaml-cf-deselect-all"><?php esc_html_e('Deselect All', 'yaml-custom-fields'); ?></button>
          </div>
          <div>
            <span id="yaml-cf-selected-count">0</span> <?php esc_html_e('selected', 'yaml-custom-fields'); ?>
          </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <td class="check-column"><input type="checkbox" id="yaml-cf-select-all-checkbox"></td>
              <th><?php esc_html_e('Title', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Slug', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Type', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Template', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('Status', 'yaml-custom-fields'); ?></th>
              <th><?php esc_html_e('ID', 'yaml-custom-fields'); ?></th>
            </tr>
          </thead>
          <tbody id="yaml-cf-posts-tbody">
          </tbody>
        </table>

        <div id="yaml-cf-export-post-ids"></div>

        <div style="margin-top: 20px;">
          <button type="submit" class="button button-primary" id="yaml-cf-export-selected" disabled>
            <?php esc_html_e('Export Selected', 'yaml-custom-fields'); ?>
          </button>
        </div>
      </div>
      </form>

      <div id="yaml-cf-no-posts" style="display: none; padding: 40px; text-align: center;">
        <p><?php esc_html_e('No pages or posts with custom field data found.', 'yaml-custom-fields'); ?></p>
      </div>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h3><?php esc_html_e('Import Page Data', 'yaml-custom-fields'); ?></h3>

      <div class="yaml-cf-info-box" style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 12px; margin-bottom: 20px;">
        <p style="margin: 0;">
          <strong><?php esc_html_e('Import Instructions:', 'yaml-custom-fields'); ?></strong>
        </p>
        <ul style="margin: 10px 0 0 20px;">
          <li><?php esc_html_e('Select a JSON file exported from this plugin', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Posts will be matched based on the export preference (slug or ID)', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Missing attachments (images/files) will be automatically removed', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Use the Data Validation page after import to check for issues', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>

      <div>
        <input type="file" id="yaml-cf-import-file" accept=".json" style="margin-bottom: 10px;">
        <br>
        <button type="button" class="button button-primary" id="yaml-cf-import-data">
          <?php esc_html_e('Import Page Data', 'yaml-custom-fields'); ?>
        </button>
        <span id="yaml-cf-import-message" style="margin-left: 15px;"></span>
      </div>

      <div id="yaml-cf-import-results" style="display: none; margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
        <h3><?php esc_html_e('Import Results', 'yaml-custom-fields'); ?></h3>
        <div id="yaml-cf-import-results-content"></div>
      </div>
    </div>

    <!-- Data Objects Export/Import -->
    <div class="card" style="max-width: 100%; margin-top: 40px;">
      <h2><?php esc_html_e('Data Objects Export/Import', 'yaml-custom-fields'); ?></h2>
      <p><?php esc_html_e('Export and import data object types and their entries.', 'yaml-custom-fields'); ?></p>

      <h3 style="margin-top: 20px;"><?php esc_html_e('Export Data Objects', 'yaml-custom-fields'); ?></h3>

      <div class="yaml-cf-info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 20px;">
        <p style="margin: 0;">
          <strong><?php esc_html_e('Note:', 'yaml-custom-fields'); ?></strong>
          <?php esc_html_e('This exports all data object types and their entries. Image and file fields in entries export attachment IDs only.', 'yaml-custom-fields'); ?>
        </p>
      </div>

      <?php
      $yaml_cf_data_object_types = get_option('yaml_cf_data_object_types', []);
      if (empty($yaml_cf_data_object_types)) :
      ?>
        <div class="notice notice-info inline">
          <p><?php esc_html_e('No data object types created yet.', 'yaml-custom-fields'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=yaml-cf-data-objects')); ?>"><?php esc_html_e('Create your first data object type', 'yaml-custom-fields'); ?></a></p>
        </div>
      <?php else : ?>
        <form method="post" id="yaml-cf-export-data-objects-form">
          <?php wp_nonce_field('yaml_cf_export_data_objects', 'yaml_cf_export_data_objects_nonce'); ?>
          <button type="submit" class="button button-primary">
            <?php esc_html_e('Export All Data Objects', 'yaml-custom-fields'); ?>
          </button>
          <p class="description" style="margin-top: 10px;">
            <?php
            $yaml_cf_total_types = count($yaml_cf_data_object_types);
            $yaml_cf_total_entries = 0;
            foreach ($yaml_cf_data_object_types as $yaml_cf_type_slug => $yaml_cf_type_data) {
              $yaml_cf_entries = get_option('yaml_cf_data_object_entries_' . $yaml_cf_type_slug, []);
              $yaml_cf_total_entries += count($yaml_cf_entries);
            }
            /* translators: 1: number of types, 2: number of entries */
            printf(esc_html__('Export %1$d data object types with %2$d total entries', 'yaml-custom-fields'), absint($yaml_cf_total_types), absint($yaml_cf_total_entries));
            ?>
          </p>
        </form>
      <?php
      endif;
      ?>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
      <h3><?php esc_html_e('Import Data Objects', 'yaml-custom-fields'); ?></h3>

      <div class="yaml-cf-info-box" style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 12px; margin-bottom: 20px;">
        <p style="margin: 0;">
          <strong><?php esc_html_e('Import Instructions:', 'yaml-custom-fields'); ?></strong>
        </p>
        <ul style="margin: 10px 0 0 20px;">
          <li><?php esc_html_e('Select a JSON file exported from this plugin', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Existing data object types with the same slug will be updated', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('Missing attachments (images/files) will be automatically removed', 'yaml-custom-fields'); ?></li>
          <li><?php esc_html_e('All entries for each type will be imported', 'yaml-custom-fields'); ?></li>
        </ul>
      </div>

      <div>
        <input type="file" id="yaml-cf-import-data-objects-file" accept=".json" style="margin-bottom: 10px;">
        <br>
        <button type="button" class="button button-primary" id="yaml-cf-import-data-objects">
          <?php esc_html_e('Import Data Objects', 'yaml-custom-fields'); ?>
        </button>
        <span id="yaml-cf-import-data-objects-message" style="margin-left: 15px;"></span>
      </div>

      <div id="yaml-cf-import-data-objects-results" style="display: none; margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
        <h3><?php esc_html_e('Import Results', 'yaml-custom-fields'); ?></h3>
        <div id="yaml-cf-import-data-objects-results-content"></div>
      </div>
    </div>
  </div>
  </div>
</div>

<script>
jQuery(document).ready(function($) {
  let postsData = [];

  // Load posts with custom field data
  function loadPosts() {
    $.ajax({
      url: yamlCF.ajax_url,
      type: 'POST',
      data: {
        action: 'yaml_cf_get_posts_with_data',
        nonce: yamlCF.nonce
      },
      success: function(response) {
        if (response.success) {
          postsData = response.data.posts;
          renderPosts();
        } else {
          $('#yaml-cf-posts-loading').html('<p style="color: red;">' + response.data + '</p>');
        }
      },
      error: function() {
        $('#yaml-cf-posts-loading').html('<p style="color: red;">Failed to load posts</p>');
      }
    });
  }

  function renderPosts() {
    $('#yaml-cf-posts-loading').hide();

    if (postsData.length === 0) {
      $('#yaml-cf-no-posts').show();
      return;
    }

    $('#yaml-cf-posts-list').show();
    const tbody = $('#yaml-cf-posts-tbody');
    tbody.empty();

    postsData.forEach(function(post) {
      const row = $('<tr>');
      row.append(
        $('<th>').addClass('check-column').append(
          $('<input>').attr({
            type: 'checkbox',
            'data-post-id': post.id,
            class: 'yaml-cf-post-checkbox'
          })
        )
      );
      row.append($('<td>').html('<a href="' + post.edit_url + '" target="_blank">' + post.title + '</a>'));
      row.append($('<td>').text(post.slug));
      row.append($('<td>').text(post.type));
      row.append($('<td>').text(post.template));
      row.append($('<td>').text(post.status));
      row.append($('<td>').text(post.id));
      tbody.append(row);
    });

    updateSelectedCount();
  }

  function updateSelectedCount() {
    const count = $('.yaml-cf-post-checkbox:checked').length;
    $('#yaml-cf-selected-count').text(count);
    $('#yaml-cf-export-selected').prop('disabled', count === 0);

    // Update hidden inputs for form submission
    updateHiddenPostIds();
  }

  function updateHiddenPostIds() {
    const $container = $('#yaml-cf-export-post-ids');
    $container.empty();

    $('.yaml-cf-post-checkbox:checked').each(function() {
      const postId = $(this).data('post-id');
      $container.append('<input type="hidden" name="post_ids[]" value="' + postId + '">');
    });
  }

  // Select/Deselect all
  $('#yaml-cf-select-all, #yaml-cf-select-all-checkbox').on('click', function() {
    $('.yaml-cf-post-checkbox').prop('checked', true);
    $('#yaml-cf-select-all-checkbox').prop('checked', true);
    updateSelectedCount();
  });

  $('#yaml-cf-deselect-all').on('click', function() {
    $('.yaml-cf-post-checkbox').prop('checked', false);
    $('#yaml-cf-select-all-checkbox').prop('checked', false);
    updateSelectedCount();
  });

  $(document).on('change', '.yaml-cf-post-checkbox', updateSelectedCount);

  // Update match_by hidden input when radio selection changes
  $('input[name="match_by"]').on('change', function() {
    $('#yaml-cf-export-match-by').val($(this).val());
  });

  // Import page data
  $('#yaml-cf-import-data').on('click', function() {
    const fileInput = document.getElementById('yaml-cf-import-file');
    if (!fileInput.files.length) {
      alert('Please select a file to import.');
      return;
    }

    const file = fileInput.files[0];
    const reader = new FileReader();
    const $message = $('#yaml-cf-import-message');
    $message.html('<span class="spinner is-active" style="float: none;"></span>');

    reader.onload = function(e) {
      $.ajax({
        url: yamlCF.ajax_url,
        type: 'POST',
        data: {
          action: 'yaml_cf_import_page_data',
          nonce: yamlCF.nonce,
          data: e.target.result
        },
        success: function(response) {
          if (response.success) {
            $message.html('<span style="color: green;">✓ ' + response.data.message + '</span>');

            // Show detailed results
            let resultsHtml = '<p><strong>Imported:</strong> ' + response.data.imported + '</p>';
            resultsHtml += '<p><strong>Skipped:</strong> ' + response.data.skipped + '</p>';
            resultsHtml += '<p><strong>Source:</strong> ' + response.data.imported_from + '</p>';
            resultsHtml += '<p><strong>Exported at:</strong> ' + response.data.exported_at + '</p>';

            if (response.data.errors && response.data.errors.length > 0) {
              resultsHtml += '<h4>Errors:</h4><ul style="color: #d63638;">';
              response.data.errors.forEach(function(error) {
                resultsHtml += '<li>' + error + '</li>';
              });
              resultsHtml += '</ul>';
            }

            // Show debug info
            if (response.data.debug && response.data.debug.length > 0) {
              resultsHtml += '<details style="margin-top: 15px;"><summary style="cursor: pointer;"><strong>Debug Information</strong></summary>';
              resultsHtml += '<table style="margin-top: 10px; border-collapse: collapse; width: 100%;">';
              resultsHtml += '<tr><th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Post</th><th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Action</th><th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Details</th></tr>';
              response.data.debug.forEach(function(item) {
                let details = '';
                if (item.action === 'imported') {
                  details = 'Fields: ' + item.data_fields + ', Data Updated: ' + (item.data_updated ? 'Yes' : 'No') + ', Schema: ' + (item.schema_included ? 'Yes' : 'No');
                } else {
                  details = 'Reason: ' + item.reason;
                }
                resultsHtml += '<tr>';
                resultsHtml += '<td style="border: 1px solid #ddd; padding: 8px;">' + (item.post_title || 'N/A') + ' (ID: ' + (item.post_id || item.search_value) + ')</td>';
                resultsHtml += '<td style="border: 1px solid #ddd; padding: 8px;">' + item.action + '</td>';
                resultsHtml += '<td style="border: 1px solid #ddd; padding: 8px;">' + details + '</td>';
                resultsHtml += '</tr>';
              });
              resultsHtml += '</table></details>';
            }

            resultsHtml += '<p style="margin-top: 15px;"><a href="' + yamlCF.admin_url + 'admin.php?page=yaml-cf-data-validation" class="button">View Data Validation →</a></p>';

            $('#yaml-cf-import-results-content').html(resultsHtml);
            $('#yaml-cf-import-results').show();

            // Reload posts list
            loadPosts();
          } else {
            $message.html('<span style="color: red;">Error: ' + response.data + '</span>');
          }
        },
        error: function() {
          $message.html('<span style="color: red;">Import failed</span>');
        }
      });
    };

    reader.readAsText(file);
  });

  // Import data objects
  $('#yaml-cf-import-data-objects').on('click', function() {
    const fileInput = document.getElementById('yaml-cf-import-data-objects-file');
    if (!fileInput.files.length) {
      alert('Please select a file to import.');
      return;
    }

    const file = fileInput.files[0];
    const reader = new FileReader();
    const $message = $('#yaml-cf-import-data-objects-message');
    $message.html('<span class="spinner is-active" style="float: none;"></span>');

    reader.onload = function(e) {
      $.ajax({
        url: yamlCF.ajax_url,
        type: 'POST',
        data: {
          action: 'yaml_cf_import_data_objects',
          nonce: yamlCF.nonce,
          data: e.target.result
        },
        success: function(response) {
          if (response.success) {
            $message.html('<span style="color: green;">✓ ' + response.data.message + '</span>');

            // Show detailed results
            let resultsHtml = '<p><strong>Types Imported:</strong> ' + response.data.types_imported + '</p>';
            resultsHtml += '<p><strong>Total Entries:</strong> ' + response.data.entries_imported + '</p>';
            resultsHtml += '<p><strong>Exported at:</strong> ' + response.data.exported_at + '</p>';

            if (response.data.errors && response.data.errors.length > 0) {
              resultsHtml += '<h4>Errors:</h4><ul style="color: #d63638;">';
              response.data.errors.forEach(function(error) {
                resultsHtml += '<li>' + error + '</li>';
              });
              resultsHtml += '</ul>';
            }

            resultsHtml += '<p style="margin-top: 15px;"><a href="' + yamlCF.admin_url + 'admin.php?page=yaml-cf-data-objects" class="button">View Data Objects →</a></p>';

            $('#yaml-cf-import-data-objects-results-content').html(resultsHtml);
            $('#yaml-cf-import-data-objects-results').show();

            // Refresh page to update export count
            setTimeout(function() {
              location.reload();
            }, 2000);
          } else {
            $message.html('<span style="color: red;">Error: ' + response.data + '</span>');
          }
        },
        error: function() {
          $message.html('<span style="color: red;">Import failed</span>');
        }
      });
    };

    reader.readAsText(file);
  });

  // Initial load
  loadPosts();
});
</script>
