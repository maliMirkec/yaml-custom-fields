<?php

namespace YamlCF\Admin\Controllers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The Settings screen: choices about how the plugin behaves site-wide
 *
 * Page caches, reading over the REST API, and what happens to the content if
 * the plugin is deleted. They used to sit at the bottom of the main screen,
 * under the template tables, where they were easy to miss and made the screen
 * people work on every day longer than it needed to be.
 */
class SettingsController extends AdminController {
  /**
   * Whether deleting the plugin should leave the content in place
   */
  const KEEP_DATA_OPTION = 'yaml_cf_keep_data_on_uninstall';

  /** The screen's ?page= slug */
  const PAGE = 'yaml-cf-settings';

  private $notificationManager;

  /**
   * @param \YamlCF\Core\NotificationManager $notificationManager Queues the "saved" notices
   */
  public function __construct($notificationManager) {
    $this->notificationManager = $notificationManager;
  }

  /**
   * Render the Settings screen
   */
  public function render() {
    $this->checkPermission();

    $keep_data_on_uninstall = (bool) get_option(self::KEEP_DATA_OPTION, false);
    $clear_post_caches = (string) get_option(\YamlCF\Cache\CacheManager::CLEAR_ALL_POSTS_OPTION, '1') !== '0';
    $rest_public_read = \YamlCF\Rest\RestApi::publicReadEnabled();

    $this->loadTemplate('settings-page.php', compact(
      'keep_data_on_uninstall',
      'clear_post_caches',
      'rest_public_read'
    ));
  }

  /**
   * Save the choice that was submitted, then come back to the screen
   *
   * Hooked to admin_init, so the "saved" notice is queued before WordPress
   * prints notices, and reloading the page does not send the form again.
   *
   * @return void
   */
  public function handleFormSubmissions() {
    if (\YamlCF\Helpers\RequestHelper::getParam('page') !== self::PAGE) {
      return;
    }

    if ($this->saveSubmittedChoices()) {
      wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE));
      exit;
    }
  }

  /**
   * Save whichever of the choices was submitted
   *
   * @return bool Whether a form was submitted
   */
  public function saveSubmittedChoices() {
    return $this->handleKeepDataSubmission() || $this->handleClearCachesSubmission() || $this->handleRestAccessSubmission();
  }

  /**
   * Save the choice about reading site details and collections over the REST API
   *
   * @return bool Whether this form was submitted
   */
  private function handleRestAccessSubmission() {
    if (!isset($_POST['yaml_cf_rest_access_nonce'])) {
      return false;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_rest_access_nonce'])), 'yaml_cf_rest_access')) {
      \YamlCF\Helpers\RequestHelper::stopStaleForm();
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $public = isset($_POST['yaml_cf_rest_public_read']) && $_POST['yaml_cf_rest_public_read'] === '1';
    update_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION, $public ? '1' : '0', false);

    $this->notificationManager->success(
      $public
        ? __('Site details and collections can now be read over the REST API without logging in.', 'yaml-custom-fields')
        : __('Reading site details and collections over the REST API now needs a logged-in user who can edit posts.', 'yaml-custom-fields')
    );

    return true;
  }

  /**
   * Save the choice about page caches when site-wide content is saved
   *
   * @return bool Whether this form was submitted
   */
  private function handleClearCachesSubmission() {
    if (!isset($_POST['yaml_cf_clear_caches_nonce'])) {
      return false;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_clear_caches_nonce'])), 'yaml_cf_clear_caches')) {
      \YamlCF\Helpers\RequestHelper::stopStaleForm();
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $clear = isset($_POST['yaml_cf_clear_caches']) && $_POST['yaml_cf_clear_caches'] === '1';
    // '0' rather than false: update_option() does not save a false over a missing option
    update_option(\YamlCF\Cache\CacheManager::CLEAR_ALL_POSTS_OPTION, $clear ? '1' : '0', false);

    $this->notificationManager->success(
      $clear
        ? __('Saving site-wide content will now clear the cache of every page with fields.', 'yaml-custom-fields')
        : __('Saving site-wide content will no longer clear the cache of every page with fields.', 'yaml-custom-fields')
    );

    return true;
  }

  /**
   * Save the choice about what happens to the content if the plugin is deleted
   *
   * Deleting the plugin removes the field content from every page, and that is
   * the site's own writing, not settings. It cannot be undone.
   *
   * @return bool Whether this form was submitted
   */
  private function handleKeepDataSubmission() {
    if (!isset($_POST['yaml_cf_keep_data_nonce'])) {
      return false;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['yaml_cf_keep_data_nonce'])), 'yaml_cf_keep_data')) {
      \YamlCF\Helpers\RequestHelper::stopStaleForm();
    }

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('This action needs administrator permissions.', 'yaml-custom-fields'));
    }

    $keep = isset($_POST['yaml_cf_keep_data']) && $_POST['yaml_cf_keep_data'] === '1';
    update_option(self::KEEP_DATA_OPTION, $keep, false);

    $this->notificationManager->success(
      $keep
        ? __('Deleting the plugin will now leave the field content in place.', 'yaml-custom-fields')
        : __('Deleting the plugin will now remove the field content as well.', 'yaml-custom-fields')
    );

    return true;
  }
}
