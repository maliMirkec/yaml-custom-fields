<?php
/**
 * Integration: exporters produce files the importers accept (formats unchanged from earlier versions)
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class ExportImportRoundTripTest extends WPTestCase {
	private function service($name) {
		return \YamlCF\Core\Plugin::getInstance()->get($name);
	}

	public function testPageDataRoundTrip() {
		$this->actingAs($this->createTestUser('administrator'));
		update_option('yaml_cf_schemas', ['single.php' => "fields:\n  - name: title\n    type: string\n  - name: embed\n    type: code\n"]);

		$source_id = $this->createTestPost(['post_name' => 'round-trip'], [
			'title' => 'Hello',
			'embed' => '__YAMLCF_B64__' . base64_encode('<div>Widget</div>'),
		]);

		$export = $this->service('page_data_exporter')->exportPost($source_id);
		$this->assertSame('single-post', $export['type']);

		// Import into a fresh post with the same slug
		wp_delete_post($source_id, true);
		$target_id = $this->createTestPost(['post_name' => 'round-trip']);
		$result = $this->service('page_data_importer')->importMany(json_decode(wp_json_encode($export), true));

		$this->assertSame(1, $result['imported']);
		$this->assertSame('Hello', ycf_get_field('title', $target_id));
		$this->assertSame('<div>Widget</div>', ycf_get_field('embed', $target_id));
	}

	public function testSettingsRoundTrip() {
		$this->actingAs($this->createTestUser('administrator'));
		update_option('yaml_cf_schemas', ['header.php' => "fields:\n  - name: logo_text\n    type: string\n"]);
		update_option('yaml_cf_partial_data', ['header.php' => ['logo_text' => 'ACME']]);
		update_option('yaml_cf_template_settings', ['header.php' => true]);

		$export = $this->service('settings_exporter')->export();
		delete_option('yaml_cf_schemas');
		delete_option('yaml_cf_partial_data');

		$result = $this->service('settings_importer')->import(json_decode(wp_json_encode($export), true), false);

		$this->assertTrue($result['success']);
		$this->assertSame([], $result['errors']);
		$this->assertSame('ACME', ycf_get_field('logo_text', 'partial:header.php'));
	}

	public function testDataObjectsRoundTrip() {
		$this->actingAs($this->createTestUser('administrator'));
		update_option('yaml_cf_data_object_types', ['companies' => ['name' => 'Companies', 'schema' => "fields:\n  - name: name\n    type: string\n"]]);
		update_option('yaml_cf_data_object_entries_companies', ['entry_1' => ['name' => 'ACME']]);

		$export = $this->service('data_object_exporter')->export();
		delete_option('yaml_cf_data_object_types');
		delete_option('yaml_cf_data_object_entries_companies');

		$result = $this->service('data_object_importer')->import(json_decode(wp_json_encode($export), true));

		$this->assertSame(1, $result['types_imported']);
		$this->assertSame(['entry_1' => ['name' => 'ACME']], ycf_get_data_objects('companies'));
	}
}
