<?php
/**
 * Settings Page Template
 * File: templates/settings-page.php
 */

if (!defined('ABSPATH')) {
  exit;
}

// Variables received from controller via extract():
// - $clear_post_caches : bool - Whether saving site-wide content clears every page's cache
// - $rest_public_read : bool - Whether site details and collections are public over the REST API
// - $keep_data_on_uninstall : bool - Whether deleting the plugin keeps the content
?>

<div class="wrap">
  <div class="yaml-cf-admin-container">
    <div class="yaml-cf-header">
      <div class="yaml-cf-header-content">
        <img src="<?php echo esc_url(YAML_CF_PLUGIN_URL . 'assets/icon-256x256.png'); ?>" alt="<?php esc_attr_e('YAML Custom Fields', 'yaml-custom-fields'); ?>" class="yaml-cf-logo" />
        <div class="yaml-cf-header-text">
          <h1><?php esc_html_e('Settings', 'yaml-custom-fields'); ?></h1>
          <p class="yaml-cf-tagline"><?php esc_html_e('How the plugin behaves across the whole site', 'yaml-custom-fields'); ?></p>
        </div>
      </div>
    </div>
    <hr class="wp-header-end">

    <!-- Page caches after site-wide content is saved -->
    <section class="yaml-cf-card yaml-cf-uninstall-card" aria-labelledby="yaml-cf-caches-title">
      <div class="yaml-cf-card-title-row">
        <h2 id="yaml-cf-caches-title">
          <?php \YamlCF\Helpers\Icons::render('page_caches'); ?>
          <?php esc_html_e('Page caches', 'yaml-custom-fields'); ?>
        </h2>
      </div>
      <div class="yaml-cf-card-body">
        <p>
          <?php esc_html_e('Site details, shared content and site parts show on many pages at once. When they are saved, the plugin can clear the cache of every page that has fields, so a caching plugin shows the new content straight away.', 'yaml-custom-fields'); ?>
        </p>
        <form method="post" action="">
          <?php wp_nonce_field('yaml_cf_clear_caches', 'yaml_cf_clear_caches_nonce'); ?>
          <p>
            <label>
              <input type="checkbox" name="yaml_cf_clear_caches" value="1" <?php checked($clear_post_caches); ?> />
              <?php esc_html_e('Clear the cache of every page with fields when site-wide content is saved', 'yaml-custom-fields'); ?>
            </label>
          </p>
          <p class="yaml-cf-hint">
            <?php esc_html_e('On a site with thousands of pages this makes each save slower. Untick it if saving is slow and the caching plugin is purged some other way.', 'yaml-custom-fields'); ?>
          </p>
          <p>
            <button type="submit" class="button"><?php esc_html_e('Save this choice', 'yaml-custom-fields'); ?></button>
          </p>
        </form>
      </div>
    </section>

    <!-- Who can read site-wide content over the REST API -->
    <section class="yaml-cf-card yaml-cf-rest-card" aria-labelledby="yaml-cf-rest-title">
      <div class="yaml-cf-card-title-row">
        <h2 id="yaml-cf-rest-title">
          <?php \YamlCF\Helpers\Icons::render('rest'); ?>
          <?php esc_html_e('REST API', 'yaml-custom-fields'); ?>
        </h2>
      </div>
      <div class="yaml-cf-card-body">
        <p>
          <?php esc_html_e('Other software, such as a front end built in JavaScript or a mobile app, can read the site details and collections over the WordPress REST API. Each page\'s fields are readable wherever the page itself is.', 'yaml-custom-fields'); ?>
        </p>
        <form method="post" action="">
          <?php wp_nonce_field('yaml_cf_rest_access', 'yaml_cf_rest_access_nonce'); ?>
          <p>
            <label>
              <input type="checkbox" name="yaml_cf_rest_public_read" value="1" <?php checked($rest_public_read); ?> />
              <?php esc_html_e('Let anyone read the site details and collections, without logging in', 'yaml-custom-fields'); ?>
            </label>
          </p>
          <p class="yaml-cf-hint">
            <?php esc_html_e('Every item of every collection becomes readable, including items no page shows, and every site detail, including ones no page shows. Leave it unticked if any of them should stay private. Unticked, reading them needs a logged-in user who can edit posts.', 'yaml-custom-fields'); ?>
          </p>
          <p>
            <button type="submit" class="button"><?php esc_html_e('Save this choice', 'yaml-custom-fields'); ?></button>
          </p>
        </form>
      </div>
    </section>

    <!-- What happens if the plugin is deleted -->
    <section class="yaml-cf-card yaml-cf-uninstall-card" aria-labelledby="yaml-cf-uninstall-title">
      <div class="yaml-cf-card-title-row">
        <h2 id="yaml-cf-uninstall-title">
          <?php \YamlCF\Helpers\Icons::render('uninstall'); ?>
          <?php esc_html_e('If this plugin is deleted', 'yaml-custom-fields'); ?>
        </h2>
      </div>
      <div class="yaml-cf-card-body">
        <p>
          <?php esc_html_e('Deleting the plugin removes the schemas, the site details, the collections, and the content filled into the fields on every page and post. Deactivating it changes nothing.', 'yaml-custom-fields'); ?>
        </p>
        <form method="post" action="">
          <?php wp_nonce_field('yaml_cf_keep_data', 'yaml_cf_keep_data_nonce'); ?>
          <p>
            <label>
              <input type="checkbox" name="yaml_cf_keep_data" value="1" <?php checked($keep_data_on_uninstall); ?> />
              <?php esc_html_e('Keep the content if the plugin is deleted', 'yaml-custom-fields'); ?>
            </label>
          </p>
          <p class="yaml-cf-hint">
            <?php esc_html_e('With this ticked, the content stays in the database and comes back if the plugin is installed again. Untick it to have everything removed.', 'yaml-custom-fields'); ?>
          </p>
          <p>
            <button type="submit" class="button"><?php esc_html_e('Save this choice', 'yaml-custom-fields'); ?></button>
          </p>
        </form>
      </div>
    </section>
  </div>
</div>
