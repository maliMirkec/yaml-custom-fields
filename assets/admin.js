/**
 * YAML Custom Fields Admin JavaScript
 * File: assets/admin.js
 */

(function ($) {
  'use strict';

  const YamlCF = {
    hasMetaBoxChanges: false,
    originalMetaBoxData: {},

    init: function () {
      this.bindEvents();
      this.initMediaUploader();
      this.initMetaBoxChangeTracking();
      this.initFieldGlobalLocal();
    },

    bindEvents: function () {
      // Enable/Disable YAML for templates
      $(document).on('change', '.yaml-cf-enable-yaml', this.toggleYAML);

      // Toggle Use Global for templates
      $(document).on('change', '.yaml-cf-use-global', this.toggleUseGlobal);

      // Toggle per-field global/local
      $(document).on('change', '.yaml-cf-use-global-checkbox', this.toggleFieldGlobalLocal);

      // Block Controls
      $(document).on('click', '.yaml-cf-add-block', this.addBlock);
      $(document).on('click', '.yaml-cf-remove-block', this.removeBlock);

      // Clear Media
      $(document).on('click', '.yaml-cf-clear-media', this.clearMedia);

      // Reset All Data
      $(document).on('click', '.yaml-cf-reset-data', this.resetAllData);

      // Import Settings
      $(document).on(
        'click',
        '.yaml-cf-import-settings-trigger',
        this.triggerImport
      );
      $(document).on('change', '#yaml-cf-import-file', this.importSettings);

      // Import Post Data
      $(document).on('change', 'input[name="yaml_cf_import_file_hidden"]', this.importPostData);

      // Code Snippet Copy
      $(document).on('click', '.yaml-cf-copy-snippet', this.copySnippet);
      $(document).on('mouseenter', '.yaml-cf-copy-snippet', this.showSnippetPopover);
      $(document).on('mouseleave', '.yaml-cf-copy-snippet', this.hideSnippetPopover);

      // Template Global Controls
      $(document).on('change', '.yaml-cf-use-template-global-checkbox', this.toggleTemplateGlobal);
      $(document).on('click', '.yaml-cf-enable-override', this.enableOverride);
      $(document).on('click', '.yaml-cf-reset-override', this.resetOverride);
    },

    toggleYAML: function () {
      const $checkbox = $(this);
      const template = $checkbox.data('template');
      const enabled = $checkbox.is(':checked');

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
          if (response.success) {
            // Update the schema button visibility
            const $row = $checkbox.closest('tr');
            const $cells = $row.find('td');

            // Schema column is always the 4th column (index 3)
            const $schemaCell = $cells.eq(3);

            // Data column is the 5th column (index 4) - only exists in partials table
            const $dataCell = $cells.eq(4);

            if (enabled) {
              const editSchemaUrl =
                yamlCF.admin_url +
                'admin.php?page=yaml-cf-edit-schema&template=' +
                encodeURIComponent(template);
              const hasSchema = response.data && response.data.has_schema;
              const buttonText = hasSchema ? 'Edit Schema' : 'Add Schema';
              const checkmark = hasSchema
                ? ' <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>'
                : '';

              $schemaCell.html(
                '<a href="' +
                  editSchemaUrl +
                  '" class="button">' +
                  buttonText +
                  '</a>' +
                  checkmark
              );

              // If this is a partial (has data column), update it too
              if ($dataCell.length) {
                if (hasSchema) {
                  const manageDataUrl =
                    yamlCF.admin_url +
                    'admin.php?page=yaml-cf-edit-partial&template=' +
                    encodeURIComponent(template);
                  $dataCell.html(
                    '<a href="' +
                      manageDataUrl +
                      '" class="button">Manage Data</a>'
                  );
                } else {
                  $dataCell.html(
                    '<span class="description">Add schema first</span>'
                  );
                }
              }
            } else {
              $schemaCell.html(
                '<span class="description">Enable YAML first</span>'
              );

              // If this is a partial (has data column), update it too
              if ($dataCell.length) {
                $dataCell.html(
                  '<span class="description">Add schema first</span>'
                );
              }
            }

            YamlCF.showMessage('Settings saved successfully', 'success');
          } else {
            $checkbox.prop('checked', !enabled);
            YamlCF.showMessage('Error saving settings', 'error');
          }
        },
        error: function () {
          $checkbox.prop('checked', !enabled);
          YamlCF.showMessage('Error saving settings', 'error');
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
            YamlCF.showMessage('Global schema setting saved', 'success');
          } else {
            $checkbox.prop('checked', !useGlobal);
            YamlCF.showMessage('Error saving setting', 'error');
          }
        },
        error: function () {
          $checkbox.prop('checked', !useGlobal);
          YamlCF.showMessage('Error saving setting', 'error');
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

    enableOverride: function () {
      const $button = $(this);
      const fieldName = $button.data('field');
      const $fieldContainer = $button.closest('.yaml-cf-template-global-field');
      const $fieldHeader = $fieldContainer.find('.yaml-cf-field-header');
      const $fieldDisplay = $fieldContainer.find('.yaml-cf-field-display');

      // Change indicator to override
      $fieldHeader.find('.yaml-cf-global-indicator').replaceWith(
        '<span class="yaml-cf-override-indicator" style="margin-left: 10px; padding: 2px 8px; background: #f0ad4e; color: #fff; border-radius: 3px; font-size: 11px; font-weight: bold;">⚠️ OVERRIDDEN</span>'
      );

      // Change button to reset
      $button.replaceWith(
        '<button type="button" class="button button-small yaml-cf-reset-override" data-field="' + fieldName + '">Reset to Global</button>'
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
      if ($fieldDisplay.find('input[name="yaml_cf_template_global_override[' + fieldName + ']"]').length === 0) {
        $fieldDisplay.prepend('<input type="hidden" name="yaml_cf_template_global_override[' + fieldName + ']" value="1" />');
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

      if (!confirm('Are you sure you want to reset this field to the global value? Any custom changes will be lost.')) {
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
        alert('Please select a block type');
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
        alert('Block definition not found');
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
      $header.append(
        $('<button>', {
          type: 'button',
          class: 'button yaml-cf-remove-block',
          text: 'Remove',
        })
      );

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
          const $field = $('<div>', { class: 'yaml-cf-field' });
          const blockFieldId = 'ycf_' + uniqueId + '_' + blockField.name;

          $field.append(
            $('<label>', {
              for: blockFieldId,
              text: blockField.label || blockField.name,
            })
          );

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
              $('<div>', {
                style:
                  'padding: 10px; background: #f0f0f0; border: 1px dashed #ccc;',
                text: 'Rich text editor will appear after saving the page.',
              })
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
            const values = blockField.values || [];

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

            $select.append($('<option>', { value: '', text: '-- Select --' }));

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
                text: 'Upload Image',
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
                text: 'Upload File',
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
      $select.val('');
    },

    removeBlock: function () {
      if (
        confirm(
          'Are you sure you want to remove this block? Remember to update the page to save changes.'
        )
      ) {
        $(this)
          .closest('.yaml-cf-block-item')
          .fadeOut(300, function () {
            $(this).remove();
            // Re-index remaining blocks
            YamlCF.reindexBlocks();
          });
      }
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

    initMediaUploader: function () {
      // Image Upload
      $(document).on('click', '.yaml-cf-upload-image', function (e) {
        e.preventDefault();

        const $button = $(this);
        const targetId = $button.data('target');
        const currentValue = $('#' + targetId).val();

        // Create a new frame instance to avoid conflicts
        const mediaUploader = wp.media({
          title: 'Select Image',
          button: {
            text: 'Use This Image',
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
              '<div class="yaml-cf-image-preview">' +
                '<img src="' +
                attachment.url +
                '" style="max-width: 200px; display: block; margin-top: 10px;" />' +
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
                text: 'Clear',
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
          title: 'Select File',
          button: {
            text: 'Use This File',
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
                text: 'Clear',
              })
            );
          }
        });

        mediaUploader.open();
      });
    },

    clearMedia: function (e) {
      e.preventDefault();

      const $button = $(this);
      const targetId = $button.data('target');
      const $field = $('#' + targetId);

      if (
        !confirm(
          'Are you sure you want to clear this file? Remember to update the page to save changes.'
        )
      ) {
        return;
      }

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
    },

    resetAllData: function (e) {
      e.preventDefault();

      const $button = $(this);

      if (
        !confirm(
          '⚠️ WARNING: This will clear all LOCAL custom field data for this page (not global or template global fields).\n\nThis action cannot be undone. You will need to save the page to make this permanent.\n\nAre you sure you want to continue?'
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
        'All custom field data has been cleared. Remember to save the page to make this change permanent.'
      );
    },

    triggerImport: function (e) {
      e.preventDefault();
      $('#yaml-cf-import-file').click();
    },

    importSettings: function (e) {
      const file = e.target.files[0];
      if (!file) return;

      // Validate file type
      if (!file.name.endsWith('.json')) {
        YamlCF.showMessage('Please select a valid JSON file', 'error');
        return;
      }

      // Confirm import
      const confirmMsg =
        'This will import settings and may overwrite existing schemas.\n\n' +
        'Choose:\n' +
        'OK = Replace all settings\n' +
        'Cancel = Merge with existing settings\n\n' +
        'Continue?';

      if (!confirm(confirmMsg)) {
        // User wants to merge
        const mergeConfirm = confirm(
          'Merge imported settings with existing settings?'
        );
        if (!mergeConfirm) {
          e.target.value = ''; // Reset file input
          return;
        }
      }

      const merge = !confirm(
        'Replace all existing settings? (Cancel to merge instead)'
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
                let message = 'Settings imported successfully!';
                if (info.imported_from && info.imported_from !== 'unknown') {
                  message += '\n\nImported from: ' + info.imported_from;
                }
                if (info.exported_at && info.exported_at !== 'unknown') {
                  message += '\nExported at: ' + info.exported_at;
                }
                alert(message);
                YamlCF.showMessage(
                  'Settings imported successfully',
                  'success'
                );

                // Reload page to show updated settings
                setTimeout(function () {
                  window.location.reload();
                }, 1500);
              } else {
                YamlCF.showMessage(
                  'Error importing settings: ' +
                    (response.data || 'Unknown error'),
                  'error'
                );
              }
            },
            error: function () {
              YamlCF.showMessage(
                'AJAX error occurred during import',
                'error'
              );
            },
            complete: function () {
              // Reset file input
              $('#yaml-cf-import-file').val('');
            },
          });
        } catch (err) {
          YamlCF.showMessage('Invalid JSON file: ' + err.message, 'error');
          $('#yaml-cf-import-file').val('');
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
              // Gutenberg
              wp.data
                .dispatch('core/editor')
                .editPost({ meta: { _ycf_changed: Date.now() } });
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

      // Clear changes flag on form submit
      $(config.submitSelector).on('submit', function () {
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
        message: 'You have unsaved changes in YAML Custom Fields fields',
        submitSelector: 'form#post',
        storageKey: 'originalMetaBoxData',
        hasChangesKey: 'hasMetaBoxChanges',
        gutenbergSupport: true,
        captureDelay: 1000,
      });
    },

    showMessage: function (message, type, persistent) {
      // Create notification container if it doesn't exist
      let $container = $('#yaml-cf-notifications');
      if (!$container.length) {
        $container = $('<div>', {
          id: 'yaml-cf-notifications',
        });
        $('body').append($container);
      }

      // Remove existing message of the same type if persistent
      if (persistent) {
        $container.find('.yaml-cf-message.' + type).remove();
      }

      const $message = $('<div>', {
        class: 'yaml-cf-message ' + type,
        text: message,
        'data-type': type,
      });

      $container.append($message);

      // Auto-hide after 3 seconds unless persistent
      if (!persistent) {
        setTimeout(function () {
          $message.fadeOut(300, function () {
            $(this).remove();
          });
        }, 3000);
      }
    },

    hideMessage: function (type) {
      $('#yaml-cf-notifications .yaml-cf-message.' + type).fadeOut(
        300,
        function () {
          $(this).remove();
        }
      );
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
        YamlCF.showMessage('Failed to copy snippet', 'error');
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
        text: 'Copied!',
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
      YamlCF.showMessage('Code snippet copied to clipboard!', 'success');
    },

    importPostData: function (e) {
      const $input = $(this);
      const file = e.target.files[0];

      if (!file) return;

      if (!confirm('⚠️ WARNING: This will replace ALL custom field data for this page. Continue?')) {
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
          alert('Import failed. Please try again.');
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

  // Make YamlCF globally accessible
  window.YamlCF = YamlCF;
})(jQuery);
