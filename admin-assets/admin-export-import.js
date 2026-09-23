/**
 * YAML Custom Fields - Export/Import Page
 * Handles post data export and import functionality
 */

jQuery(document).ready(function($) {
  const { __, _n, sprintf } = wp.i18n;
  const PER_PAGE = 20;

  let postsData = [];
  let currentPage = 1;
  let totalPages = 1;
  // Selected post IDs persist across pages
  const selectedIds = new Set();

  // Escape a value for safe insertion into HTML strings
  function escapeHtml(value) {
    return $('<div>').text(value === undefined || value === null ? '' : String(value)).html();
  }

  function showPostsError(message) {
    $('#yaml-cf-posts-loading').empty().append($('<p>').addClass('yaml-cf-message-error').text(message)).show();
  }

  // Load one page of posts with custom field data
  function loadPosts(page) {
    $('#yaml-cf-posts-loading').show();

    $.ajax({
      url: yamlCF.ajax_url,
      type: 'POST',
      data: {
        action: 'yaml_cf_get_posts_with_data',
        nonce: yamlCF.nonce,
        page: page,
        per_page: PER_PAGE
      },
      success: function(response) {
        if (response.success) {
          postsData = response.data.posts;
          currentPage = response.data.page;
          totalPages = response.data.total_pages;
          renderPosts(response.data.total);
        } else {
          showPostsError(response.data);
        }
      },
      error: function() {
        showPostsError(__('The list of posts could not be loaded. Reload the page to try again.', 'yaml-custom-fields'));
      }
    });
  }

  function renderPosts(total) {
    $('#yaml-cf-posts-loading').hide();

    if (total === 0) {
      $('#yaml-cf-posts-list').hide();
      $('#yaml-cf-no-posts').show();
      return;
    }

    $('#yaml-cf-no-posts').hide();
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
          }).prop('checked', selectedIds.has(post.id))
        )
      );
      row.append($('<td>').append($('<a>').attr({ href: post.edit_url, target: '_blank' }).text(post.title || __('(no title)', 'yaml-custom-fields'))));
      row.append($('<td>').text(post.slug));
      row.append($('<td>').text(post.type));
      row.append($('<td>').text(post.template));
      row.append($('<td>').text(post.status));
      row.append($('<td>').text(post.id));
      tbody.append(row);
    });

    renderPagination(total);
    updateSelectedCount();
  }

  function renderPagination(total) {
    /* translators: %d: number of posts */
    $('#yaml-cf-posts-total').text(sprintf(_n('%d item', '%d items', total, 'yaml-custom-fields'), total));
    /* translators: 1: current page, 2: total pages */
    $('#yaml-cf-posts-page-info').text(sprintf(__('Page %1$d of %2$d', 'yaml-custom-fields'), currentPage, totalPages));
    $('#yaml-cf-posts-prev').prop('disabled', currentPage <= 1);
    $('#yaml-cf-posts-next').prop('disabled', currentPage >= totalPages);
    $('#yaml-cf-posts-pagination .pagination-links').toggle(totalPages > 1);
  }

  function updateSelectedCount() {
    $('#yaml-cf-selected-count').text(selectedIds.size);
    $('#yaml-cf-export-selected').prop('disabled', selectedIds.size === 0);

    const $pageBoxes = $('.yaml-cf-post-checkbox');
    $('#yaml-cf-select-all-checkbox').prop('checked', $pageBoxes.length > 0 && $pageBoxes.filter(':checked').length === $pageBoxes.length);

    updateHiddenPostIds();
  }

  function updateHiddenPostIds() {
    const $container = $('#yaml-cf-export-post-ids');
    $container.empty();

    selectedIds.forEach(function(postId) {
      $container.append($('<input>').attr({ type: 'hidden', name: 'post_ids[]', value: postId }));
    });
  }

  function setPageSelection(checked) {
    $('.yaml-cf-post-checkbox').each(function() {
      const postId = $(this).data('post-id');
      $(this).prop('checked', checked);
      if (checked) {
        selectedIds.add(postId);
      } else {
        selectedIds.delete(postId);
      }
    });
    updateSelectedCount();
  }

  // Select all on the current page
  $('#yaml-cf-select-all').on('click', function() {
    setPageSelection(true);
  });

  $('#yaml-cf-select-all-checkbox').on('change', function() {
    setPageSelection($(this).prop('checked'));
  });

  // Deselect everything, on all pages
  $('#yaml-cf-deselect-all').on('click', function() {
    selectedIds.clear();
    $('.yaml-cf-post-checkbox').prop('checked', false);
    updateSelectedCount();
  });

  $(document).on('change', '.yaml-cf-post-checkbox', function() {
    const postId = $(this).data('post-id');
    if ($(this).prop('checked')) {
      selectedIds.add(postId);
    } else {
      selectedIds.delete(postId);
    }
    updateSelectedCount();
  });

  $('#yaml-cf-posts-prev').on('click', function() {
    if (currentPage > 1) {
      loadPosts(currentPage - 1);
    }
  });

  $('#yaml-cf-posts-next').on('click', function() {
    if (currentPage < totalPages) {
      loadPosts(currentPage + 1);
    }
  });

  // Update match_by hidden input when radio selection changes
  $('input[name="match_by"]').on('change', function() {
    $('#yaml-cf-export-match-by').val($(this).val());
  });

  function showSpinner($message) {
    $message.empty().append($('<span>').addClass('spinner is-active yaml-cf-spinner-inline'));
  }

  function showResult($message, success, text) {
    const prefix = success ? '✓ ' : __('Problem:', 'yaml-custom-fields') + ' ';
    $message.empty().append(
      $('<span>').addClass(success ? 'yaml-cf-message-success' : 'yaml-cf-message-error').text(prefix + text)
    );
  }

  function errorListHtml(errors) {
    if (!errors || errors.length === 0) {
      return '';
    }
    let html = '<h4>' + escapeHtml(__('Problems:', 'yaml-custom-fields')) + '</h4><ul class="yaml-cf-error-list">';
    errors.forEach(function(error) {
      html += '<li>' + escapeHtml(error) + '</li>';
    });
    return html + '</ul>';
  }

  function resultLine(label, value) {
    return '<p><strong>' + escapeHtml(label) + '</strong> ' + escapeHtml(value) + '</p>';
  }

  // Import page data
  $('#yaml-cf-import-data').on('click', function() {
    const fileInput = document.getElementById('yaml-cf-import-file');
    if (!fileInput.files.length) {
      alert(__('Choose a file first.', 'yaml-custom-fields'));
      return;
    }

    const file = fileInput.files[0];
    const reader = new FileReader();
    const $message = $('#yaml-cf-import-message');
    showSpinner($message);

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
          if (!response.success) {
            showResult($message, false, response.data);
            return;
          }

          const result = response.data;
          showResult($message, true, result.message);

          let resultsHtml = resultLine(__('Imported:', 'yaml-custom-fields'), result.imported);
          resultsHtml += resultLine(__('Skipped:', 'yaml-custom-fields'), result.skipped);
          resultsHtml += resultLine(__('From website:', 'yaml-custom-fields'), result.imported_from);
          resultsHtml += resultLine(__('Exported on:', 'yaml-custom-fields'), result.exported_at);
          resultsHtml += errorListHtml(result.errors);

          if (result.debug && result.debug.length > 0) {
            resultsHtml += '<details class="yaml-cf-debug-details"><summary><strong>' + escapeHtml(__('Details for each post', 'yaml-custom-fields')) + '</strong></summary>';
            resultsHtml += '<table class="yaml-cf-debug-table">';
            resultsHtml += '<tr><th>' + escapeHtml(__('Post', 'yaml-custom-fields')) + '</th><th>' + escapeHtml(__('Action', 'yaml-custom-fields')) + '</th><th>' + escapeHtml(__('Details', 'yaml-custom-fields')) + '</th></tr>';
            result.debug.forEach(function(item) {
              let details;
              if (item.action === 'imported') {
                details = sprintf(
                  /* translators: 1: number of fields, 2: Yes/No data updated, 3: Yes/No schema stored */
                  __('Fields: %1$s. Content changed: %2$s. Schema found: %3$s.', 'yaml-custom-fields'),
                  item.cleaned_data_fields,
                  item.data_updated ? __('Yes', 'yaml-custom-fields') : __('No', 'yaml-custom-fields'),
                  item.schema_included ? __('Yes', 'yaml-custom-fields') : __('No', 'yaml-custom-fields')
                );
              } else {
                /* translators: %s: reason code */
                details = sprintf(__('Why: %s', 'yaml-custom-fields'), item.reason);
              }
              resultsHtml += '<tr>';
              resultsHtml += '<td>' + escapeHtml(item.post_title || __('N/A', 'yaml-custom-fields')) + ' (ID: ' + escapeHtml(item.post_id || item.search_value) + ')</td>';
              resultsHtml += '<td>' + escapeHtml(item.action) + '</td>';
              resultsHtml += '<td>' + escapeHtml(details) + '</td>';
              resultsHtml += '</tr>';
            });
            resultsHtml += '</table></details>';
          }

          resultsHtml += '<p class="yaml-cf-spaced-top"><a href="' + escapeHtml(yamlCF.admin_url + 'admin.php?page=yaml-cf-data-validation') + '" class="button">' + escapeHtml(__('Check images and files →', 'yaml-custom-fields')) + '</a></p>';

          $('#yaml-cf-import-results-content').html(resultsHtml);
          $('#yaml-cf-import-results').show();

          // Reload the current page of the posts list
          loadPosts(currentPage);
        },
        error: function() {
          showResult($message, false, __('The import did not work. Please try again.', 'yaml-custom-fields'));
        }
      });
    };

    reader.readAsText(file);
  });

  // Import data objects
  $('#yaml-cf-import-data-objects').on('click', function() {
    const fileInput = document.getElementById('yaml-cf-import-data-objects-file');
    if (!fileInput.files.length) {
      alert(__('Choose a file first.', 'yaml-custom-fields'));
      return;
    }

    const file = fileInput.files[0];
    const reader = new FileReader();
    const $message = $('#yaml-cf-import-data-objects-message');
    showSpinner($message);

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
          if (!response.success) {
            showResult($message, false, response.data);
            return;
          }

          const result = response.data;
          showResult($message, true, result.message);

          let resultsHtml = resultLine(__('Lists imported:', 'yaml-custom-fields'), result.types_imported);
          resultsHtml += resultLine(__('Items:', 'yaml-custom-fields'), result.entries_imported);
          resultsHtml += resultLine(__('Exported on:', 'yaml-custom-fields'), result.exported_at);
          resultsHtml += errorListHtml(result.errors);
          resultsHtml += '<p class="yaml-cf-spaced-top"><a href="' + escapeHtml(yamlCF.admin_url + 'admin.php?page=yaml-cf-data-objects') + '" class="button">' + escapeHtml(__('Go to Reusable lists →', 'yaml-custom-fields')) + '</a></p>';

          $('#yaml-cf-import-data-objects-results-content').html(resultsHtml);
          $('#yaml-cf-import-data-objects-results').show();

          // Refresh page to update export count
          setTimeout(function() {
            location.reload();
          }, 2000);
        },
        error: function() {
          showResult($message, false, __('The import did not work. Please try again.', 'yaml-custom-fields'));
        }
      });
    };

    reader.readAsText(file);
  });

  // Initial load
  loadPosts(1);
});
