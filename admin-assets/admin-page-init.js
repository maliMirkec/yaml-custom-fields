/**
 * YAML Custom Fields - Admin Page Initialization
 * Handles page-specific initialization for admin pages
 * Replaces inline scripts with proper wp_enqueue_script usage
 */

(function($) {
  'use strict';

  const { __ } = wp.i18n;

  // Said beside the Refresh button, in place of its hint, so nothing moves
  function refreshStatus(message) {
    if (typeof YamlCF !== 'undefined' && YamlCF.status) {
      YamlCF.status($('#yaml-cf-refresh-help'), message, 'error');
    }
  }

  // Wait for DOM ready
  $(document).ready(function() {
    // Check if page data exists
    if (typeof yamlCFPageInit === 'undefined') {
      return;
    }

    var pageData = yamlCFPageInit;

    // Save results arrive as notices printed by the server (NotificationManager),
    // not through this data.

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
          YamlCF.icon('refresh').addClass('spin'),
          document.createTextNode(' ' + __('Refreshing…', 'yaml-custom-fields'))
        );

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            action: 'yaml_cf_refresh_templates',
            nonce: yamlCF.nonce,
            admin_nonce: yamlCF.adminNonce
          },
          success: function(response) {
            if (response.success) {
              // Reload the page to show updated template list
              window.location.reload();
            } else {
              $btn.prop('disabled', false).html(originalHtml);
              refreshStatus(response.data || refreshFailed);
            }
          },
          error: function() {
            $btn.prop('disabled', false).html(originalHtml);
            refreshStatus(refreshFailed);
          }
        });
      });
    }
  });

})(jQuery);
