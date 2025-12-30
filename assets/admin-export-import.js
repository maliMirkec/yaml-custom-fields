/**
 * YAML Custom Fields - Export/Import Page
 * Handles post data export and import functionality
 */

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
