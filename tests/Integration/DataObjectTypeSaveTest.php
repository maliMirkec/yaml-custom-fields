<?php
/**
 * Integration: saving a collection cannot overwrite a different one
 *
 * The short name keys the collection, its items and every schema that points at
 * it. Adding a collection under a short name already in use used to replace
 * that collection's name and schema without a word, and the read-only short
 * name on the edit screen was only read-only in the browser.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class DataObjectTypeSaveTest extends WPTestCase {
	const EXISTING_SCHEMA = "fields:\n  - name: name\n    type: string\n";
	const OTHER_SCHEMA = "fields:\n  - name: title\n    type: string\n";

	public function setUp(): void {
		parent::setUp();
		$this->actingAs($this->createTestUser('administrator'));

		update_option('yaml_cf_data_object_types', [
			'people' => ['name' => 'People', 'schema' => self::EXISTING_SCHEMA],
		], false);
	}

	public function tearDown(): void {
		$_POST = [];
		$_GET = [];
		\YamlCF\Core\Plugin::getInstance()->get('notification_manager')->clear();
		parent::tearDown();
	}

	private function submit(array $fields) {
		$_POST = wp_slash(array_merge([
			'yaml_cf_save_data_object_type_nonce' => wp_create_nonce('yaml_cf_save_data_object_type'),
		], $fields));

		\YamlCF\Core\Plugin::getInstance()->get('data_object_controller')->handleFormSubmissions();
	}

	private function types() {
		return (array) get_option('yaml_cf_data_object_types', []);
	}

	public function testAddingACollectionUnderATakenShortNameIsRefused() {
		$this->submit([
			'type_name' => 'Staff',
			'type_slug' => 'people',
			'schema' => self::OTHER_SCHEMA,
		]);

		$this->assertSame('People', $this->types()['people']['name']);
		$this->assertSame(self::EXISTING_SCHEMA, $this->types()['people']['schema']);

		ob_start();
		do_action('admin_notices');
		$html = ob_get_clean();
		$this->assertStringContainsString('already a collection', $html);
	}

	/**
	 * Each item is stored under an option named after the short name, and
	 * option names hold 191 characters
	 */
	public function testAShortNameTooLongToStoreItemsUnderIsRefused() {
		$slug = str_repeat('a', \YamlCF\Data\EntryStore::MAX_SLUG_LENGTH + 1);

		$this->submit([
			'type_name' => 'Long',
			'type_slug' => $slug,
			'schema' => self::OTHER_SCHEMA,
		]);

		$this->assertArrayNotHasKey($slug, $this->types());
	}

	public function testEditingKeepsTheShortNameWhateverTheFormSends() {
		$_GET['type_id'] = 'people';

		$this->submit([
			'type_name' => 'People and teams',
			'type_slug' => 'something_else',
			'schema' => self::OTHER_SCHEMA,
		]);

		$types = $this->types();
		$this->assertArrayNotHasKey('something_else', $types);
		$this->assertSame('People and teams', $types['people']['name']);
		$this->assertSame(trim(self::OTHER_SCHEMA), $types['people']['schema']);
	}

	/**
	 * Items are listed and picked by the label worked out when each was saved.
	 * A schema that puts another field first used to leave every old label in
	 * place until each item happened to be saved again.
	 */
	public function testPuttingAnotherFieldFirstRelabelsTheItems() {
		update_option('yaml_cf_data_object_types', [
			'people' => ['name' => 'People', 'schema' => "fields:\n  - name: name\n    type: string\n  - name: email\n    type: string\n"],
		], false);
		$repository = new \YamlCF\Data\Repositories\DataObjectRepository();
		$repository->saveEntry('people', 'entry_a', ['name' => 'Ana', 'email' => 'ana@example.org'], 'name');
		$this->assertSame(['entry_a' => 'Ana'], $repository->getLabels('people'));

		$_GET['type_id'] = 'people';
		$this->submit([
			'type_name' => 'People',
			'type_slug' => 'people',
			'schema' => "fields:\n  - name: email\n    type: string\n  - name: name\n    type: string\n",
		]);

		$this->assertSame(['entry_a' => 'ana@example.org'], $repository->getLabels('people'));
	}

	/**
	 * A section break or an info box holds no value, so it cannot label an item
	 */
	public function testTheLabelFieldSkipsSectionsAndInfoBoxes() {
		$schema = ['fields' => [
			['name' => 'intro', 'type' => 'info'],
			['name' => 'details', 'type' => 'section'],
			['name' => 'title', 'type' => 'string'],
		]];

		$this->assertSame('title', \YamlCF\Data\EntryStore::labelFieldOf($schema));
		$this->assertSame('', \YamlCF\Data\EntryStore::labelFieldOf(null));
	}
}
