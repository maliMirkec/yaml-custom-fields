<?php
/**
 * Integration: uninstall.php removes all plugin data
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class UninstallTest extends WPTestCase {
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

		if (!defined('WP_UNINSTALL_PLUGIN')) {
			define('WP_UNINSTALL_PLUGIN', 'yaml-custom-fields/yaml-custom-fields.php');
		}
		include dirname(__DIR__, 2) . '/uninstall.php';

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
}
