/**
 * YAML Custom Fields Admin JavaScript
 * File: admin-assets/admin.js
 */

(function ($) {
  'use strict';

  const { __, sprintf } = wp.i18n;

  const YamlCF = {
    hasMetaBoxChanges: false,
    originalMetaBoxData: {},

    init: function () {
      this.bindEvents();
      this.initMediaUploader();
      this.initMetaBoxChangeTracking();
      this.initFieldGlobalLocal();
      this.initPostboxes();
    },

    bindEvents: function () {
      // Use namespaced events and unbind first to prevent duplicates
      const ns = '.yamlcf';

      // Enable/Disable YAML for templates
      $(document).off('change' + ns, '.yaml-cf-enable-yaml').on('change' + ns, '.yaml-cf-enable-yaml', this.toggleYAML);

      // Toggle Use Global for templates
      $(document).off('change' + ns, '.yaml-cf-use-global').on('change' + ns, '.yaml-cf-use-global', this.toggleUseGlobal);

      // Toggle per-field global/local
      $(document).off('change' + ns, '.yaml-cf-use-global-checkbox').on('change' + ns, '.yaml-cf-use-global-checkbox', this.toggleFieldGlobalLocal);

      // Block Controls
      $(document).off('click' + ns, '.yaml-cf-add-block').on('click' + ns, '.yaml-cf-add-block', this.addBlock);
      $(document).off('click' + ns, '.yaml-cf-remove-block').on('click' + ns, '.yaml-cf-remove-block', this.removeBlock);
      $(document).off('click' + ns, '.yaml-cf-move-up').on('click' + ns, '.yaml-cf-move-up', this.moveBlockUp);
      $(document).off('click' + ns, '.yaml-cf-move-down').on('click' + ns, '.yaml-cf-move-down', this.moveBlockDown);

      // Clear Media
      $(document).off('click' + ns, '.yaml-cf-clear-media').on('click' + ns, '.yaml-cf-clear-media', this.clearMedia);

      // File List Controls
      $(document).off('click' + ns, '.yaml-cf-remove-file-list-item').on('click' + ns, '.yaml-cf-remove-file-list-item', this.removeFileListItem);

      // Reset All Data
      $(document).off('click' + ns, '.yaml-cf-reset-data').on('click' + ns, '.yaml-cf-reset-data', this.resetAllData);

      // Import Settings
      $(document).off('click' + ns, '.yaml-cf-import-settings-trigger').on('click' + ns, '.yaml-cf-import-settings-trigger', this.triggerImport);
      $(document).off('change' + ns, '#yaml-cf-import-settings-file').on('change' + ns, '#yaml-cf-import-settings-file', this.importSettings);

      // Import Post Data
      $(document).off('change' + ns, 'input[name="yaml_cf_import_file_hidden"]').on('change' + ns, 'input[name="yaml_cf_import_file_hidden"]', this.importPostData);
      $(document).off('click' + ns, '.yaml-cf-import-trigger').on('click' + ns, '.yaml-cf-import-trigger', function () {
        $('#' + $(this).data('target')).trigger('click');
      });

      // Code Snippet Copy
      $(document).off('click' + ns, '.yaml-cf-copy-snippet').on('click' + ns, '.yaml-cf-copy-snippet', this.copySnippet);
      $(document).off('mouseenter' + ns, '.yaml-cf-copy-snippet').on('mouseenter' + ns, '.yaml-cf-copy-snippet', this.showSnippetPopover);
      $(document).off('mouseleave' + ns, '.yaml-cf-copy-snippet').on('mouseleave' + ns, '.yaml-cf-copy-snippet', this.hideSnippetPopover);

      // Template Global Controls
      $(document).off('change' + ns, '.yaml-cf-use-template-global-checkbox').on('change' + ns, '.yaml-cf-use-template-global-checkbox', this.toggleTemplateGlobal);
      $(document).off('click' + ns, '.yaml-cf-enable-override').on('click' + ns, '.yaml-cf-enable-override', this.enableOverride);
      $(document).off('click' + ns, '.yaml-cf-reset-override').on('click' + ns, '.yaml-cf-reset-override', this.resetOverride);
    },

    toggleYAML: function () {
      const $checkbox = $(this);
      const template = $checkbox.data('template');
      const templateName = $checkbox.data('template-name') || template;
      const hasSchema = String($checkbox.data('has-schema')) === '1';
      const enabled = $checkbox.is(':checked');
      const $row = $checkbox.closest('.yaml-cf-row');

      // Both states are rendered by PHP; the row class decides which one is visible
      const applyState = function (isEnabled) {
        $row.toggleClass('is-enabled', isEnabled).toggleClass('is-disabled', !isEnabled);
      };

      const revert = function (message) {
        $checkbox.prop('checked', !enabled);
        applyState(!enabled);
        YamlCF.showMessage(message, 'error');
      };

      applyState(enabled);
      $checkbox.prop('disabled', true);

      $.ajax({
        url: yamlCF.ajax_url,
        type: 'POST',
        data: {
          action: 'yaml_cf_save_template_settings',
          nonce: yamlCF.nonce,
          template: template,
          enabled: enabled,
        },
        success: function (response) {
          if (!response.success) {
            /* translators: %s: template name */
            revert(sprintf(__('Could not change custom fields for %s. Please try again.', 'yaml-custom-fields'), templateName));
            return;
          }

          let message;
          if (!enabled) {
            /* translators: %s: template name */
            message = sprintf(__('Custom fields turned off for %s. Existing content is kept.', 'yaml-custom-fields'), templateName);
          } else if (hasSchema) {
            /* translators: %s: template name */
            message = sprintf(__('Custom fields turned on for %s.', 'yaml-custom-fields'), templateName);
          } else {
            /* translators: %s: template name */
            message = sprintf(__('Custom fields turned on for %s. Next, create its schema.', 'yaml-custom-fields'), templateName);
          }
          YamlCF.showMessage(message, 'success');
        },
        error: function () {
          revert(__('Could not reach the server. Check the connection and try again.', 'yaml-custom-fields'));
        },
        complete: function () {
          $checkbox.prop('disabled', false);
        },
      });
    },

    toggleUseGlobal: function () {
      const $checkbox = $(this);
      const template = $checkbox.data('template');
      const useGlobal = $checkbox.is(':checked');

      $.ajax({
        url: yamlCF.ajax_url,
        type: 'POST',
        data: {
          action: 'yaml_cf_toggle_use_global',
          nonce: yamlCF.nonce,
          template: template,
          use_global: useGlobal,
        },
        success: function (response) {
          if (response.success) {
            YamlCF.showMessage(__('Site details setting saved.', 'yaml-custom-fields'), 'success');
          } else {
            $checkbox.prop('checked', !useGlobal);
            YamlCF.showMessage(__('This setting could not be saved. Please try again.', 'yaml-custom-fields'), 'error');
          }
        },
        error: function () {
          $checkbox.prop('checked', !useGlobal);
          YamlCF.showMessage(__('This setting could not be saved. Please try again.', 'yaml-custom-fields'), 'error');
        },
      });
    },

    toggleTemplateGlobal: function () {
      const $checkbox = $(this);
      const $fieldsContainer = $checkbox.closest('.yaml-cf-template-global-section').find('.yaml-cf-template-global-fields');

      if ($checkbox.is(':checked')) {
        $fieldsContainer.slideDown(200);
      } else {
        $fieldsContainer.slideUp(200);
      }
    },

    toggleFieldGlobalLocal: function () {
      const $checkbox = $(this);
      const $dualField = $checkbox.closest('.yaml-cf-dual-field');
      const $localPart = $dualField.find('.yaml-cf-local-part');
      const useGlobal = $checkbox.is(':checked');

      if (useGlobal) {
        // Block all interaction at the container level using CSS
        $localPart.addClass('yaml-cf-container-disabled');
      } else {
        // Re-enable interaction
        $localPart.removeClass('yaml-cf-container-disabled');
      }
    },

    initFieldGlobalLocal: function () {
      const self = this;
      $('.yaml-cf-use-global-checkbox').each(function () {
        self.toggleFieldGlobalLocal.call(this);
      });
    },

    initPostboxes: function () {
      const $metaBox = $('#yaml-cf-meta-box');

      if (!$metaBox.length) {
        return;
      }

      // Restore collapsed state from localStorage
      const isCollapsed = localStorage.getItem('yaml_cf_metabox_collapsed') === 'true';
      if (isCollapsed) {
        $metaBox.addClass('closed');
      }

      // Toggle on header click
      $metaBox.find('.hndle').on('click', function() {
        // Save state to localStorage
        const isClosed = $metaBox.hasClass('closed');
        localStorage.setItem('yaml_cf_metabox_collapsed', isClosed);
      });
    },

    enableOverride: function () {
      const $button = $(this);
      const fieldName = $button.data('field');
      const $fieldContainer = $button.closest('.yaml-cf-template-global-field');
      const $fieldHeader = $fieldContainer.find('.yaml-cf-field-header');
      const $fieldDisplay = $fieldContainer.find('.yaml-cf-field-display');

      // Change indicator to override
      $fieldHeader.find('.yaml-cf-global-indicator').replaceWith(
        $('<span>', { 'class': 'yaml-cf-override-indicator', text: '⚠️ ' + __('Changed for this page', 'yaml-custom-fields') })
      );

      // Change button to reset
      $button.replaceWith(
        $('<button>', {
          type: 'button',
          'class': 'button button-small yaml-cf-reset-override',
          'data-field': fieldName,
          text: __('Use shared value', 'yaml-custom-fields')
        })
      );

      // Enable editing - remove readonly class
      $fieldDisplay.find('.yaml-cf-readonly').removeClass('yaml-cf-readonly');
      $fieldDisplay.find('input, textarea, select').prop('disabled', false).css({
        'opacity': '1',
        'background-color': '#fff',
        'cursor': 'text',
        'pointer-events': 'auto'
      });

      // Add hidden field to mark as override
      const overrideName = 'yaml_cf_template_global_override[' + fieldName + ']';
      const hasOverrideInput = $fieldDisplay.find('input').filter(function() {
        return $(this).attr('name') === overrideName;
      }).length > 0;
      if (!hasOverrideInput) {
        $fieldDisplay.prepend($('<input>', { type: 'hidden', name: overrideName, value: '1' }));
      }

      // Update field names to use override namespace
      $fieldDisplay.find('input, textarea, select').each(function() {
        const $field = $(this);
        const name = $field.attr('name');
        if (name && name.indexOf('yaml_cf[') === 0 && name.indexOf('_template_global_override') === -1) {
          const newName = name.replace('yaml_cf[', 'yaml_cf[_template_global_override][' + fieldName + '][');
          $field.attr('name', newName);
        }
      });
    },

    resetOverride: function () {
      const $button = $(this);
      const fieldName = $button.data('field');

      if (!confirm(__('Go back to the shared value? The value typed for this page will be lost.', 'yaml-custom-fields'))) {
        return;
      }

      // Reload the page to reset the field
      // Note: A better implementation would use AJAX to reload just the field
      location.reload();
    },

    addBlock: function () {
      const $container = $(this).closest('.yaml-cf-block-container');
      const $select = $container.find('.yaml-cf-block-type-select');
      const blockType = $select.val();

      if (!blockType) {
        alert(__('Choose a section type first.', 'yaml-custom-fields'));
        return;
      }

      const $blockList = $container.find('.yaml-cf-block-list');
      const fieldName = $container.data('field-name');
      const index = $blockList.find('.yaml-cf-block-item').length;

      // Generate unique ID for this block instance
      const uniqueId =
        Date.now() + '_' + Math.random().toString(36).substr(2, 9);

      // Get block definition from schema
      let blockDef = null;
      if (yamlCF.schema && yamlCF.schema.fields) {
        for (let field of yamlCF.schema.fields) {
          if (field.name === fieldName && field.blocks) {
            for (let block of field.blocks) {
              if (block.name === blockType) {
                blockDef = block;
                break;
              }
            }
            break;
          }
        }
      }

      if (!blockDef) {
        alert(__('This section type no longer exists in the schema.', 'yaml-custom-fields'));
        return;
      }

      const blockLabel = blockDef.label || blockType;

      // Create new block item
      const $blockItem = $('<div>', {
        class: 'yaml-cf-block-item',
        'data-block-type': blockType,
      });

      const $header = $('<div>', { class: 'yaml-cf-block-header' });
      $header.append($('<strong>').text(blockLabel));

      const $actions = $('<span>', { class: 'yaml-cf-block-actions' });
      $actions.append(
        $('<button>', {
          type: 'button',
          class: 'button button-small yaml-cf-move-up',
          title: __('Move up', 'yaml-custom-fields'),
          html: '<span class="dashicons dashicons-arrow-up-alt"></span>',
        })
      );
      $actions.append(
        $('<button>', {
          type: 'button',
          class: 'button button-small yaml-cf-move-down',
          title: __('Move down', 'yaml-custom-fields'),
          html: '<span class="dashicons dashicons-arrow-down-alt"></span>',
        })
      );
      $actions.append(
        $('<button>', {
          type: 'button',
          class: 'button button-small yaml-cf-remove-block',
          text: __('Remove', 'yaml-custom-fields'),
        })
      );
      $header.append($actions);

      $blockItem.append($header);
      $blockItem.append(
        $('<input>', {
          type: 'hidden',
          name: 'yaml_cf[' + fieldName + '][' + index + '][type]',
          value: blockType,
        })
      );

      // Add fields from block definition
      if (blockDef.fields && blockDef.fields.length > 0) {
        const $fieldsContainer = $('<div>', {
          class: 'yaml-cf-block-fields',
        });

        for (let blockField of blockDef.fields) {
          const $field = $('<div>', { class: 'yaml-cf-field', 'data-type': blockField.type });
          const blockFieldId = 'ycf_' + uniqueId + '_' + blockField.name;

          $field.append(
            $('<label>', {
              for: blockFieldId,
              text: blockField.label || blockField.name,
            })
          );

          // Help text for writers (the optional "description" in the schema)
          if (blockField.description) {
            $field.append($('<p>', { 'class': 'yaml-cf-field-help', text: blockField.description }));
          }

          // Render field based on type
          if (blockField.type === 'boolean') {
            $field.append(
              $('<input>', {
                type: 'checkbox',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                value: '1',
              })
            );
          } else if (blockField.type === 'rich-text') {
            // For rich-text, we need to use WordPress editor which requires page reload
            $field.append(
              $('<div>', { 'class': 'yaml-cf-richtext-notice' }).append(
                $('<strong>').text('ℹ️ ' + __('Text editor', 'yaml-custom-fields')),
                $('<br>'),
                document.createTextNode(__('Save this post to see the full text editor here.', 'yaml-custom-fields'))
              )
            );
            // Add hidden input to preserve the field structure
            $field.append(
              $('<input>', {
                type: 'hidden',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                value: '',
              })
            );
          } else if (
            blockField.type === 'text' ||
            blockField.type === 'textarea'
          ) {
            $field.append(
              $('<textarea>', {
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                rows: 5,
                class: 'large-text',
              })
            );
          } else if (blockField.type === 'code') {
            const options = blockField.options || {};
            const language = options.language || 'html';
            $field.append(
              $('<textarea>', {
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                rows: 10,
                class: 'large-text code',
                'data-language': language,
              })
            );
          } else if (blockField.type === 'number') {
            const options = blockField.options || {};
            $field.append(
              $('<input>', {
                type: 'number',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                class: 'small-text',
                min: options.min || '',
                max: options.max || '',
              })
            );
          } else if (blockField.type === 'date') {
            const options = blockField.options || {};
            const hasTime = options.time || false;
            $field.append(
              $('<input>', {
                type: hasTime ? 'datetime-local' : 'date',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
              })
            );
          } else if (blockField.type === 'select') {
            const options = blockField.options || {};
            const multiple = blockField.multiple || false;
            // Check for values in options.values first, then fallback to root level values
            const values = (options.values && Array.isArray(options.values)) ? options.values : (blockField.values || []);

            const $select = $('<select>', {
              name:
                'yaml_cf[' +
                fieldName +
                '][' +
                index +
                '][' +
                blockField.name +
                ']' +
                (multiple ? '[]' : ''),
              id: blockFieldId,
              multiple: multiple,
            });

            $select.append($('<option>', { value: '', text: __('-- Select --', 'yaml-custom-fields') }));

            if (Array.isArray(values)) {
              values.forEach(function (option) {
                const optValue =
                  typeof option === 'object' ? option.value || '' : option;
                const optLabel =
                  typeof option === 'object' ? option.label || optValue : option;
                $select.append(
                  $('<option>', { value: optValue, text: optLabel })
                );
              });
            }

            $field.append($select);
          } else if (blockField.type === 'taxonomy') {
            // Taxonomy field - renders as select dropdown
            const options = blockField.options || {};
            const taxonomy = options.taxonomy || 'category';
            const multiple = blockField.multiple || false;

            const $select = $('<select>', {
              name:
                'yaml_cf[' +
                fieldName +
                '][' +
                index +
                '][' +
                blockField.name +
                ']' +
                (multiple ? '[]' : ''),
              id: blockFieldId,
              class: 'regular-text',
              multiple: multiple,
            });

            const fieldLabel = blockField.label || blockField.name;
            $select.append($('<option>', { value: '', text: sprintf(
              /* translators: %s: field label */
              __('-- Select %s --', 'yaml-custom-fields'), fieldLabel) }));

            // Get taxonomy terms from pre-loaded data
            if (typeof yamlCF !== 'undefined' && yamlCF.taxonomyTerms && yamlCF.taxonomyTerms[taxonomy]) {
              yamlCF.taxonomyTerms[taxonomy].forEach(function (term) {
                $select.append(
                  $('<option>', { value: term.id, text: term.name })
                );
              });
            }

            $field.append($select);
          } else if (blockField.type === 'post_type') {
            // Post type field - renders as select dropdown
            const $select = $('<select>', {
              name:
                'yaml_cf[' +
                fieldName +
                '][' +
                index +
                '][' +
                blockField.name +
                ']',
              id: blockFieldId,
              class: 'regular-text',
            });

            const fieldLabel = blockField.label || blockField.name;
            $select.append($('<option>', { value: '', text: sprintf(
              /* translators: %s: field label */
              __('-- Select %s --', 'yaml-custom-fields'), fieldLabel) }));

            // Get post types from pre-loaded data
            if (typeof yamlCF !== 'undefined' && yamlCF.postTypes && Array.isArray(yamlCF.postTypes)) {
              yamlCF.postTypes.forEach(function (postType) {
                $select.append(
                  $('<option>', { value: postType.name, text: postType.label })
                );
              });
            }

            $field.append($select);
          } else if (blockField.type === 'data_object') {
            // Data object field - renders as select dropdown
            const options = blockField.options || {};
            const objectType = options.object_type || '';
            const multiple = blockField.multiple || false;

            if (!objectType) {
              $field.append(
                $('<p>', {
                  'class': 'yaml-cf-status-bad',
                  text: __('This field needs a list to choose from. Add object_type to the schema.', 'yaml-custom-fields')
                })
              );
            } else {
              const $select = $('<select>', {
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']' +
                  (multiple ? '[]' : ''),
                id: blockFieldId,
                class: 'regular-text',
                multiple: multiple,
              });

              const fieldLabel = blockField.label || blockField.name;
              $select.append($('<option>', { value: '', text: sprintf(
              /* translators: %s: field label */
              __('-- Select %s --', 'yaml-custom-fields'), fieldLabel) }));

              // Get data object entries from pre-loaded data
              if (typeof yamlCF !== 'undefined' && yamlCF.dataObjects && yamlCF.dataObjects[objectType]) {
                yamlCF.dataObjects[objectType].forEach(function (entry) {
                  $select.append(
                    $('<option>', { value: entry.id, text: entry.label })
                  );
                });
              }

              $field.append($select);
            }
          } else if (blockField.type === 'image') {
            // Image upload field
            $field.append(
              $('<input>', {
                type: 'hidden',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                value: '',
              })
            );
            const $mediaButtons = $('<div>', {
              class: 'yaml-cf-media-buttons',
            });
            $mediaButtons.append(
              $('<button>', {
                type: 'button',
                class: 'button yaml-cf-upload-image',
                'data-target': blockFieldId,
                text: __('Choose image', 'yaml-custom-fields'),
              })
            );
            $field.append($mediaButtons);
          } else if (blockField.type === 'file') {
            // File upload field
            $field.append(
              $('<input>', {
                type: 'hidden',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                value: '',
              })
            );
            const $mediaButtons = $('<div>', {
              class: 'yaml-cf-media-buttons',
            });
            $mediaButtons.append(
              $('<button>', {
                type: 'button',
                class: 'button yaml-cf-upload-file',
                'data-target': blockFieldId,
                text: __('Choose file', 'yaml-custom-fields'),
              })
            );
            $field.append($mediaButtons);
          } else if (blockField.type === 'string') {
            const options = blockField.options || {};
            $field.append(
              $('<input>', {
                type: 'text',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                class: 'regular-text',
                minlength: options.minlength || '',
                maxlength: options.maxlength || '',
              })
            );
          } else {
            // Default to text input for unknown types
            $field.append(
              $('<input>', {
                type: 'text',
                name:
                  'yaml_cf[' +
                  fieldName +
                  '][' +
                  index +
                  '][' +
                  blockField.name +
                  ']',
                id: blockFieldId,
                class: 'regular-text',
              })
            );
          }

          $fieldsContainer.append($field);
        }

        $blockItem.append($fieldsContainer);
      }

      $blockList.append($blockItem);
      // Keep the select value for easier adding of multiple blocks of same type
    },

    removeBlock: function (e) {
      e.preventDefault();
      e.stopPropagation();

      $(this)
        .closest('.yaml-cf-block-item')
        .fadeOut(300, function () {
          $(this).remove();
          // Re-index remaining blocks
          YamlCF.reindexBlocks();
        });

      // Show notification to remind user to save
      YamlCF.showMessage(
        __("Section removed. Click Update to save the change.", 'yaml-custom-fields'),
        'warning'
      );
    },

    reindexBlocks: function () {
      $('.yaml-cf-block-container').each(function () {
        const fieldName = $(this).data('field-name');
        $(this)
          .find('.yaml-cf-block-item')
          .each(function (index) {
            // Update input names with new index
            $(this)
              .find('input, textarea, select')
              .each(function () {
                const $input = $(this);
                const name = $input.attr('name');
                if (name) {
                  const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                  $input.attr('name', newName);
                }
              });
          });
      });
    },

    moveBlockUp: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $block = $(this).closest('.yaml-cf-block-item');
      const $prev = $block.prev('.yaml-cf-block-item');

      if ($prev.length) {
        $block.insertBefore($prev);
        YamlCF.reindexBlocks();
      }
    },

    moveBlockDown: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $block = $(this).closest('.yaml-cf-block-item');
      const $next = $block.next('.yaml-cf-block-item');

      if ($next.length) {
        $block.insertAfter($next);
        YamlCF.reindexBlocks();
      }
    },

    initMediaUploader: function () {
      // Image Upload
      $(document).on('click', '.yaml-cf-upload-image', function (e) {
        e.preventDefault();

        const $button = $(this);
        const targetId = $button.data('target');
        const currentValue = $('#' + targetId).val();

        // Create a new frame instance to avoid conflicts
        const mediaUploader = wp.media({
          title: __('Choose an image', 'yaml-custom-fields'),
          button: {
            text: __('Use this image', 'yaml-custom-fields'),
          },
          multiple: false,
          library: {
            type: 'image',
          },
          frame: 'select',
        });

        // Reset and pre-select the current image if one exists
        mediaUploader.on('open', function () {
          const selection = mediaUploader.state().get('selection');
          // Clear any existing selection first
          selection.reset();

          // Pre-select current image if one exists
          if (currentValue) {
            const attachment = wp.media.attachment(currentValue);
            attachment.fetch();
            selection.add(attachment ? [attachment] : []);
          }
        });

        mediaUploader.on('select', function () {
          const attachment = mediaUploader
            .state()
            .get('selection')
            .first()
            .toJSON();

          // Store attachment ID instead of URL
          const $field = $('#' + targetId);
          $field.val(attachment.id);

          // Trigger change event to ensure form knows it's been modified
          $field.trigger('change');

          // Update preview - look for preview after the buttons container
          const $buttonsDiv = $button.closest('.yaml-cf-media-buttons');
          const $preview = $buttonsDiv.siblings('.yaml-cf-image-preview');

          if ($preview.length) {
            // Update existing preview
            $preview.find('img').attr('src', attachment.url);
          } else {
            // Create new preview after the buttons container
            $buttonsDiv.after(
              $('<div>', { 'class': 'yaml-cf-image-preview' }).append(
                $('<img>', { src: attachment.url, 'class': 'yaml-cf-image-preview-img', alt: '' })
              )
            );
          }

          // Add clear button if it doesn't exist
          if (!$buttonsDiv.find('.yaml-cf-clear-media').length) {
            $buttonsDiv.append(
              $('<button>', {
                type: 'button',
                class: 'button yaml-cf-clear-media',
                'data-target': targetId,
                text: __('Remove', 'yaml-custom-fields'),
              })
            );
          }
        });

        mediaUploader.open();
      });

      // File Upload
      $(document).on('click', '.yaml-cf-upload-file', function (e) {
        e.preventDefault();

        const $button = $(this);
        const targetId = $button.data('target');

        // Always create a new media uploader instance to avoid target conflicts
        const mediaUploader = wp.media({
          title: __('Choose a file', 'yaml-custom-fields'),
          button: {
            text: __('Use this file', 'yaml-custom-fields'),
          },
          multiple: false,
        });

        mediaUploader.on('select', function () {
          const attachment = mediaUploader
            .state()
            .get('selection')
            .first()
            .toJSON();
          // Store attachment ID instead of URL
          $('#' + targetId).val(attachment.id);

          // Update file name display - look for display after the buttons container
          const $buttonsDiv = $button.closest('.yaml-cf-media-buttons');
          const $fileDisplay = $buttonsDiv.siblings('.yaml-cf-file-name');

          if ($fileDisplay.length) {
            // Update existing display
            $fileDisplay.text(attachment.filename);
          } else {
            // Create new display after the buttons container
            $buttonsDiv.after(
              '<div class="yaml-cf-file-name">' +
                attachment.filename +
                '</div>'
            );
          }

          // Add clear button if it doesn't exist
          if (!$buttonsDiv.find('.yaml-cf-clear-media').length) {
            $buttonsDiv.append(
              $('<button>', {
                type: 'button',
                class: 'button yaml-cf-clear-media',
                'data-target': targetId,
                text: __('Remove', 'yaml-custom-fields'),
              })
            );
          }
        });

        mediaUploader.open();
      });

      // File List Upload (file field with list: true)
      $(document).on('click', '.yaml-cf-add-file-list-item', function (e) {
        e.preventDefault();

        const $button = $(this);
        const targetName = $button.data('target-name');
        const $list = $button.siblings('.yaml-cf-file-list-items');

        const mediaUploader = wp.media({
          title: __('Choose files', 'yaml-custom-fields'),
          button: {
            text: __('Add files', 'yaml-custom-fields'),
          },
          multiple: true,
        });

        mediaUploader.on('select', function () {
          const attachments = mediaUploader
            .state()
            .get('selection')
            .toJSON();

          attachments.forEach(function (attachment) {
            const $item = $('<div>', { class: 'yaml-cf-file-list-item' });
            $('<input>', {
              type: 'hidden',
              name: 'yaml_cf[' + targetName + '][]',
              value: attachment.id,
            }).appendTo($item);
            $('<div>', {
              class: 'yaml-cf-file-name',
              text: attachment.filename,
            }).appendTo($item);
            $('<button>', {
              type: 'button',
              class: 'button button-small yaml-cf-remove-file-list-item',
              text: __('Remove', 'yaml-custom-fields'),
            }).appendTo($item);
            $list.append($item);
          });
        });

        mediaUploader.open();
      });
    },

    removeFileListItem: function (e) {
      e.preventDefault();
      e.stopPropagation();

      $(this).closest('.yaml-cf-file-list-item').remove();
    },

    clearMedia: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $button = $(this);
      const targetId = $button.data('target');
      const $field = $('#' + targetId);

      // Clear the hidden input value
      $field.val('');

      // Remove the preview/filename display - look for siblings of the buttons container
      const $buttonsDiv = $button.closest('.yaml-cf-media-buttons');
      $buttonsDiv.siblings('.yaml-cf-image-preview').remove();
      $buttonsDiv.siblings('.yaml-cf-file-name').remove();

      // Also try finding within the field container (for backwards compatibility)
      $button
        .closest('.yaml-cf-field')
        .find('.yaml-cf-image-preview')
        .remove();
      $button
        .closest('.yaml-cf-field')
        .find('.yaml-cf-file-name')
        .remove();

      // Remove the clear button itself
      $button.remove();

      // Show notification to remind user to save
      YamlCF.showMessage(
        __("Click Update to save the change.", 'yaml-custom-fields'),
        'warning'
      );
    },

    resetAllData: function (e) {
      e.preventDefault();

      const $button = $(this);

      if (
        !confirm(
          '⚠️ ' + __('This empties every field on this page. Shared fields and site details stay as they are.\n\nClick Update afterwards to save the change.\n\nContinue?', 'yaml-custom-fields')
        )
      ) {
        return;
      }

      // Reset only local fields (not global or template global)
      // First, get all inputs that are NOT in template global parts
      const $localInputs = $('#yaml-cf-meta-box > .inside > .yaml-cf-fields')
        .find('input, textarea, select')
        .filter(function() {
          // Exclude inputs that are inside .yaml-cf-template-global-part
          return $(this).closest('.yaml-cf-template-global-part').length === 0;
        });

      $localInputs.each(function () {
        const $input = $(this);
        const type = $input.attr('type');

        if (type === 'checkbox') {
          $input.prop('checked', false);
        } else if (
          type === 'hidden' &&
          ($input
            .closest('.yaml-cf-field')
            .find('.yaml-cf-upload-image').length ||
            $input
              .closest('.yaml-cf-field')
              .find('.yaml-cf-upload-file').length)
        ) {
          // Clear image/file fields
          $input.val('');
        } else if ($input.is('select')) {
          $input.prop('selectedIndex', 0);
        } else if (
          !type ||
          type === 'text' ||
          type === 'number' ||
          type === 'date' ||
          type === 'datetime-local' ||
          $input.is('textarea')
        ) {
          $input.val('');
        }
      });

      // Clear image previews and file names (but exclude template global parts)
      $('#yaml-cf-meta-box > .inside > .yaml-cf-fields .yaml-cf-image-preview')
        .not('.yaml-cf-template-global-part .yaml-cf-image-preview')
        .remove();
      $('#yaml-cf-meta-box > .inside > .yaml-cf-fields .yaml-cf-file-name')
        .not('.yaml-cf-template-global-part .yaml-cf-file-name')
        .remove();
      $('#yaml-cf-meta-box > .inside > .yaml-cf-fields .yaml-cf-clear-media')
        .not('.yaml-cf-template-global-part .yaml-cf-clear-media')
        .remove();

      // Clear WordPress editors (if any)
      if (typeof tinymce !== 'undefined') {
        $('#yaml-cf-meta-box > .inside > .yaml-cf-fields')
          .find('textarea')
          .each(function () {
            const editorId = $(this).attr('id');
            if (editorId && tinymce.get(editorId)) {
              tinymce.get(editorId).setContent('');
            }
          });
      }

      // Remove all blocks
      $('#yaml-cf-meta-box > .inside > .yaml-cf-fields .yaml-cf-block-item').remove();

      alert(
        __('All fields on this page are now empty. Click Update to save this change.', 'yaml-custom-fields')
      );
    },

    triggerImport: function (e) {
      e.preventDefault();
      $('#yaml-cf-import-settings-file').click();
    },

    importSettings: function (e) {
      const file = e.target.files[0];
      if (!file) return;

      // Validate file type
      if (!file.name.endsWith('.json')) {
        YamlCF.showMessage(__('Choose a .json file exported from YAML Custom Fields.', 'yaml-custom-fields'), 'error');
        return;
      }

      // Confirm import
      const confirmMsg = __('This imports schemas and settings from the file. It can change the current setup.\n\nOK: replace the current settings.\nCancel: add them to the current settings.\n\nContinue?', 'yaml-custom-fields');

      if (!confirm(confirmMsg)) {
        // User wants to merge
        const mergeConfirm = confirm(
          __('Add the imported settings to the current ones?', 'yaml-custom-fields')
        );
        if (!mergeConfirm) {
          e.target.value = ''; // Reset file input
          return;
        }
      }

      const merge = !confirm(
        __('Replace all current settings with the imported ones? Choose Cancel to add them instead.', 'yaml-custom-fields')
      );

      const reader = new FileReader();
      reader.onload = function (evt) {
        try {
          const importData = JSON.parse(evt.target.result);

          $.ajax({
            url: yamlCF.ajax_url,
            type: 'POST',
            data: {
              action: 'yaml_cf_import_settings',
              nonce: yamlCF.nonce,
              data: JSON.stringify(importData),
              merge: merge,
            },
            success: function (response) {
              if (response.success) {
                const info = response.data;
                let message = __('Settings imported.', 'yaml-custom-fields');
                if (info.errors && info.errors.length > 0) {
                  message = __('Settings imported, but some parts were skipped:', 'yaml-custom-fields') + '\n- ' + info.errors.join('\n- ');
                }
                if (info.imported_from && info.imported_from !== 'unknown') {
                  message += '\n\n' + __('From website:', 'yaml-custom-fields') + ' ' + info.imported_from;
                }
                if (info.exported_at && info.exported_at !== 'unknown') {
                  message += '\n' + __('Exported on:', 'yaml-custom-fields') + ' ' + info.exported_at;
                }
                alert(message);
                YamlCF.showMessage(
                  __('Settings imported.', 'yaml-custom-fields'),
                  'success'
                );

                // Reload page to show updated settings
                setTimeout(function () {
                  window.location.reload();
                }, 1500);
              } else {
                YamlCF.showMessage(
                  __('The settings could not be imported:', 'yaml-custom-fields') + ' ' +
                    (response.data || __('Something went wrong.', 'yaml-custom-fields')),
                  'error'
                );
              }
            },
            error: function () {
              YamlCF.showMessage(
                __('The import did not reach the server. Check the connection and try again.', 'yaml-custom-fields'),
                'error'
              );
            },
            complete: function () {
              // Reset file input
              $('#yaml-cf-import-settings-file').val('');
            },
          });
        } catch (err) {
          YamlCF.showMessage(__('This file could not be read:', 'yaml-custom-fields') + ' ' + err.message, 'error');
          $('#yaml-cf-import-settings-file').val('');
        }
      };

      reader.readAsText(file);
    },

    initFormChangeTracking: function (config) {
      const self = this;
      const $container = $(config.container);

      // Exit if container doesn't exist
      if (!$container.length) return;

      const storageKey = config.storageKey || 'formData';
      const hasChangesKey = config.hasChangesKey || 'hasFormChanges';

      // Initialize storage
      if (!self[storageKey]) {
        self[storageKey] = {};
      }

      // Capture form state
      function captureFormState() {
        const data = {};
        $container
          .find(config.fieldsSelector)
          .find('input, textarea, select')
          .each(function () {
            const $field = $(this);
            const name = $field.attr('name');
            if (!name) return;

            if ($field.attr('type') === 'checkbox') {
              data[name] = $field.is(':checked');
            } else if ($field.is('select') && $field.prop('multiple')) {
              data[name] = JSON.stringify($field.val() || []);
            } else {
              data[name] = $field.val();
            }
          });
        return data;
      }

      // Check for changes
      function checkFormChanges() {
        const currentData = captureFormState();
        const changed =
          JSON.stringify(self[storageKey]) !== JSON.stringify(currentData);

        if (changed !== self[hasChangesKey]) {
          self[hasChangesKey] = changed;
          toggleIndicator(changed);

          // Integrate with WordPress's own save warning (if enabled)
          if (changed && config.gutenbergSupport) {
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
              // Gutenberg - check if core/editor store exists
              const editor = wp.data.dispatch('core/editor');
              if (editor && editor.editPost) {
                editor.editPost({ meta: { _ycf_changed: Date.now() } });
              }
            } else {
              // Classic editor
              $('#post').trigger('change');
            }
          }
        }
      }

      // Show/hide indicator
      function toggleIndicator(show) {
        if (show) {
          YamlCF.showMessage(config.message, 'warning', true);
        } else {
          YamlCF.hideMessage('warning');
        }
      }

      // Capture initial state after page loads
      setTimeout(function () {
        self[storageKey] = captureFormState();
      }, config.captureDelay || 1000);

      // Watch for changes
      $container.on('input change', 'input, textarea, select', function () {
        checkFormChanges();
      });

      // Clear changes flag on form submit and sync TinyMCE editors
      $(config.submitSelector).on('submit', function () {
        // Sync all TinyMCE editors to their textareas before submit
        if (typeof tinymce !== 'undefined') {
          tinymce.triggerSave();
        }
        self[hasChangesKey] = false;
        toggleIndicator(false);
      });

      // beforeunload warning (if enabled)
      if (config.beforeUnloadMessage) {
        $(window).on('beforeunload', function (e) {
          if (self[hasChangesKey]) {
            e.returnValue = config.beforeUnloadMessage;
            return config.beforeUnloadMessage;
          }
        });
      }

      // Gutenberg save handler (if enabled)
      if (config.gutenbergSupport) {
        if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
          let wasSaving = false;
          wp.data.subscribe(function () {
            const isSaving = wp.data.select('core/editor').isSavingPost();
            if (!wasSaving && isSaving) {
              // Save is starting - sync TinyMCE editors
              if (typeof tinymce !== 'undefined') {
                tinymce.triggerSave();
              }
            }
            if (wasSaving && !isSaving) {
              // Save just completed
              self[hasChangesKey] = false;
              self[storageKey] = captureFormState();
              toggleIndicator(false);
            }
            wasSaving = isSaving;
          });
        }
      }
    },

    initMetaBoxChangeTracking: function () {
      const $metaBox = $('#yaml-cf-meta-box');

      // Only run on post editor
      if (!$metaBox.length) return;

      this.initFormChangeTracking({
        container: '#yaml-cf-meta-box',
        fieldsSelector: '.yaml-cf-fields',
        message: __('There are changes that are not saved yet.', 'yaml-custom-fields'),
        submitSelector: 'form#post',
        storageKey: 'originalMetaBoxData',
        hasChangesKey: 'hasMetaBoxChanges',
        gutenbergSupport: true,
        captureDelay: 1000,
      });
    },

    /**
     * Show instant feedback for an action on the current page (toast)
     *
     * Page-load feedback (after a save or import) is shown as admin notices by
     * YamlCF\Core\NotificationManager; toasts use the same colours and icons.
     *
     * @param {string} message Text to show
     * @param {string} type success | error | warning | info
     * @param {boolean} [persistent] Keep until dismissed (errors and warnings always are)
     */
    showMessage: function (message, type, persistent) {
      const icons = {
        success: 'dashicons-yes-alt',
        error: 'dashicons-dismiss',
        warning: 'dashicons-warning',
        info: 'dashicons-info',
      };
      type = icons[type] ? type : 'info';
      const sticky = persistent || type === 'error' || type === 'warning';

      let $container = $('#yaml-cf-notifications');
      if (!$container.length) {
        $container = $('<div>', {
          id: 'yaml-cf-notifications',
          role: 'region',
          'aria-label': __('Notifications', 'yaml-custom-fields'),
        });
        $('body').append($container);
      }

      // One toast per message: replace an identical one instead of stacking duplicates
      $container.find('.yaml-cf-toast').filter(function () {
        return $(this).data('message') === message;
      }).remove();

      const $toast = $('<div>', {
        'class': 'yaml-cf-toast is-' + type,
        role: type === 'error' ? 'alert' : 'status',
      }).data('message', message);

      const dismiss = function () {
        $toast.addClass('is-leaving');
        setTimeout(function () {
          $toast.remove();
        }, 200);
      };

      $toast.append(
        $('<span>', { 'class': 'dashicons ' + icons[type], 'aria-hidden': 'true' }),
        $('<span>', { 'class': 'yaml-cf-toast-text', text: message }),
        $('<button>', {
          type: 'button',
          'class': 'yaml-cf-toast-close',
          'aria-label': __('Dismiss notification', 'yaml-custom-fields'),
        }).append($('<span>', { 'class': 'dashicons dashicons-no-alt', 'aria-hidden': 'true' })).on('click', dismiss)
      );

      $container.append($toast);

      if (!sticky) {
        let timer = setTimeout(dismiss, 5000);
        // Pause while the pointer or keyboard focus is on the toast
        $toast.on('mouseenter focusin', function () {
          clearTimeout(timer);
        }).on('mouseleave focusout', function () {
          timer = setTimeout(dismiss, 3000);
        });
      }
    },

    hideMessage: function (type) {
      $('#yaml-cf-notifications .yaml-cf-toast.is-' + type).remove();
    },

    showSnippetPopover: function (e) {
      const $button = $(this);
      const popoverId = $button.data('popover');

      if (!popoverId) {
        return;
      }

      const $popover = $('#' + popoverId);

      if (!$popover.length) {
        return;
      }

      // Clear any hide timeout
      if ($popover.data('hideTimeout')) {
        clearTimeout($popover.data('hideTimeout'));
      }

      // Show the popover
      $popover.addClass('visible');

      // Add hover handlers to the popover itself
      $popover.off('mouseenter mouseleave');

      $popover.on('mouseenter', function () {
        // Clear hide timeout when entering popover
        if ($(this).data('hideTimeout')) {
          clearTimeout($(this).data('hideTimeout'));
        }
        $(this).addClass('visible');
      });

      $popover.on('mouseleave', function () {
        const $self = $(this);
        const hideTimeout = setTimeout(function () {
          $self.removeClass('visible');
        }, 100);
        $self.data('hideTimeout', hideTimeout);
      });
    },

    hideSnippetPopover: function (e) {
      const $button = $(this);
      const popoverId = $button.data('popover');

      if (!popoverId) {
        return;
      }

      const $popover = $('#' + popoverId);

      // Delay hiding to allow moving to popover
      const hideTimeout = setTimeout(function () {
        $popover.removeClass('visible');
      }, 100);

      $popover.data('hideTimeout', hideTimeout);
    },

    copySnippet: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $button = $(this);
      const snippet = $button.data('snippet');
      const popoverId = $button.data('popover');

      if (!snippet) {
        return;
      }

      // Hide the popover immediately
      if (popoverId) {
        const $popover = $('#' + popoverId);
        $popover.removeClass('visible');
        if ($popover.data('hideTimeout')) {
          clearTimeout($popover.data('hideTimeout'));
        }
      }

      // Copy to clipboard
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(snippet).then(
          function () {
            YamlCF.showCopyFeedback($button);
          },
          function () {
            // Fallback for older browsers
            YamlCF.fallbackCopyToClipboard(snippet, $button);
          }
        );
      } else {
        // Fallback for older browsers
        YamlCF.fallbackCopyToClipboard(snippet, $button);
      }
    },

    fallbackCopyToClipboard: function (text, $button) {
      const $temp = $('<textarea>');
      $('body').append($temp);
      $temp.val(text).select();
      try {
        document.execCommand('copy');
        YamlCF.showCopyFeedback($button);
      } catch (err) {
        YamlCF.showMessage(__('Could not copy. Select the code and copy it by hand.', 'yaml-custom-fields'), 'error');
      }
      $temp.remove();
    },

    showCopyFeedback: function ($button) {
      // Remove existing tooltips
      $('.yaml-cf-snippet-tooltip').remove();

      // Add visual feedback to button
      $button.addClass('copied');
      setTimeout(function () {
        $button.removeClass('copied');
      }, 2000);

      // Create success tooltip
      const $tooltip = $('<div>', {
        class: 'yaml-cf-snippet-tooltip',
        text: __('Copied', 'yaml-custom-fields'),
      });

      // Position tooltip
      const buttonOffset = $button.offset();
      const buttonHeight = $button.outerHeight();
      const buttonWidth = $button.outerWidth();

      $('body').append($tooltip);

      const tooltipWidth = $tooltip.outerWidth();
      const tooltipLeft = buttonOffset.left - tooltipWidth / 2 + buttonWidth / 2;
      const tooltipTop = buttonOffset.top + buttonHeight + 8;

      $tooltip.css({
        left: tooltipLeft + 'px',
        top: tooltipTop + 'px',
      });

      // Auto-hide tooltip
      setTimeout(function () {
        $tooltip.fadeOut(300, function () {
          $(this).remove();
        });
      }, 2000);

      // Show success message
      YamlCF.showMessage(__('Code copied. Paste it into the theme file.', 'yaml-custom-fields'), 'success');
    },

    importPostData: function (e) {
      const $input = $(this);
      const file = e.target.files[0];

      if (!file) return;

      if (!confirm('⚠️ ' + __('This replaces all fields on this page with the file\'s content. Continue?', 'yaml-custom-fields'))) {
        $input.val('');
        return;
      }

      const postId = $input.data('post-id');
      const nonce = $input.data('nonce');
      const formData = new FormData();
      formData.append('yaml_cf_import_file', file);
      formData.append('yaml_cf_import_post_nonce', nonce);
      formData.append('post_id', postId);

      // Submit via AJAX to avoid form nesting issues
      $.ajax({
        url: window.location.href,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function () {
          window.location.href = window.location.href.split('?')[0] + '?post=' + postId + '&action=edit&yaml_cf_imported=1';
        },
        error: function () {
          alert(__('The import did not work. Please try again.', 'yaml-custom-fields'));
          $input.val('');
        }
      });
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    YamlCF.init();

    // Fix duplicate nonce IDs created by multiple wp_editor() instances
    YamlCF.removeDuplicateNonces();
  });

  // Remove duplicate nonce fields with the same ID
  YamlCF.removeDuplicateNonces = function() {
    const seenIds = {};
    $('input[type="hidden"]').each(function() {
      const id = $(this).attr('id');
      if (id && id.includes('nonce')) {
        if (seenIds[id]) {
          // Remove duplicate
          $(this).remove();
        } else {
          // Mark as seen
          seenIds[id] = true;
        }
      }
    });
  };

  /**
   * Fold the custom fields box away
   *
   * The box is printed on edit_form_after_title rather than registered with
   * add_meta_box(), so core's postbox script does not manage it. The open or
   * closed state is remembered in this browser only.
   */
  const BOX_STATE_KEY = 'yamlCFMetaBoxClosed';

  function setMetaBoxClosed($box, closed) {
    $box.toggleClass('closed', closed);
    $box.find('.yaml-cf-box-toggle').attr('aria-expanded', closed ? 'false' : 'true');
  }

  $(function() {
    const $box = $('#yaml-cf-meta-box');
    if (!$box.length) {
      return;
    }

    try {
      if (window.localStorage.getItem(BOX_STATE_KEY) === '1') {
        setMetaBoxClosed($box, true);
      }
    } catch (e) {
      // Private windows and blocked storage: the box just starts open
    }

    // One handler on the whole header: a click on the caret bubbles up to it,
    // so the title and the caret behave the same and neither toggles twice.
    $box.on('click', '.yaml-cf-box-header', function() {
      const closed = !$box.hasClass('closed');
      setMetaBoxClosed($box, closed);

      try {
        window.localStorage.setItem(BOX_STATE_KEY, closed ? '1' : '0');
      } catch (e) {
        // Not remembering the state is fine
      }
    });
  });

  // Make YamlCF globally accessible
  window.YamlCF = YamlCF;
})(jQuery);
