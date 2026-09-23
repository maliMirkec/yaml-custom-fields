/**
 * YAML Custom Fields - Admin Page Initialization
 * Handles page-specific initialization for admin pages
 * Replaces inline scripts with proper wp_enqueue_script usage
 */

(function($) {
  'use strict';

  const { __ } = wp.i18n;

  function showMessage(message, type, persistent) {
    if (typeof YamlCF !== 'undefined' && YamlCF.showMessage) {
      YamlCF.showMessage(message, type, persistent);
    }
  }

  // Wait for DOM ready
  $(document).ready(function() {
    // Check if page data exists
    if (typeof yamlCFPageInit === 'undefined') {
      return;
    }

    var pageData = yamlCFPageInit;

    // Display success message if provided
    if (pageData.successMessage) {
      showMessage(pageData.successMessage, 'success');
    }

    // Display error message if provided
    if (pageData.errorMessage) {
      showMessage(pageData.errorMessage, 'error', true);
    }

    // Display notification if provided (for admin page)
    if (pageData.notification) {
      showMessage(pageData.notification.message, pageData.notification.type);
    }

    // Initialize form change tracking if configured
    if (pageData.formTracking && pageData.formTracking.enabled) {
      if (typeof YamlCF !== 'undefined' && YamlCF.initFormChangeTracking) {
        YamlCF.initFormChangeTracking(pageData.formTracking);
      }
    }

    // Initialize refresh templates button if present
    if (pageData.hasRefreshButton) {
      $('#yaml-cf-refresh-templates').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var originalHtml = $btn.html();
        var refreshFailed = __('The list could not be refreshed. Please try again.', 'yaml-custom-fields');

        // Disable button and show loading state
        $btn.prop('disabled', true).empty().append(
          $('<span>').addClass('dashicons dashicons-update spin'),
          document.createTextNode(' ' + __('Refreshing…', 'yaml-custom-fields'))
        );

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            action: 'yaml_cf_refresh_templates',
            nonce: yamlCF.nonce
          },
          success: function(response) {
            if (response.success) {
              // Reload the page to show updated template list
              window.location.reload();
            } else {
              $btn.prop('disabled', false).html(originalHtml);
              showMessage(response.data || refreshFailed, 'error');
            }
          },
          error: function() {
            $btn.prop('disabled', false).html(originalHtml);
            showMessage(refreshFailed, 'error');
          }
        });
      });
    }
  });

})(jQuery);
