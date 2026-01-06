/**
 * YAML Custom Fields - Admin Page Initialization
 * Handles page-specific initialization for admin pages
 * Replaces inline scripts with proper wp_enqueue_script usage
 */

(function($) {
  'use strict';

  // Wait for DOM ready
  $(document).ready(function() {
    // Check if page data exists
    if (typeof yamlCFPageInit === 'undefined') {
      return;
    }

    var pageData = yamlCFPageInit;

    // Display success message if provided
    if (pageData.successMessage && typeof YamlCF !== 'undefined' && YamlCF.showMessage) {
      YamlCF.showMessage(pageData.successMessage, 'success');
    }

    // Display error message if provided
    if (pageData.errorMessage && typeof YamlCF !== 'undefined' && YamlCF.showMessage) {
      YamlCF.showMessage(pageData.errorMessage, 'error', true);
    }

    // Display notification if provided (for admin page)
    if (pageData.notification && typeof yamlCF !== 'undefined' && yamlCF.showMessage) {
      yamlCF.showMessage(pageData.notification.message, pageData.notification.type);
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
        var i18n = pageData.i18n || {};

        // Disable button and show loading state
        var refreshingText = i18n.refreshing || 'Refreshing...';
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + refreshingText);

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
              if (typeof yamlCF !== 'undefined' && yamlCF.showMessage) {
                var errorMsg = response.data || i18n.refreshFailed || 'Failed to refresh template list';
                yamlCF.showMessage(errorMsg, 'error');
              }
            }
          },
          error: function() {
            $btn.prop('disabled', false).html(originalHtml);
            if (typeof yamlCF !== 'undefined' && yamlCF.showMessage) {
              var errorMsg = i18n.refreshFailed || 'Failed to refresh template list';
              yamlCF.showMessage(errorMsg, 'error');
            }
          }
        });
      });
    }
  });

})(jQuery);
