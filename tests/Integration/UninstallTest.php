<?php
/**
 * Integration: uninstall.php removes all plugin data
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class UninstallTest extends WPTestCase {
	public function tearDown(): void {
		delete_option('yaml_cf_keep_data_on_uninstall');
		parent::tearDown();
	}

	/**
	 * Run the uninstall routine.
	 *
	 * The file declares a function, so it can only be included once per process.
	 * After the first time, the function it left behind is called instead.
	 */
	private function runUninstall() {
		if (!defined('WP_UNINSTALL_PLUGIN')) {
			define('WP_UNINSTALL_PLUGIN', 'yaml-custom-fields/yaml-custom-fields.php');
		}

		if (function_exists('yaml_cf_uninstall_site')) {
			yaml_cf_uninstall_site();
			return;
		}

		include dirname(__DIR__, 2) . '/uninstall.php';
	}

	public function testUninstallRemovesOptionsMetaAndTransients() {
		update_option('yaml_cf_schemas', ['page.php' => 'fields: []']);
		update_option('yaml_cf_tracked_posts', [1]);
		update_option('yaml_cf_data_object_types', ['companies' => ['name' => 'Companies', 'schema' => '']]);
		update_option('yaml_cf_data_object_entries_companies', ['entry_1' => []]);
		update_option('keep_me_yaml_cf_data_object_entries_x', 'unrelated');
		set_transient('yaml_cf_templates_default', ['cached']);
		$post_id = $this->createTestPost([], ['title' => 'x']);
		update_post_meta($post_id, '_yaml_cf_imported', true);
		update_post_meta($post_id, '_yamlXcfXother', 'unrelated');

		$this->runUninstall();

		$this->assertFalse(get_option('yaml_cf_schemas'));
		$this->assertFalse(get_option('yaml_cf_tracked_posts'));
		$this->assertFalse(get_option('yaml_cf_data_object_types'));
		$this->assertFalse(get_option('yaml_cf_data_object_entries_companies'));
		$this->assertFalse(get_transient('yaml_cf_templates_default'));
		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_data', true));
		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_imported', true));

		// LIKE patterns are escaped: similar names from other plugins survive
		$this->assertSame('unrelated', get_option('keep_me_yaml_cf_data_object_entries_x'));
		$this->assertSame('unrelated', get_post_meta($post_id, '_yamlXcfXother', true));
	}
	/**
	 * The field content is the site's own writing, and removing it cannot be
	 * undone. An administrator can ask on the plugin's page for it to be kept.
	 */
	public function testUninstallLeavesEverythingAloneWhenAskedTo() {
		update_option('yaml_cf_keep_data_on_uninstall', true, false);
		update_option('yaml_cf_schemas', ['page.php' => 'fields: []'], false);
		$post_id = $this->createTestPost([], ['title' => 'Still here']);

		$this->runUninstall();

		$this->assertSame(['page.php' => 'fields: []'], get_option('yaml_cf_schemas'));
		$this->assertSame(['title' => 'Still here'], get_post_meta($post_id, '_yaml_cf_data', true));
	}

	/**
	 * Left unticked, which is how it starts, nothing changes from before.
	 */
	public function testUninstallStillRemovesEverythingByDefault() {
		update_option('yaml_cf_keep_data_on_uninstall', false, false);
		update_option('yaml_cf_schemas', ['page.php' => 'fields: []'], false);
		$post_id = $this->createTestPost([], ['title' => 'Goes away']);

		$this->runUninstall();

		$this->assertFalse(get_option('yaml_cf_schemas'));
		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_data', true));
	}

}
