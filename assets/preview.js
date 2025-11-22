/**
 * YAML Custom Fields - Preview Mode JavaScript
 *
 * Handles the frontend preview experience including:
 * - Preview bar with field count and exit button
 * - Field tooltips on click
 * - Optional fields panel sidebar
 */

(function ($) {
  'use strict';

  var YcfPreview = {
    fields: [],
    activeTooltip: null,
    panelOpen: false,

    init: function () {
      if (typeof ycfPreview === 'undefined' || !ycfPreview.isPreview) {
        return;
      }

      this.collectFields();
      this.createPreviewBar();
      this.createFieldsPanel();
      this.bindEvents();
      this.addBodyClass();
    },

    /**
     * Collect all preview fields on the page
     */
    collectFields: function () {
      var self = this;
      self.fields = [];

      $('.ycf-preview-field').each(function () {
        var $field = $(this);
        self.fields.push({
          element: $field,
          name: $field.data('ycf-field'),
          type: $field.data('ycf-type'),
          function: $field.data('ycf-function'),
        });
      });
    },

    /**
     * Create the preview mode indicator bar
     */
    createPreviewBar: function () {
      var fieldCount = this.fields.length;

      var barHtml =
        '<div class="ycf-preview-bar">' +
        '<div class="ycf-preview-bar__title">' +
        '<svg class="ycf-preview-bar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>' +
        '<circle cx="12" cy="12" r="3"></circle>' +
        '</svg>' +
        '<span>' + ycfPreview.i18n.previewMode + '</span>' +
        '</div>' +
        '<div class="ycf-preview-bar__actions">' +
        '<span class="ycf-preview-bar__field-count">' +
        fieldCount +
        ' field' +
        (fieldCount !== 1 ? 's' : '') +
        ' detected</span>' +
        '<button type="button" class="ycf-preview-bar__toggle">Fields Panel</button>' +
        '<button type="button" class="ycf-preview-bar__exit">' +
        ycfPreview.i18n.closePreview +
        '</button>' +
        '</div>' +
        '</div>';

      $('body').prepend(barHtml);
    },

    /**
     * Create the fields panel sidebar
     */
    createFieldsPanel: function () {
      var self = this;
      var listItems = '';

      $.each(this.fields, function (index, field) {
        listItems +=
          '<li class="ycf-preview-panel__item" data-field-index="' +
          index +
          '">' +
          '<div class="ycf-preview-panel__item-name">' +
          self.escapeHtml(field.name) +
          '</div>' +
          '<div class="ycf-preview-panel__item-type">' +
          self.escapeHtml(field.type) +
          ' &bull; ' +
          self.escapeHtml(field.function) +
          '</div>' +
          '</li>';
      });

      var panelHtml =
        '<div class="ycf-preview-panel">' +
        '<div class="ycf-preview-panel__header">' +
        '<h3 class="ycf-preview-panel__title">Custom Fields (' +
        this.fields.length +
        ')</h3>' +
        '</div>' +
        '<ul class="ycf-preview-panel__list">' +
        listItems +
        '</ul>' +
        '</div>';

      $('body').append(panelHtml);
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      var self = this;

      // Exit preview button
      $(document).on('click', '.ycf-preview-bar__exit', function () {
        self.exitPreview();
      });

      // Toggle fields panel
      $(document).on('click', '.ycf-preview-bar__toggle', function () {
        self.togglePanel();
      });

      // Click on preview field to show tooltip
      $(document).on('click', '.ycf-preview-field', function (e) {
        e.preventDefault();
        e.stopPropagation();
        self.showTooltip($(this));
      });

      // Click on panel item to scroll to field
      $(document).on('click', '.ycf-preview-panel__item', function () {
        var index = $(this).data('field-index');
        var field = self.fields[index];
        if (field && field.element.length) {
          self.scrollToField(field.element);
          self.showTooltip(field.element);
        }
      });

      // Close tooltip on outside click
      $(document).on('click', function (e) {
        if (
          self.activeTooltip &&
          !$(e.target).closest('.ycf-preview-tooltip').length &&
          !$(e.target).closest('.ycf-preview-field').length
        ) {
          self.hideTooltip();
        }
      });

      // Close tooltip button
      $(document).on('click', '.ycf-preview-tooltip__close', function () {
        self.hideTooltip();
      });

      // ESC key to close tooltip
      $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
          self.hideTooltip();
        }
      });
    },

    /**
     * Add body class for styling
     */
    addBodyClass: function () {
      $('body').addClass('ycf-preview-active');
    },

    /**
     * Exit preview mode
     */
    exitPreview: function () {
      // Remove preview parameters from URL and reload
      var url = new URL(window.location.href);
      url.searchParams.delete('ycf_preview');
      url.searchParams.delete('ycf_preview_nonce');
      window.location.href = url.toString();
    },

    /**
     * Toggle fields panel
     */
    togglePanel: function () {
      this.panelOpen = !this.panelOpen;
      $('.ycf-preview-panel').toggleClass('is-open', this.panelOpen);
    },

    /**
     * Show tooltip for a field
     */
    showTooltip: function ($field) {
      var self = this;

      // Hide existing tooltip
      this.hideTooltip();

      var fieldName = $field.data('ycf-field');
      var fieldType = $field.data('ycf-type');
      var fieldFunction = $field.data('ycf-function');

      var editUrl =
        ycfPreview.adminUrl +
        'post.php?post=' +
        ycfPreview.postId +
        '&action=edit#yaml-cf-field-' +
        fieldName;

      var tooltipHtml =
        '<div class="ycf-preview-tooltip">' +
        '<div class="ycf-preview-tooltip__header">' +
        '<span class="ycf-preview-tooltip__title">' +
        this.escapeHtml(fieldName) +
        '</span>' +
        '<button type="button" class="ycf-preview-tooltip__close">&times;</button>' +
        '</div>' +
        '<div class="ycf-preview-tooltip__row">' +
        '<span class="ycf-preview-tooltip__label">' +
        ycfPreview.i18n.fieldType +
        '</span>' +
        '<span class="ycf-preview-tooltip__value">' +
        this.escapeHtml(fieldType) +
        '</span>' +
        '</div>' +
        '<div class="ycf-preview-tooltip__row">' +
        '<span class="ycf-preview-tooltip__label">' +
        ycfPreview.i18n.function +
        '</span>' +
        '<span class="ycf-preview-tooltip__value"><code>' +
        this.escapeHtml(fieldFunction) +
        '()</code></span>' +
        '</div>' +
        '<a href="' +
        editUrl +
        '" class="ycf-preview-tooltip__edit" target="_blank">' +
        ycfPreview.i18n.clickToEdit +
        '</a>' +
        '</div>';

      var $tooltip = $(tooltipHtml);
      $('body').append($tooltip);

      // Position tooltip
      var fieldOffset = $field.offset();
      var fieldHeight = $field.outerHeight();
      var tooltipWidth = $tooltip.outerWidth();
      var tooltipHeight = $tooltip.outerHeight();
      var windowWidth = $(window).width();
      var windowHeight = $(window).height();
      var scrollTop = $(window).scrollTop();

      var top = fieldOffset.top + fieldHeight + 10;
      var left = fieldOffset.left;

      // Adjust if tooltip goes off right edge
      if (left + tooltipWidth > windowWidth - 20) {
        left = windowWidth - tooltipWidth - 20;
      }

      // Adjust if tooltip goes off bottom
      if (top + tooltipHeight > scrollTop + windowHeight - 20) {
        top = fieldOffset.top - tooltipHeight - 10;
      }

      $tooltip.css({
        top: top + 'px',
        left: left + 'px',
      });

      this.activeTooltip = $tooltip;

      // Highlight the field
      $field.addClass('ycf-preview-field--active');
    },

    /**
     * Hide active tooltip
     */
    hideTooltip: function () {
      if (this.activeTooltip) {
        this.activeTooltip.remove();
        this.activeTooltip = null;
      }
      $('.ycf-preview-field--active').removeClass('ycf-preview-field--active');
    },

    /**
     * Scroll to a field element
     */
    scrollToField: function ($field) {
      var offset = $field.offset().top - 100;
      $('html, body').animate(
        {
          scrollTop: offset,
        },
        300
      );
    },

    /**
     * Escape HTML for safe output
     */
    escapeHtml: function (text) {
      if (!text) return '';
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },
  };

  // Initialize on DOM ready
  $(document).ready(function () {
    YcfPreview.init();
  });
})(jQuery);
