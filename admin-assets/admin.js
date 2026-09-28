/**
 * YAML Custom Fields Admin JavaScript
 * File: admin-assets/admin.js
 */

(function ($) {
  'use strict';

  const { __, _n, sprintf } = wp.i18n;

  const YamlCF = {
    hasMetaBoxChanges: false,
    originalMetaBoxData: {},

    init: function () {
      this.initNotices();
      this.initBlockCounters();
      this.bindEvents();
      this.initMediaUploader();
      this.initMetaBoxChangeTracking();
      this.initFieldGlobalLocal();
      this.initGallerySorting();
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

      // Folding a section away, and keeping its header text up to date
      $(document).off('click' + ns, '.yaml-cf-block-toggle').on('click' + ns, '.yaml-cf-block-toggle', this.toggleBlock);
      $(document).off('click' + ns, '.yaml-cf-collapse-all').on('click' + ns, '.yaml-cf-collapse-all', this.toggleAllBlocks);
      $(document).off('input' + ns, '.yaml-cf-block-fields input, .yaml-cf-block-fields textarea').on('input' + ns, '.yaml-cf-block-fields input, .yaml-cf-block-fields textarea', this.updateBlockSummary);

      // Picker (taxonomy or data_object field with too many choices to list)
      $(document).off('input' + ns, '.yaml-cf-picker-search').on('input' + ns, '.yaml-cf-picker-search', this.searchPicker);
      $(document).off('focus' + ns, '.yaml-cf-picker-search').on('focus' + ns, '.yaml-cf-picker-search', this.searchPicker);
      $(document).off('keydown' + ns, '.yaml-cf-picker-search').on('keydown' + ns, '.yaml-cf-picker-search', this.navigatePicker);
      $(document).off('click' + ns, '.yaml-cf-picker-result').on('click' + ns, '.yaml-cf-picker-result', this.choosePickerResult);
      $(document).off('click' + ns, '.yaml-cf-picker-remove').on('click' + ns, '.yaml-cf-picker-remove', this.removePickerChip);

      // Clicking away closes any open result list
      $(document).off('click' + ns + '-picker').on('click' + ns + '-picker', function (e) {
        if (!$(e.target).closest('.yaml-cf-picker').length) {
          $('.yaml-cf-picker-results').attr('hidden', true).empty();
          $('.yaml-cf-picker-search').attr('aria-expanded', 'false');
        }
      });

      // Gallery Controls (image field with list: true)
      $(document).off('click' + ns, '.yaml-cf-remove-image-list-item').on('click' + ns, '.yaml-cf-remove-image-list-item', this.removeImageListItem);
      $(document).off('click' + ns, '.yaml-cf-move-image-earlier').on('click' + ns, '.yaml-cf-move-image-earlier', this.moveImageEarlier);
      $(document).off('click' + ns, '.yaml-cf-move-image-later').on('click' + ns, '.yaml-cf-move-image-later', this.moveImageLater);

      // Reset All Data
      $(document).off('click' + ns, '.yaml-cf-reset-data').on('click' + ns, '.yaml-cf-reset-data', this.resetAllData);

      // Forms that ask before doing something that cannot be undone. The text
      // sits in data-confirm rather than an inline onsubmit, where an apostrophe
      // in a translation or a collection name broke the script and the form
      // then went through without asking at all.
      $(document).off('submit' + ns, 'form[data-confirm]').on('submit' + ns, 'form[data-confirm]', function (e) {
        if (!window.confirm($(this).attr('data-confirm'))) {
          e.preventDefault();
        }
      });

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

      const $status = $checkbox.closest('td').find('.yaml-cf-status[data-slot="toggle"]');

      const revert = function (message) {
        $checkbox.prop('checked', !enabled);
        applyState(!enabled);
        YamlCF.status($status, __('Not saved. Try again.', 'yaml-custom-fields'), 'error', message);
      };

      applyState(enabled);
      $checkbox.prop('disabled', true);

      $.ajax({
        url: yamlCF.ajax_url,
        type: 'POST',
        data: {
          action: 'yaml_cf_save_template_settings',
          nonce: yamlCF.nonce,
          admin_nonce: yamlCF.adminNonce,
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
          YamlCF.status($status, __('Saved', 'yaml-custom-fields'), 'success', message);
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
      const $status = $checkbox.closest('div').find('.yaml-cf-status[data-slot="site-details"]');
      const notSaved = function () {
        $checkbox.prop('checked', !useGlobal);
        YamlCF.status($status, __('Not saved. Try again.', 'yaml-custom-fields'), 'error', __('This setting could not be saved. Please try again.', 'yaml-custom-fields'));
      };

      $checkbox.prop('disabled', true);

      $.ajax({
        url: yamlCF.ajax_url,
        type: 'POST',
        data: {
          action: 'yaml_cf_toggle_use_global',
          nonce: yamlCF.nonce,
          admin_nonce: yamlCF.adminNonce,
          template: template,
          use_global: useGlobal,
        },
        success: function (response) {
          if (!response.success) {
            notSaved();
            return;
          }
          YamlCF.status(
            $status,
            __('Saved', 'yaml-custom-fields'),
            'success',
            useGlobal
              ? __('Saved. These pages now use site details.', 'yaml-custom-fields')
              : __('Saved. These pages no longer use site details.', 'yaml-custom-fields')
          );
        },
        error: notSaved,
        complete: function () {
          $checkbox.prop('disabled', false);
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

    /**
     * Add a section, rendered by the server
     *
     * The markup comes from YamlCF\Rendering\BlockItemRenderer, the same
     * code that renders the sections already on the page. Building it here in
     * JavaScript meant maintaining every field type twice, and the two drifted:
     * taxonomy, post_type and data_object fields used to come out wrong in a
     * section added on the page.
     */
    addBlock: function () {
      const $button = $(this);
      const $container = $button.closest('.yaml-cf-block-container');
      const $select = $container.find('.yaml-cf-block-type-select');
      const blockType = $select.val();
      const $status = $container.find('.yaml-cf-status[data-slot="add-section"]').last();

      if (!blockType) {
        YamlCF.status($status, __('Choose a section type first.', 'yaml-custom-fields'), 'warning');
        $select.trigger('focus');
        return;
      }

      const source = yamlCF.schemaSource;
      if (!source || !source.source) {
        YamlCF.status($status, __('Reload the page and try again.', 'yaml-custom-fields'), 'error', __('The schema for this screen could not be found. Reload the page and try again.', 'yaml-custom-fields'));
        return;
      }

      const $blockList = $container.find('.yaml-cf-block-list');
      const fieldName = $container.data('field-name');
      const index = YamlCF.nextBlockIndex($container);

      $button.prop('disabled', true);

      $.post(yamlCF.ajax_url, {
        action: 'yaml_cf_render_block_item',
        nonce: yamlCF.nonce,
        admin_nonce: yamlCF.adminNonce,
        source: source.source,
        key: source.key || '',
        post_id: source.post_id || 0,
        field_name: fieldName,
        block_type: blockType,
        index: index,
      })
        .done(function (response) {
          if (!response || !response.success) {
            YamlCF.status(
              $status,
              __('Not added. Try again.', 'yaml-custom-fields'),
              'error',
              (response && typeof response.data === 'string' && response.data) ||
                __('The section could not be added.', 'yaml-custom-fields')
            );
            return;
          }

          const $item = $(response.data.html);
          $blockList.append($item);
          YamlCF.clearStatus($status);

          // The new section is where the writer continues: focus its header,
          // and say what was added
          $item.find('.yaml-cf-block-toggle').first().trigger('focus');
          YamlCF.announce(
            /* translators: %s: section type name */
            sprintf(__('%s section added.', 'yaml-custom-fields'), $select.find('option:selected').text()),
            ''
          );

          // The index above only has to be unique on this page; the names are
          // numbered in order again so the saved list has no gaps
          YamlCF.reindexBlocks();

          // Rich text and galleries need wiring up once they are in the page
          YamlCF.initAddedBlock($item);
        })
        .fail(function () {
          YamlCF.status($status, __('Not added. Try again.', 'yaml-custom-fields'), 'error', __('The section could not be added. Try again.', 'yaml-custom-fields'));
        })
        .always(function () {
          $button.prop('disabled', false);
        });
      // Keep the select value for easier adding of multiple blocks of same type
    },

    /**
     * Start up the parts of a freshly added section that need JavaScript
     *
     * @param {jQuery} $item The block item just put into the page
     */
    initAddedBlock: function ($item) {
      // wp_editor() markup arrives inert over AJAX, so TinyMCE is started here
      if (window.tinymce && window.wp && wp.editor && wp.editor.initialize) {
        $item.find('.wp-editor-area').each(function () {
          const id = this.id;
          if (!id || window.tinymce.get(id)) {
            return;
          }
          try {
            wp.editor.initialize(id, { tinymce: true, quicktags: true });
          } catch (e) {
            // A plain textarea still submits its value
          }
        });
      }

      YamlCF.initGallerySorting();
    },


    removeBlock: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $item = $(this).closest('.yaml-cf-block-item');
      const $container = $item.closest('.yaml-cf-block-container');

      // Where focus goes once the section is gone: the next section, the one
      // before, or the section type picker
      const $next = $item.nextAll('.yaml-cf-block-item').first();
      const $previous = $item.prevAll('.yaml-cf-block-item').first();
      const $focusTarget = ($next.length ? $next : $previous).find('.yaml-cf-block-toggle').first();

      $item.fadeOut(300, function () {
        $item.remove();
        YamlCF.reindexBlocks();
        ($focusTarget.length ? $focusTarget : $container.find('.yaml-cf-block-type-select').first()).trigger('focus');
        // Nothing is inserted into the page: the box itself says there are
        // changes to save (see initFormChangeTracking)
        $(document).trigger('yamlcf:changed');
      });

      YamlCF.announce(__('Section removed. Save to keep the change.', 'yaml-custom-fields'), '');
    },

    /**
     * Remember, per sections field, how many sections the page was drawn with
     *
     * @see nextBlockIndex
     */
    initBlockCounters: function () {
      $('.yaml-cf-block-container').each(function () {
        const $container = $(this);
        if ($container.data('yamlCfNextIndex') === undefined) {
          $container.data('yamlCfNextIndex', $container.find('.yaml-cf-block-list').children('.yaml-cf-block-item').length);
        }
      });
    },

    /**
     * An index for a new section that no section on this page has used
     *
     * The index is part of each field's id, a rich-text editor's included.
     * Counting the sections left after one was removed handed out an index a
     * remaining section still had, so two editors shared an id: the new one
     * did not start, and its content could be saved into the other section.
     * Ids are not renumbered when sections move, so the count only goes up.
     *
     * @param {jQuery} $container The sections field
     * @return {number}
     */
    nextBlockIndex: function ($container) {
      let next = $container.data('yamlCfNextIndex');
      if (next === undefined) {
        next = $container.find('.yaml-cf-block-list').children('.yaml-cf-block-item').length;
      }
      $container.data('yamlCfNextIndex', next + 1);
      return next;
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
              })
                .append(YamlCF.icon('remove'))
                .append(document.createTextNode(__('Remove', 'yaml-custom-fields')))
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
              })
                .append(YamlCF.icon('remove'))
                .append(document.createTextNode(__('Remove', 'yaml-custom-fields')))
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
            })
              .append(YamlCF.icon('remove'))
              .append(document.createTextNode(__('Remove', 'yaml-custom-fields')))
              .appendTo($item);
            $list.append($item);
          });
        });

        mediaUploader.open();
      });

      // Gallery (image field with list: true): pick many images in one go
      $(document).on('click', '.yaml-cf-add-image-list-item', function (e) {
        e.preventDefault();

        const $button = $(this);
        const targetName = $button.data('target-name');
        const $list = $button.siblings('.yaml-cf-image-list-items');

        const mediaUploader = wp.media({
          title: __('Choose images', 'yaml-custom-fields'),
          button: {
            text: __('Add images', 'yaml-custom-fields'),
          },
          library: { type: 'image' },
          multiple: 'add',
        });

        mediaUploader.on('select', function () {
          const attachments = mediaUploader.state().get('selection').toJSON();

          attachments.forEach(function (attachment) {
            // The same image twice in one gallery is almost always a misclick
            if ($list.find('.yaml-cf-image-list-item[data-id="' + attachment.id + '"]').length) {
              return;
            }
            $list.append(YamlCF.buildImageListItem(targetName, attachment));
          });

          YamlCF.initGallerySorting();
        });

        mediaUploader.open();
      });
    },

    /**
     * Build one gallery tile, matching the markup of
     * YamlCF\Rendering\MediaListRenderer::renderImageListItem()
     */
    buildImageListItem: function (fieldName, attachment) {
      const $item = $('<li>', {
        class: 'yaml-cf-image-list-item',
        'data-id': attachment.id,
      });

      $('<input>', {
        type: 'hidden',
        name: 'yaml_cf[' + fieldName + '][]',
        value: attachment.id,
      }).appendTo($item);

      // sizes.thumbnail is missing for an image smaller than the thumbnail size
      const thumb =
        (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) ||
        attachment.url;

      $('<img>', {
        src: thumb,
        alt: attachment.alt || '',
        class: 'yaml-cf-image-list-thumb',
      }).appendTo($item);

      const $actions = $('<span>', { class: 'yaml-cf-image-list-actions' });
      const buttons = [
        ['yaml-cf-move-image-earlier', 'back', __('Move earlier', 'yaml-custom-fields')],
        ['yaml-cf-move-image-later', 'next', __('Move later', 'yaml-custom-fields')],
        ['yaml-cf-remove-image-list-item', 'remove', __('Remove this image', 'yaml-custom-fields')],
      ];

      buttons.forEach(function (spec) {
        $('<button>', {
          type: 'button',
          class: 'button button-small ' + spec[0],
          title: spec[2],
        })
          .append(YamlCF.icon(spec[1]))
          .append($('<span>', { class: 'screen-reader-text', text: spec[2] }))
          .appendTo($actions);
      });

      $actions.appendTo($item);
      return $item;
    },

    /**
     * Let galleries be reordered by dragging
     *
     * The order of the hidden inputs is the order that is saved, so moving a
     * tile is all it takes. Safe to call again after adding images.
     */
    initGallerySorting: function () {
      if (!$.fn.sortable) {
        return;
      }

      $('.yaml-cf-image-list-items.is-sortable').each(function () {
        const $list = $(this);
        if ($list.data('ycf-sortable')) {
          return;
        }
        $list.sortable({
          items: '> .yaml-cf-image-list-item',
          placeholder: 'yaml-cf-image-list-placeholder-tile',
          forcePlaceholderSize: true,
          tolerance: 'pointer',
        });
        $list.data('ycf-sortable', true);
      });
    },

    toggleBlock: function (e) {
      e.preventDefault();

      const $toggle = $(this);
      const $item = $toggle.closest('.yaml-cf-block-item');
      const collapsed = !$item.hasClass('is-collapsed');

      $item.toggleClass('is-collapsed', collapsed);
      $toggle.attr('aria-expanded', collapsed ? 'false' : 'true');
    },

    toggleAllBlocks: function (e) {
      e.preventDefault();

      const $button = $(this);

      // Only this field's own sections, not those of a block nested inside one
      const $items = $button
        .closest('.yaml-cf-field')
        .children('.yaml-cf-block-container')
        .first()
        .children('.yaml-cf-block-list')
        .children('.yaml-cf-block-item');

      const collapse = $button.attr('aria-pressed') !== 'true';

      $items.toggleClass('is-collapsed', collapse);
      $items
        .children('.yaml-cf-block-header')
        .children('.yaml-cf-block-toggle')
        .attr('aria-expanded', collapse ? 'false' : 'true');

      // Only the label and the icon change; the button keeps its shape
      $button.attr('aria-pressed', collapse ? 'true' : 'false');
      $button.children('.yaml-cf-icon').replaceWith(YamlCF.icon(collapse ? 'expand' : 'collapse'));
      $button
        .children('.yaml-cf-collapse-all-label')
        .text(collapse ? __('Expand all', 'yaml-custom-fields') : __('Collapse all', 'yaml-custom-fields'));
    },

    /**
     * Keep a section's header text matching the field it stands for
     */
    updateBlockSummary: function () {
      const $input = $(this);
      const $item = $input.closest('.yaml-cf-block-item');
      const summaryField = $item.data('summary-field');

      if (!summaryField) {
        return;
      }

      // The field name ends in [<summaryField>] for both top-level and nested blocks
      const name = $input.attr('name') || '';
      if (!name.endsWith('[' + summaryField + ']')) {
        return;
      }

      const text = String($input.val() || '');
      const words = text.trim().split(/\s+/).slice(0, 12).join(' ');
      $item
        .children('.yaml-cf-block-header')
        .find('.yaml-cf-block-summary')
        .text(words === '' ? '' : words + (text.trim().split(/\s+/).length > 12 ? '…' : ''));
    },

    /**
     * Search a picker's choices
     *
     * The requests are debounced per field and the stale ones are dropped, so
     * typing quickly cannot leave an earlier reply on screen.
     */
    searchPicker: function () {
      const $search = $(this);
      const $picker = $search.closest('.yaml-cf-picker');
      const $results = $picker.find('.yaml-cf-picker-results');
      const term = $search.val();

      clearTimeout($search.data('ycf-timer'));

      const timer = setTimeout(function () {
        const request = ($search.data('ycf-request') || 0) + 1;
        $search.data('ycf-request', request);

        $.post(yamlCF.ajax_url, {
          action:
            $picker.data('source') === 'taxonomy'
              ? 'yaml_cf_search_terms'
              : 'yaml_cf_search_data_objects',
          nonce: yamlCF.nonce,
          admin_nonce: yamlCF.adminNonce,
          taxonomy: $picker.data('key'),
          object_type: $picker.data('key'),
          search: term,
        })
          .done(function (response) {
            // A slower earlier request must not overwrite a later reply
            if ($search.data('ycf-request') !== request) {
              return;
            }
            YamlCF.renderPickerResults($picker, $results, $search, response);
          })
          .fail(function () {
            $results
              .empty()
              .append(
                $('<li>', {
                  class: 'yaml-cf-picker-empty',
                  text: __('The search could not be finished. Try again.', 'yaml-custom-fields'),
                })
              )
              .removeAttr('hidden');
            $search.attr('aria-expanded', 'true');
          });
      }, 200);

      $search.data('ycf-timer', timer);
    },

    renderPickerResults: function ($picker, $results, $search, response) {
      $results.empty();

      if (!response || !response.success || !response.data.results.length) {
        $results.append(
          $('<li>', {
            class: 'yaml-cf-picker-empty',
            text: __('Nothing matched.', 'yaml-custom-fields'),
          })
        );
        $results.removeAttr('hidden');
        $search.attr('aria-expanded', 'true');
        YamlCF.announce(__('Nothing matched.', 'yaml-custom-fields'), '');
        return;
      }

      const chosen = {};
      $picker.find('.yaml-cf-picker-chip').each(function () {
        chosen[String($(this).data('id'))] = true;
      });

      response.data.results.forEach(function (result) {
        const isChosen = chosen[String(result.id)];
        $('<li>', {
          class: 'yaml-cf-picker-result' + (isChosen ? ' is-chosen' : ''),
          role: 'option',
          'aria-selected': isChosen ? 'true' : 'false',
          'data-id': result.id,
          'data-label': result.label,
          text: result.label,
        }).appendTo($results);
      });

      if (response.data.truncated) {
        $results.append(
          $('<li>', {
            class: 'yaml-cf-picker-empty',
            text: __('More matches exist. Keep typing to narrow them down.', 'yaml-custom-fields'),
          })
        );
      }

      $results.removeAttr('hidden');
      $search.attr('aria-expanded', 'true');

      // A list appearing under the box is only seen, so its size is also said
      const count = response.data.results.length;
      YamlCF.announce(
        response.data.truncated
          /* translators: %d: number of results shown */
          ? sprintf(__('%d results shown. Keep typing to narrow them down.', 'yaml-custom-fields'), count)
          /* translators: %d: number of results */
          : sprintf(_n('%d result.', '%d results.', count, 'yaml-custom-fields'), count),
        ''
      );
    },

    /**
     * Move through the result list with the keyboard
     */
    navigatePicker: function (e) {
      const $search = $(this);
      const $picker = $search.closest('.yaml-cf-picker');
      const $results = $picker.find('.yaml-cf-picker-results');
      const $options = $results.find('.yaml-cf-picker-result');

      if (e.key === 'Escape') {
        $results.attr('hidden', true).empty();
        $search.attr('aria-expanded', 'false');
        return;
      }

      if (!$options.length) {
        return;
      }

      const $current = $options.filter('.is-active');
      let index = $options.index($current);

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        index = index + 1 >= $options.length ? 0 : index + 1;
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        index = index <= 0 ? $options.length - 1 : index - 1;
      } else if (e.key === 'Enter') {
        if ($current.length) {
          e.preventDefault();
          $current.trigger('click');
        }
        return;
      } else {
        return;
      }

      $options.removeClass('is-active');
      $options.eq(index).addClass('is-active');
    },

    choosePickerResult: function (e) {
      e.preventDefault();

      const $result = $(this);
      const $picker = $result.closest('.yaml-cf-picker');
      const $selected = $picker.find('.yaml-cf-picker-selected');
      const $search = $picker.find('.yaml-cf-picker-search');
      const multiple = String($picker.data('multiple')) === '1';
      const fieldName = $picker.data('field-name');
      const id = String($result.data('id'));
      const label = String($result.data('label'));

      if ($selected.find('.yaml-cf-picker-chip[data-id="' + id + '"]').length) {
        return;
      }

      // One value only: the new choice replaces the old one
      if (!multiple) {
        $selected.empty();
        $picker.children('.yaml-cf-picker-value').remove();
      }

      const name = multiple
        ? 'yaml_cf[' + fieldName + '][]'
        : 'yaml_cf[' + fieldName + ']';

      const $chip = $('<li>', {
        class: 'yaml-cf-picker-chip',
        'data-id': id,
      });
      $('<input>', {
        type: 'hidden',
        class: 'yaml-cf-picker-value',
        name: name,
        value: id,
      }).appendTo($chip);
      $('<span>', { class: 'yaml-cf-picker-chip-label', text: label }).appendTo($chip);
      // The label is text from a term or a collection item, so it goes in as
      // text: built as markup, "R&D <team>" came out mangled or as tags.
      $('<button>', { type: 'button', class: 'yaml-cf-picker-remove' })
        .append(YamlCF.icon('remove'))
        .append($('<span>', { class: 'screen-reader-text', text: __('Remove', 'yaml-custom-fields') + ' ' + label }))
        .appendTo($chip);

      $selected.append($chip);

      $search.val('').attr('aria-expanded', 'false');
      $picker.find('.yaml-cf-picker-results').attr('hidden', true).empty();
    },

    removePickerChip: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $chip = $(this).closest('.yaml-cf-picker-chip');
      const $picker = $chip.closest('.yaml-cf-picker');
      const multiple = String($picker.data('multiple')) === '1';
      const fieldName = $picker.data('field-name');

      $chip.remove();

      // A single-value picker still has to post something, or the old value
      // would survive the save.
      if (!multiple && !$picker.find('.yaml-cf-picker-chip').length) {
        $('<input>', {
          type: 'hidden',
          class: 'yaml-cf-picker-value',
          name: 'yaml_cf[' + fieldName + ']',
          value: '',
        }).insertBefore($picker.find('.yaml-cf-picker-search-row'));
      }
    },

    removeFileListItem: function (e) {
      e.preventDefault();
      e.stopPropagation();

      $(this).closest('.yaml-cf-file-list-item').remove();
    },

    removeImageListItem: function (e) {
      e.preventDefault();
      e.stopPropagation();

      $(this).closest('.yaml-cf-image-list-item').remove();
    },

    moveImageEarlier: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $item = $(this).closest('.yaml-cf-image-list-item');
      const $previous = $item.prev('.yaml-cf-image-list-item');
      if ($previous.length) {
        $item.insertBefore($previous);
        $item.find('.yaml-cf-move-image-earlier').trigger('focus');
      }
    },

    moveImageLater: function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $item = $(this).closest('.yaml-cf-image-list-item');
      const $next = $item.next('.yaml-cf-image-list-item');
      if ($next.length) {
        $item.insertAfter($next);
        $item.find('.yaml-cf-move-image-later').trigger('focus');
      }
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

      // The button goes with the value it cleared, so focus moves to the
      // field's choose button
      const $wrapper = $button.closest('.yaml-cf-field');
      $button.remove();
      $wrapper.find('.yaml-cf-upload-image, .yaml-cf-upload-file').first().trigger('focus');

      YamlCF.announce(__('Removed. Save to keep the change.', 'yaml-custom-fields'), '');
      $(document).trigger('yamlcf:changed');
    },

    resetAllData: function (e) {
      e.preventDefault();

      const $button = $(this);

      if (
        !confirm(
          '⚠️ ' + __('This empties every field on this page. Shared fields and site details stay as they are.\n\nSave afterwards to keep the change.\n\nContinue?', 'yaml-custom-fields')
        )
      ) {
        return;
      }

      // Reset only local fields (not global or template global)
      // First, get all inputs that are NOT in template global parts
      const $localInputs = $('#yaml-cf-meta-box > .inside > .yaml-cf-fields')
        .find('input, textarea, select')
        .filter(function() {
          // Exclude inputs that are inside .yaml-cf-template-global-part, and the
          // "Use the shared value on this page" ticks: emptying the page's own
          // values must not switch a field from the shared value to an empty one
          return $(this).closest('.yaml-cf-template-global-part').length === 0 &&
            !$(this).hasClass('yaml-cf-use-global-checkbox');
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

      // Galleries, file lists and search pickers keep their values in hidden
      // inputs inside each item or chip, which the loop above leaves alone. They
      // stayed filled after "Empty all fields" and were saved again on Update.
      // Each list keeps its empty placeholder, so the field saves as empty.
      const $ownFields = $('#yaml-cf-meta-box > .inside > .yaml-cf-fields');
      $ownFields
        .find('.yaml-cf-image-list-item, .yaml-cf-file-list-item')
        .filter(function () {
          return $(this).closest('.yaml-cf-template-global-part').length === 0;
        })
        .remove();
      $ownFields
        .find('.yaml-cf-picker')
        .filter(function () {
          return $(this).closest('.yaml-cf-template-global-part').length === 0;
        })
        .each(function () {
          const $picker = $(this);
          const hadChips = $picker.find('.yaml-cf-picker-chip').length > 0;
          $picker.find('.yaml-cf-picker-chip').remove();

          // A single-value picker posts its value from the chip; with the chip
          // gone it needs an empty input, as when its remove button is used
          if (hadChips && String($picker.data('multiple')) !== '1' && !$picker.children('.yaml-cf-picker-value').length) {
            $('<input>', {
              type: 'hidden',
              class: 'yaml-cf-picker-value',
              name: 'yaml_cf[' + $picker.data('field-name') + ']',
              value: '',
            }).insertBefore($picker.find('.yaml-cf-picker-search-row'));
          }
        });

      YamlCF.announce(__('All fields on this page are now empty. Save to keep this change.', 'yaml-custom-fields'), '');
      $(document).trigger('yamlcf:changed');
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

      // Unsaved changes are said in the form's own status line (next to its
      // Save button, or in the Custom fields box's header), so the page does
      // not move. Only called when the state flips, so it is said once. In the
      // block editor too: its Save button lights up for any change to the
      // post, and this line says the change was in the custom fields.
      const $unsaved = $container.find('.yaml-cf-status[data-slot="unsaved"]').first();

      function toggleIndicator(show) {
        if (show) {
          YamlCF.status($unsaved, config.message, 'warning');
        } else {
          YamlCF.clearStatus($unsaved);
        }
      }

      // Capture initial state after page loads
      setTimeout(function () {
        self[storageKey] = captureFormState();
      }, config.captureDelay || 1000);

      // Watch for changes: typing, and things the script itself changes
      // (a section removed, an image cleared, every field emptied)
      $container.on('input change', 'input, textarea, select', function () {
        checkFormChanges();
      });
      $(document).off('yamlcf:changed.tracking').on('yamlcf:changed.tracking', checkFormChanges);

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
            // wp.data can be on a screen with no block editor (the classic
            // editor, when another plugin loads it); there is no save to follow
            const editorStore = wp.data.select('core/editor');
            if (!editorStore || typeof editorStore.isSavingPost !== 'function') {
              return;
            }
            const isSaving = editorStore.isSavingPost();
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
        message: __('Changes not saved yet. Save to keep them.', 'yaml-custom-fields'),
        submitSelector: 'form#post',
        storageKey: 'originalMetaBoxData',
        hasChangesKey: 'hasMetaBoxChanges',
        gutenbergSupport: true,
        captureDelay: 1000,
      });
    },

    /**
     * Say how an action on the page went, right next to the control used
     *
     * For anything done without leaving the page: a switch saved, a section
     * that could not be added, changes waiting to be saved. The text appears in
     * a slot the page already keeps room for (.yaml-cf-status), so nothing
     * moves, and it is announced to screen readers. A notice at the top of the
     * page would push the whole screen down under the pointer.
     *
     * A slot that holds text of its own (a hint) gets that text back when the
     * status is cleared.
     *
     * @param {jQuery} $slot The .yaml-cf-status element
     * @param {string} text Short text to show, e.g. "Saved"
     * @param {string} type success | error | warning | info
     * @param {string} [spoken] Fuller text for screen readers (default: text)
     */
    status: function ($slot, text, type, spoken) {
      type = YamlCF.noticeTypes.indexOf(type) !== -1 ? type : 'info';

      if ($slot && $slot.length) {
        if ($slot.data('default') === undefined) {
          $slot.data('default', $slot.text());
        }

        // One "Saved" at a time: an older one elsewhere has done its job
        if (type === 'success') {
          $('.yaml-cf-status.is-success').not($slot).each(function () {
            YamlCF.clearStatus($(this));
          });
        }

        $slot
          .removeClass('is-success is-error is-warning is-info')
          .addClass('is-' + type)
          .empty()
          .append($('<span>', { 'class': 'screen-reader-text', text: YamlCF.noticeLabel(type) + ' ' }))
          .append(document.createTextNode(text));
      }

      YamlCF.announce(spoken || text, type);
    },

    /**
     * Empty a status slot again, or give it back its own text
     *
     * @param {jQuery} $slot The .yaml-cf-status element
     */
    clearStatus: function ($slot) {
      if (!$slot || !$slot.length) {
        return;
      }
      $slot.removeClass('is-success is-error is-warning is-info').text($slot.data('default') || '');
    },

    /**
     * Tell the user something. The one way the plugin does it.
     *
     * Every message is the same notice WordPress uses, built the same way as
     * the ones YamlCF\Core\NotificationManager prints after a save:
     *
     * - It sits in the page, under the heading (or inline, where asked), not
     *   floating in a corner away from what it is about.
     * - It stays until it is dismissed. Nothing disappears while it is read.
     * - wp.a11y.speak() announces it, errors assertively and the rest politely,
     *   with its kind ("Error:", "Warning:") spelled out, so colour and the
     *   icon are never the only signal. The notice itself has no live-region
     *   role, so nothing is read twice.
     * - In the block editor it goes to the editor's own notice area.
     *
     * @param {string} message Text to show
     * @param {string} type success | error | warning | info
     * @param {Object} [options]
     * @param {string} [options.key] Replaces an earlier notice with this key (default: the message)
     * @param {boolean} [options.once] Leave a notice with this key and text alone rather than repeat it
     * @param {jQuery} [options.target] Show the notice here, inline, instead of under the page heading
     * @return {jQuery|null} The notice, or null in the block editor
     */
    notify: function (message, type, options) {
      type = YamlCF.noticeTypes.indexOf(type) !== -1 ? type : 'info';
      options = options || {};
      const key = options.key || message;

      if (YamlCF.inBlockEditor()) {
        const id = 'yaml-cf-' + key;
        const shown = wp.data.select('core/notices').getNotices().some(function (notice) {
          return notice.id === id && notice.content === message;
        });
        if (!(options.once && shown)) {
          wp.data.dispatch('core/notices').createNotice(type, message, { id: id, isDismissible: true });
        }
        return null;
      }

      const inline = !!(options.target && options.target.length);
      const $region = inline ? options.target : YamlCF.noticeRegion();
      const $existing = $region.children('.yaml-cf-notice').filter(function () {
        return $(this).attr('data-key') === key;
      });

      if (options.once && $existing.length && $existing.find('.yaml-cf-notice-text').text() === message) {
        return $existing.first();
      }
      $existing.remove();

      const $notice = YamlCF.buildNotice(message, type, !inline).attr('data-key', key);
      $region.append($notice);
      YamlCF.announce(message, type);

      return $notice;
    },

    /**
     * Take a notice away again, by the key it was shown with
     *
     * @param {string} key The notice's key
     */
    dismissNotice: function (key) {
      if (YamlCF.inBlockEditor()) {
        wp.data.dispatch('core/notices').removeNotice('yaml-cf-' + key);
        return;
      }

      $('.yaml-cf-notice').filter(function () {
        return $(this).attr('data-key') === key;
      }).remove();
    },

    noticeTypes: ['success', 'error', 'warning', 'info'],

    /**
     * An icon, the way YamlCF\Helpers\Icons prints it on the server
     *
     * The names come from the same list (localised as yamlCF.icons), so a
     * button drawn by the script has the icon the server gives the same button.
     *
     * @param {string} key What the icon stands for, e.g. 'remove'
     * @return {jQuery}
     */
    icon: function (key) {
      const icons = (window.yamlCF && yamlCF.icons) || {};
      return $('<span>', {
        'class': 'dashicons dashicons-' + (icons[key] || 'marker') + ' yaml-cf-icon',
        'aria-hidden': 'true',
      });
    },

    /**
     * The words a screen reader says for a kind of notice (as NotificationManager::typeLabel())
     *
     * @param {string} type Notice type
     * @return {string}
     */
    noticeLabel: function (type) {
      const labels = {
        success: __('Done:', 'yaml-custom-fields'),
        error: __('Error:', 'yaml-custom-fields'),
        warning: __('Warning:', 'yaml-custom-fields'),
        info: __('Note:', 'yaml-custom-fields'),
      };
      return labels[type] || labels.info;
    },

    /**
     * The same markup NotificationManager::renderNotice() prints
     *
     * The icon is drawn by the stylesheet from the notice type, for every notice
     * alike, and hidden from screen readers; the label says the type instead.
     *
     * @param {string} message Text
     * @param {string} type Notice type
     * @param {boolean} dismissible Whether it gets a dismiss button
     * @return {jQuery}
     */
    buildNotice: function (message, type, dismissible) {
      const $notice = $('<div>', {
        'class': 'notice notice-' + type + ' yaml-cf-notice' + (dismissible ? ' is-dismissible' : ' inline'),
        'data-notice-type': type,
        tabindex: '-1',
      });

      $('<p>')
        .append($('<span>', { 'class': 'screen-reader-text', text: YamlCF.noticeLabel(type) + ' ' }))
        .append($('<span>', { 'class': 'yaml-cf-notice-text', text: message }))
        .appendTo($notice);

      if (dismissible) {
        $('<button>', { type: 'button', 'class': 'notice-dismiss' })
          .append($('<span>', { 'class': 'screen-reader-text', text: __('Dismiss this notice.', 'yaml-custom-fields') }))
          .appendTo($notice);
      }

      return $notice;
    },

    /**
     * Where notices go: under the page heading, where WordPress puts its own
     *
     * @return {jQuery}
     */
    noticeRegion: function () {
      let $region = $('#yaml-cf-notices');
      if ($region.length) {
        return $region;
      }

      $region = $('<div>', { id: 'yaml-cf-notices', 'class': 'yaml-cf-notices' });
      const $headerEnd = $('#wpbody-content .wp-header-end').first();
      if ($headerEnd.length) {
        $region.insertAfter($headerEnd);
      } else {
        $region.prependTo($('#wpbody-content .wrap').first().length ? $('#wpbody-content .wrap').first() : $('#wpbody-content'));
      }

      return $region;
    },

    /**
     * Say a message to screen reader users, as the notice shows it to everyone else
     *
     * @param {string} message Text
     * @param {string} type Notice type, or '' to say the text alone
     */
    announce: function (message, type) {
      if (!window.wp || !wp.a11y || !wp.a11y.speak) {
        return;
      }
      const text = type ? YamlCF.noticeLabel(type) + ' ' + message : message;
      wp.a11y.speak(text, type === 'error' ? 'assertive' : 'polite');
    },

    /**
     * Whether this is the block editor, whose notices live in core/notices
     *
     * @return {boolean}
     */
    inBlockEditor: function () {
      return document.body.classList.contains('block-editor-page') &&
        !!(window.wp && wp.data && wp.data.select && wp.data.select('core/notices'));
    },

    /**
     * Put keyboard focus somewhere sensible when a notice goes away
     *
     * Dismissing removes the button that had focus, which would drop the
     * keyboard back at the top of the document.
     *
     * @param {jQuery} $notice The notice being dismissed
     */
    focusAfterNotice: function ($notice) {
      if (!$notice.has(document.activeElement).length && !$notice.is(document.activeElement)) {
        return;
      }

      const $next = $notice.nextAll('.yaml-cf-notice').first();
      const $previous = $notice.prevAll('.yaml-cf-notice').first();
      if ($next.length || $previous.length) {
        ($next.length ? $next : $previous).find('.notice-dismiss').first().trigger('focus');
        return;
      }

      const $heading = $('#wpbody-content .wrap h1').first();
      if ($heading.length) {
        $heading.attr('tabindex', '-1').trigger('focus');
      }
    },

    /**
     * Dismissing, and the notices a page arrives with
     *
     * An error the server reports after a save is focused, so it is the first
     * thing read; the others sit right under the heading, where reading starts.
     */
    initNotices: function () {
      $(document).off('click.yamlcf-notice').on('click.yamlcf-notice', '.yaml-cf-notice .notice-dismiss', function () {
        const $notice = $(this).closest('.yaml-cf-notice');
        YamlCF.focusAfterNotice($notice);
        // WordPress removes the notices it printed itself; these are ours
        if ($notice.is('[data-key]')) {
          $notice.remove();
        }
      });

      // After WordPress has moved its notices under the heading
      setTimeout(function () {
        if (document.activeElement && document.activeElement !== document.body) {
          return;
        }
        $('.yaml-cf-notice.notice-error.is-dismissible').filter(':visible').first().trigger('focus');
      }, 0);
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
        // Said on the button, like "Copied"; the code is in the popover to copy by hand
        $button.find('.snippet-text').text(__('Not copied', 'yaml-custom-fields'));
        YamlCF.announce(__('Could not copy. Select the code and copy it by hand.', 'yaml-custom-fields'), 'error');
      }
      $temp.remove();
    },

    showCopyFeedback: function ($button) {
      // Feedback where the click was: the button says so for a moment, and a
      // screen reader hears it. No notice for something this small.
      const $label = $button.find('.snippet-text');
      if (!$button.data('label')) {
        $button.data('label', $label.text());
      }

      $button.addClass('copied');
      $label.text(__('Copied', 'yaml-custom-fields'));
      YamlCF.announce(__('Code copied. Paste it into the theme file.', 'yaml-custom-fields'), '');

      clearTimeout($button.data('copyTimer'));
      $button.data('copyTimer', setTimeout(function () {
        $button.removeClass('copied');
        $label.text($button.data('label'));
      }, 2000));
    },

    importPostData: function (e) {
      const $input = $(this);
      const file = e.target.files[0];

      if (!file) return;

      if (!confirm('⚠️ ' + __('This fills in this page\'s fields from the file. Fields the file does not mention keep their content. The page then reloads, so changes you have not saved yet are lost. Continue?', 'yaml-custom-fields'))) {
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
          YamlCF.status(
            $('#yaml-cf-meta-box .yaml-cf-status[data-slot="unsaved"]').first(),
            __('The import did not work. Please try again.', 'yaml-custom-fields'),
            'error'
          );
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
  //
  // Only a repeat inside the same form and under the same name is dropped: that
  // one is redundant. A list screen prints one small form per row, each with
  // its own nonce under the same ID, and removing those left every row after
  // the first unable to submit.
  YamlCF.removeDuplicateNonces = function() {
    const seen = [];
    $('input[type="hidden"]').each(function() {
      const id = $(this).attr('id');
      if (!id || !id.includes('nonce')) {
        return;
      }

      const form = this.form;
      const name = $(this).attr('name');
      const isRepeat = seen.some(function(entry) {
        return entry.id === id && entry.form === form && entry.name === name;
      });

      if (isRepeat) {
        $(this).remove();
      } else {
        seen.push({ id: id, form: form, name: name });
      }
    });
  };

  // Folding the custom fields box away is core's job: it is a real meta box
  // (add_meta_box), so postboxes.js handles the toggle and remembers the open or
  // closed state per user instead of per browser.

  // Make YamlCF globally accessible
  window.YamlCF = YamlCF;
})(jQuery);
