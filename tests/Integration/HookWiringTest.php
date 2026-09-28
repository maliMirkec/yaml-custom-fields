<?php
/**
 * Integration: the plugin's hooks, fired the way WordPress fires them
 *
 * The meta box, its save, the admin forms and the value memo used to be hooked
 * up by YAML_Custom_Fields's constructor. YamlCF\Core\HookManager registers them
 * now. These tests go through do_action() rather than calling the handlers, so
 * a hook that is missing, or registered twice, shows up here.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class HookWiringRedirect extends \Exception {}

class HookWiringTest extends WPTestCase {
	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => "fields:\n  - name: title\n    label: Title\n    type: string\n"], false);
		update_option('yaml_cf_template_settings', ['single.php' => true], false);
	}

	public function tearDown(): void {
		$_POST = [];
		remove_all_filters('wp_redirect');
		parent::tearDown();
	}

	public function testSavingAPostSavesItsFieldsThroughSavePost() {
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$_POST = wp_slash([
			'post_ID' => $post_id,
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => ['title' => 'Saved by the hook'],
		]);
		wp_update_post(['ID' => $post_id, 'post_title' => 'Updated']);

		$this->assertSame(['title' => 'Saved by the hook'], get_post_meta($post_id, '_yaml_cf_data', true));
	}

	public function testDeletingAPostTakesItOffTheTrackedList() {
		$post_id = $this->createTestPost([], ['title' => 'x']);
		\YamlCF\Cache\PostTracker::track($post_id);

		wp_delete_post($post_id, true);

		$this->assertNotContains($post_id, \YamlCF\Cache\PostTracker::getAll());
	}

	public function testAnAdminFormIsHandledOnAdminInit() {
		$this->actingAs($this->createTestUser('administrator'));
		$_POST = wp_slash([
			'yaml_cf_save_global_schema_nonce' => wp_create_nonce('yaml_cf_save_global_schema'),
			'global_schema' => "fields:\n  - name: phone\n    type: string\n",
		]);
		add_filter('wp_redirect', function ($location) {
			throw new HookWiringRedirect($location);
		});

		// Core's own admin_init callbacks complain outside a real wp-admin request
		$this->setExpectedIncorrectUsage('wp_add_privacy_policy_content');

		try {
			do_action('admin_init');
			$this->fail('A saved form redirects back to its screen.');
		} catch (HookWiringRedirect $e) {
			$this->assertStringContainsString('page=yaml-cf-edit-global-schema', $e->getMessage());
		}

		$this->assertSame("fields:\n  - name: phone\n    type: string", get_option('yaml_cf_global_schema'));
	}

	/**
	 * The legacy class registers nothing any more, so nothing runs twice
	 */
	public function testTheLegacyClassHooksNothing() {
		global $wp_filter;

		foreach (['admin_init', 'add_meta_boxes', 'edit_form_after_title', 'save_post', 'delete_post', 'updated_post_meta', 'updated_option', 'switch_theme'] as $hook) {
			if (!isset($wp_filter[$hook])) {
				continue;
			}
			foreach ($wp_filter[$hook]->callbacks as $callbacks) {
				foreach ($callbacks as $callback) {
					$function = $callback['function'];
					$this->assertFalse(
						is_array($function) && $function[0] instanceof \YAML_Custom_Fields,
						'YAML_Custom_Fields is still hooked to ' . $hook
					);
					$this->assertNotSame('yaml_cf_flush_resolved_post_fields', $function, 'Still hooked straight to ' . $hook);
				}
			}
		}
	}
}
