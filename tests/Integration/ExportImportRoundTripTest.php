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

	/**
	 * An imported item is labelled by the collection's first field.
	 *
	 * The label is what the admin list and every picker show. It is written into
	 * the index at import time, so an import that does not say which field labels
	 * an item leaves the index holding whichever value came first in the file.
	 */
	public function testImportedItemsAreLabelledByTheFirstSchemaField() {
		$this->actingAs($this->createTestUser('administrator'));

		// "name" labels an item; the file happens to list "code" first.
		$bundle = [
			'plugin' => 'yaml-custom-fields',
			'type' => 'data_objects',
			'types' => [
				'companies' => [
					'name' => 'Companies',
					'schema' => "fields:\n  - name: name\n    type: string\n  - name: code\n    type: string\n",
					'entries' => [
						'entry_1' => ['code' => 'AC-1', 'name' => 'ACME'],
					],
				],
			],
		];

		$result = $this->service('data_object_importer')->import($bundle);
		$this->assertSame(1, $result['types_imported']);

		$index = (new \YamlCF\Data\EntryStore())->index('companies');

		$this->assertSame(
			['entry_1' => 'ACME'],
			$index,
			'The label must come from the schema\'s first field, not the file\'s key order.'
		);
	}

	/**
	 * Looking one item up does not read every item of every collection.
	 *
	 * Each item is an option of its own since 2.0.0, so resolving a stored id by
	 * asking each collection for all of its items would read the whole database
	 * on a front-end page view.
	 */
	public function testResolvingAStoredItemDoesNotReadEveryItem() {
		$this->actingAs($this->createTestUser('administrator'));

		update_option('yaml_cf_data_object_types', [
			'people' => ['name' => 'People', 'schema' => "fields:\n  - name: name\n    type: string\n"],
			'companies' => ['name' => 'Companies', 'schema' => "fields:\n  - name: name\n    type: string\n"],
		]);

		$store = new \YamlCF\Data\EntryStore();
		for ($i = 0; $i < 40; $i++) {
			$store->save('people', 'person_' . $i, ['name' => 'Person ' . $i], 'name');
		}
		$store->save('companies', 'acme', ['name' => 'ACME'], 'name');

		update_option('yaml_cf_schemas', ['single.php' => "fields:\n  - name: employer\n    type: data_object\n"]);
		$post_id = $this->createTestPost(['post_name' => 'lookup'], ['employer' => 'acme']);

		// Warm the option cache so the count below reflects only this lookup.
		wp_cache_flush();
		$before = get_num_queries();

		$entry = ycf_get_data_object('employer', $post_id);

		$queries = get_num_queries() - $before;

		$this->assertSame(['name' => 'ACME'], $entry);
		$this->assertLessThan(
			40,
			$queries,
			'Resolving one id must not read every item of every collection.'
		);
	}
}
