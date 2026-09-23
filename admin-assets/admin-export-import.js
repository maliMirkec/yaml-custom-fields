/**
 * YAML Custom Fields - Export & Import page
 *
 * Export runs as a three step wizard: what to export, which posts, review.
 * Import reads the file first and lists what it holds, marking anything that
 * would land on something already here, so overwriting is always a choice.
 */

jQuery(document).ready(function ($) {
  const { __, _n, sprintf } = wp.i18n;
  const PER_PAGE = 20;

  // ---------------------------------------------------------------- helpers

  function escapeHtml(value) {
    return $('<div>').text(value === undefined || value === null ? '' : String(value)).html();
  }

  function showSpinner($message) {
    $message.empty().append($('<span>').addClass('spinner is-active yaml-cf-spinner-inline'));
  }

  function showResult($message, success, text) {
    const prefix = success ? '✓ ' : __('Problem:', 'yaml-custom-fields') + ' ';
    $message.empty().append(
      $('<span>').addClass(success ? 'yaml-cf-message-success' : 'yaml-cf-message-error').text(prefix + text)
    );
  }

  // Hand the browser a file without a round trip through the server
  function downloadJson(data, filename) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  function readJsonFile(file, onReady, onError) {
    const reader = new FileReader();
    reader.onload = function (event) {
      try {
        onReady(JSON.parse(event.target.result));
      } catch (e) {
        onError(__('This file is not valid JSON.', 'yaml-custom-fields'));
      }
    };
    reader.onerror = function () {
      onError(__('This file could not be read.', 'yaml-custom-fields'));
    };
    reader.readAsText(file);
  }

  function exportFilename() {
    const host = window.location.hostname.replace(/[^a-z0-9.-]/gi, '');
    const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
    return 'yaml-cf-' + host + '-' + stamp + '.json';
  }

  // ================================================================ EXPORT

  let postsData = [];
  let currentPage = 1;
  let totalPages = 1;
  let postsLoaded = false;
  const selectedIds = new Set();

  function wantsPosts() {
    return $('#yaml-cf-want-posts').prop('checked');
  }

  // Both panes use the same tick-box classes, so the export says where to look
  const EXPORT_PICKS = '.yaml-cf-wizard-panel[data-panel="1"]';

  function chosenItems() {
    return $(EXPORT_PICKS + ' .yaml-cf-pick:checked').map(function () {
      return $(this).val();
    }).get();
  }

  function goToStep(step) {
    $('.yaml-cf-wizard-panel').addClass('yaml-cf-hidden').filter('[data-panel="' + step + '"]').removeClass('yaml-cf-hidden');
    $('#yaml-cf-wizard-steps li').removeClass('is-current is-done').each(function () {
      const own = parseInt($(this).data('step'), 10);
      if (own === step) {
        $(this).addClass('is-current');
      } else if (own < step) {
        $(this).addClass('is-done');
      }
    });
    $('#yaml-cf-wizard-steps li[data-step="2"]').toggleClass('is-skipped', !wantsPosts());
  }

  /**
   * Keep a parent tick box in step with the leaves under it
   *
   * Used at both levels: a template row over its own pieces, and a group
   * heading over everything in the group.
   */
  function refreshParent($parent, $leaves) {
    const total = $leaves.length;
    const checked = $leaves.filter(':checked').length;
    $parent
      .prop('checked', total > 0 && checked === total)
      .prop('indeterminate', checked > 0 && checked < total);
  }

  function refreshGroup($group) {
    $group.find('.yaml-cf-pick-node').each(function () {
      refreshParent($(this).find('.yaml-cf-pick-branch'), $(this).find('.yaml-cf-pick:not(:disabled)'));
    });
    refreshParent($group.children('.yaml-cf-pick-row').find('.yaml-cf-pick-all'), $group.find('.yaml-cf-pick:not(:disabled)'));
  }

  // A group heading turns everything in the group on or off
  $(document).on('change', '.yaml-cf-pick-all', function () {
    const $group = $(this).closest('.yaml-cf-pick-group');
    const on = $(this).prop('checked');
    $group.find('.yaml-cf-pick:not(:disabled), .yaml-cf-pick-branch').prop('checked', on).prop('indeterminate', false);
  });

  // A template row turns its own pieces on or off
  $(document).on('change', '.yaml-cf-pick-branch', function () {
    const $node = $(this).closest('.yaml-cf-pick-node');
    $node.find('.yaml-cf-pick:not(:disabled)').prop('checked', $(this).prop('checked'));
    refreshGroup($node.closest('.yaml-cf-pick-group'));
  });

  // A single piece brings its template row and its group heading up to date
  $(document).on('change', '.yaml-cf-pick', function () {
    refreshGroup($(this).closest('.yaml-cf-pick-group'));
  });

  // Open a template row to see the pieces inside it
  $(document).on('click', '.yaml-cf-pick-expand', function () {
    const open = $(this).attr('aria-expanded') === 'true';
    $(this).attr('aria-expanded', open ? 'false' : 'true');
    $('#' + $(this).attr('aria-controls')).prop('hidden', open);
  });

  $('#yaml-cf-step1-next').on('click', function () {
    if (chosenItems().length === 0 && !wantsPosts()) {
      window.alert(__('Choose at least one thing to export.', 'yaml-custom-fields'));
      return;
    }

    if (wantsPosts()) {
      if (!postsLoaded) {
        loadPosts(1);
      }
      goToStep(2);
    } else {
      renderSummary();
      goToStep(3);
    }
  });

  $('#yaml-cf-step2-next').on('click', function () {
    renderSummary();
    goToStep(3);
  });

  $('[data-wizard-back="1"]').on('click', function () {
    goToStep(1);
  });

  $('#yaml-cf-step3-back').on('click', function () {
    goToStep(wantsPosts() ? 2 : 1);
  });

  function renderSummary() {
    const lines = [];
    const groups = {};

    $(EXPORT_PICKS + ' .yaml-cf-pick:checked').each(function () {
      const group = $(this).closest('.yaml-cf-pick-group').data('group');
      groups[group] = (groups[group] || 0) + 1;
    });

    if (groups.setup) {
      lines.push(sprintf(
        /* translators: %d: number of setup pieces */
        _n('%d setup item', '%d setup items', groups.setup, 'yaml-custom-fields'),
        groups.setup
      ));
    }
    if (wantsPosts()) {
      lines.push(sprintf(
        /* translators: %d: number of pages and posts */
        _n('%d page or post', '%d pages and posts', selectedIds.size, 'yaml-custom-fields'),
        selectedIds.size
      ));
    }
    if (groups.lists) {
      lines.push(sprintf(
        /* translators: %d: number of collections */
        _n('%d collection', '%d collections', groups.lists, 'yaml-custom-fields'),
        groups.lists
      ));
    }

    let html = '<h3>' + escapeHtml(__('This file will hold:', 'yaml-custom-fields')) + '</h3>';
    if (lines.length === 0) {
      html += '<p>' + escapeHtml(__('Nothing is selected.', 'yaml-custom-fields')) + '</p>';
    } else {
      html += '<ul class="yaml-cf-summary-list">';
      lines.forEach(function (line) {
        html += '<li>' + escapeHtml(line) + '</li>';
      });
      html += '</ul>';
    }

    $('#yaml-cf-export-summary').html(html);
  }

  $('#yaml-cf-do-export').on('click', function () {
    const $button = $(this);
    const $message = $('#yaml-cf-export-message');

    showSpinner($message);
    $button.prop('disabled', true);

    $.ajax({
      url: yamlCF.ajax_url,
      type: 'POST',
      data: {
        action: 'yaml_cf_export_bundle',
        nonce: yamlCF.nonce,
        selection: chosenItems(),
        post_ids: wantsPosts() ? Array.from(selectedIds) : [],
        match_by: $('input[name="match_by"]:checked').val() || 'slug'
      },
      success: function (response) {
        if (response.success) {
          downloadJson(response.data, exportFilename());
          showResult($message, true, __('File saved.', 'yaml-custom-fields'));
        } else {
          showResult($message, false, response.data);
        }
      },
      error: function () {
        showResult($message, false, __('The export could not be built. Try again.', 'yaml-custom-fields'));
      },
      complete: function () {
        $button.prop('disabled', false);
      }
    });
  });

  // ---------------------------------------------------------- posts table

  function showPostsError(message) {
    $('#yaml-cf-posts-loading').empty().append($('<p>').addClass('yaml-cf-message-error').text(message)).show();
  }

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
      success: function (response) {
        if (response.success) {
          postsLoaded = true;
          postsData = response.data.posts;
          currentPage = response.data.page;
          totalPages = response.data.total_pages;
          renderPosts(response.data.total);
        } else {
          showPostsError(response.data);
        }
      },
      error: function () {
        showPostsError(__('The list could not be loaded. Reload the page to try again.', 'yaml-custom-fields'));
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

    postsData.forEach(function (post) {
      const row = $('<tr>');
      row.append($('<th>').addClass('check-column').append(
        $('<input>').attr({ type: 'checkbox', 'aria-label': post.title })
          .addClass('yaml-cf-post-checkbox')
          .attr('data-post-id', post.id)
          .prop('checked', selectedIds.has(post.id))
      ));
      row.append($('<td>').append($('<a>').attr({ href: post.edit_url, target: '_blank' }).text(post.title || __('(no title)', 'yaml-custom-fields'))));
      row.append($('<td>').text(post.slug || ''));
      row.append($('<td>').text(post.type || ''));
      row.append($('<td>').text(post.template || ''));
      row.append($('<td>').text(post.status || ''));
      row.append($('<td>').text(post.id));
      tbody.append(row);
    });

    $('#yaml-cf-posts-total').text(sprintf(
      /* translators: %d: number of posts */
      _n('%d item', '%d items', total, 'yaml-custom-fields'),
      total
    ));
    $('#yaml-cf-posts-page-info').text(sprintf(
      /* translators: 1: current page, 2: total pages */
      __('Page %1$d of %2$d', 'yaml-custom-fields'),
      currentPage,
      totalPages
    ));
    $('#yaml-cf-posts-prev').prop('disabled', currentPage <= 1);
    $('#yaml-cf-posts-next').prop('disabled', currentPage >= totalPages);

    updateSelectedCount();
  }

  function updateSelectedCount() {
    $('#yaml-cf-selected-count').text(selectedIds.size);
    $('#yaml-cf-step2-next').prop('disabled', false);
  }

  $(document).on('change', '.yaml-cf-post-checkbox', function () {
    const postId = $(this).data('post-id');
    if ($(this).prop('checked')) {
      selectedIds.add(postId);
    } else {
      selectedIds.delete(postId);
    }
    updateSelectedCount();
  });

  $('#yaml-cf-select-all, #yaml-cf-select-all-checkbox').on('click', function () {
    $('.yaml-cf-post-checkbox').prop('checked', true).trigger('change');
  });

  $('#yaml-cf-deselect-all').on('click', function () {
    selectedIds.clear();
    $('.yaml-cf-post-checkbox').prop('checked', false);
    updateSelectedCount();
  });

  $('#yaml-cf-posts-prev').on('click', function () {
    if (currentPage > 1) {
      loadPosts(currentPage - 1);
    }
  });

  $('#yaml-cf-posts-next').on('click', function () {
    if (currentPage < totalPages) {
      loadPosts(currentPage + 1);
    }
  });

  // ================================================================ IMPORT

  let bundleData = null;

  const GROUP_TITLES = {
    setup: __('Setup', 'yaml-custom-fields'),
    posts: __('Pages and posts', 'yaml-custom-fields'),
    lists: __('Collections', 'yaml-custom-fields')
  };

  $('#yaml-cf-bundle-file').on('change', function () {
    const file = this.files[0];
    const $message = $('#yaml-cf-inspect-message');

    $('#yaml-cf-import-preview').hide();
    $('#yaml-cf-import-results').hide();

    if (!file) {
      $message.empty();
      return;
    }

    showSpinner($message);

    readJsonFile(file, function (parsed) {
      bundleData = parsed;

      $.ajax({
        url: yamlCF.ajax_url,
        type: 'POST',
        data: {
          action: 'yaml_cf_inspect_bundle',
          nonce: yamlCF.nonce,
          data: JSON.stringify(parsed)
        },
        success: function (response) {
          if (response.success) {
            $message.empty();
            renderPreview(response.data);
          } else {
            showResult($message, false, response.data);
          }
        },
        error: function () {
          showResult($message, false, __('The file could not be checked. Try again.', 'yaml-custom-fields'));
        }
      });
    }, function (error) {
      showResult($message, false, error);
    });
  });

  function flagHtml(row, group) {
    if (group === 'posts' && row.missing) {
      return '<span class="yaml-cf-pick-flag is-missing">' + escapeHtml(__('not on this website', 'yaml-custom-fields')) + '</span>';
    }
    if (row.exists) {
      return '<span class="yaml-cf-pick-flag is-overwrite">' + escapeHtml(__('exists here - will overwrite', 'yaml-custom-fields')) + '</span>';
    }
    return '<span class="yaml-cf-pick-flag is-new">' + escapeHtml(__('new', 'yaml-custom-fields')) + '</span>';
  }

  function leafHtml(row, group) {
    const missing = group === 'posts' && row.missing;
    let html = '<label class="yaml-cf-pick-row' + (missing ? ' is-missing' : '') + '">';
    html += '<input type="checkbox" class="yaml-cf-pick" value="' + escapeHtml(row.id) + '"' +
      (missing ? ' disabled' : ' checked') + ' />';
    html += '<span class="yaml-cf-pick-label">' + escapeHtml(row.label) + '</span>';
    if (row.meta) {
      html += '<span class="yaml-cf-pick-meta">' + escapeHtml(row.meta) + '</span>';
    }
    html += flagHtml(row, group);
    return html + '</label>';
  }

  // A template and its pieces, folded away until opened - the same shape the
  // export wizard uses, so one set of tick-box rules covers both.
  function branchHtml(node, index) {
    const children = node.children || [];
    const single = children.length === 1;
    const panelId = 'yaml-cf-import-branch-' + index;
    const overwrites = children.filter(function (c) { return c.exists; }).length;

    let html = '<div class="yaml-cf-pick-node"><div class="yaml-cf-pick-row is-branch">';
    html += '<input type="checkbox" class="yaml-cf-pick-branch" checked aria-label="' + escapeHtml(node.label) + '" />';
    html += '<span class="yaml-cf-pick-label">' + escapeHtml(node.label) + '</span>';
    if (node.summary) {
      html += '<span class="yaml-cf-pick-meta">' + escapeHtml(node.summary) + '</span>';
    }
    if (overwrites > 0) {
      html += '<span class="yaml-cf-pick-flag is-overwrite">' + escapeHtml(sprintf(
        /* translators: %d: number of pieces that already exist here */
        _n('%d already here', '%d already here', overwrites, 'yaml-custom-fields'),
        overwrites
      )) + '</span>';
    }
    if (!single) {
      html += '<button type="button" class="yaml-cf-pick-expand" aria-expanded="false" aria-controls="' + panelId + '">';
      html += '<span class="screen-reader-text">' + escapeHtml(__('Show what is inside', 'yaml-custom-fields')) + '</span>';
      html += '<span class="yaml-cf-pick-caret" aria-hidden="true"></span></button>';
    }
    html += '</div><div class="yaml-cf-pick-children" id="' + panelId + '" hidden>';
    children.forEach(function (child) {
      html += leafHtml(child, 'setup');
    });
    return html + '</div></div>';
  }

  function groupHtml(group, rows) {
    let html = '<div class="yaml-cf-pick-group" data-group="' + escapeHtml(group) + '">';
    html += '<label class="yaml-cf-pick-row is-group">';
    html += '<input type="checkbox" class="yaml-cf-pick-all" checked />';
    html += '<span class="yaml-cf-pick-label">' + escapeHtml(GROUP_TITLES[group]) + '</span>';
    html += '<span class="yaml-cf-pick-meta">' + escapeHtml(sprintf(
      /* translators: %d: number of things in the file */
      _n('%d item in the file', '%d items in the file', rows.length, 'yaml-custom-fields'),
      rows.length
    )) + '</span></label><div class="yaml-cf-pick-list">';

    rows.forEach(function (row, index) {
      // Setup arrives as templates holding their pieces; the rest are plain rows
      html += group === 'setup' ? branchHtml(row, index) : leafHtml(row, group);
    });

    return html + '</div></div>';
  }

  function renderPreview(payload) {
    const items = payload.items || {};
    let html = '';
    let any = false;

    ['setup', 'posts', 'lists'].forEach(function (group) {
      const rows = items[group] || [];
      if (rows.length === 0) {
        return;
      }
      any = true;
      html += groupHtml(group, rows);
    });

    if (!any) {
      html = '<p>' + escapeHtml(__('This file holds nothing that can be imported.', 'yaml-custom-fields')) + '</p>';
    }

    $('#yaml-cf-import-groups').html(html);

    let source = '';
    if (payload.from) {
      /* translators: %s: website the file came from */
      source = sprintf(__('From %s', 'yaml-custom-fields'), payload.from);
    }
    if (payload.exported_at) {
      /* translators: %s: date the file was exported */
      source += (source ? ' \u00b7 ' : '') + sprintf(__('exported %s', 'yaml-custom-fields'), payload.exported_at);
    }
    $('#yaml-cf-import-source').text(source);

    $('#yaml-cf-import-preview').show();
    updateImportCounts();
  }

  function importSelection() {
    return $('#yaml-cf-import-groups .yaml-cf-pick:checked');
  }

  function updateImportCounts() {
    const $chosen = importSelection();
    const count = $chosen.length;
    let overwrites = 0;

    $chosen.each(function () {
      if ($(this).closest('.yaml-cf-pick-row').find('.yaml-cf-pick-flag.is-overwrite').length) {
        overwrites++;
      }
    });

    $('#yaml-cf-do-import')
      .prop('disabled', count === 0)
      .text(count === 0
        ? __('Import', 'yaml-custom-fields')
        : sprintf(
          /* translators: %d: number of ticked things */
          _n('Import %d selected', 'Import %d selected', count, 'yaml-custom-fields'),
          count
        ));

    $('#yaml-cf-overwrite-count').text(overwrites === 0 ? '' : sprintf(
      /* translators: %d: number of things that will be overwritten */
      _n('%d will be overwritten', '%d will be overwritten', overwrites, 'yaml-custom-fields'),
      overwrites
    ));
  }

  // Bound after the shared tick-box rules above, so the counts see the result
  $(document).on('change', '#yaml-cf-import-groups input[type="checkbox"]', updateImportCounts);

  $('#yaml-cf-do-import').on('click', function () {
    const selection = importSelection().map(function () {
      return $(this).val();
    }).get();

    if (selection.length === 0 || !bundleData) {
      return;
    }

    const overwrites = $('#yaml-cf-overwrite-count').text();
    if (overwrites && !window.confirm(
      __('Some of these already exist on this website and will be replaced. Continue?', 'yaml-custom-fields')
    )) {
      return;
    }

    const $button = $(this);
    const $message = $('#yaml-cf-import-message');

    showSpinner($message);
    $button.prop('disabled', true);

    $.ajax({
      url: yamlCF.ajax_url,
      type: 'POST',
      data: {
        action: 'yaml_cf_import_bundle',
        nonce: yamlCF.nonce,
        data: JSON.stringify(bundleData),
        selection: selection
      },
      success: function (response) {
        if (response.success) {
          showResult($message, true, response.data.message);
          renderImportResults(response.data);
        } else {
          showResult($message, false, response.data);
        }
      },
      error: function () {
        showResult($message, false, __('The import did not finish. Try again.', 'yaml-custom-fields'));
      },
      complete: function () {
        $button.prop('disabled', false);
      }
    });
  });

  function renderImportResults(data) {
    let html = '<p>' + escapeHtml(data.message) + '</p>';

    if (data.errors && data.errors.length) {
      html += '<h4>' + escapeHtml(__('Problems:', 'yaml-custom-fields')) + '</h4><ul class="yaml-cf-error-list">';
      data.errors.forEach(function (error) {
        html += '<li>' + escapeHtml(error) + '</li>';
      });
      html += '</ul>';
    }

    html += '<p class="yaml-cf-spaced-top">';
    html += '<a href="' + escapeHtml(yamlCF.admin_url + 'admin.php?page=yaml-cf-data-validation') + '" class="button">';
    html += escapeHtml(__('Check images and files', 'yaml-custom-fields')) + '</a></p>';

    $('#yaml-cf-import-results-content').html(html);
    $('#yaml-cf-import-results').show();
  }
});
