<?php
/**
 * Integration: the schema a post is checked against is worked out, not stored
 *
 * Every save used to write the whole parsed schema into the post as
 * _yaml_cf_schema, although it can be worked out from the post's template. On a
 * site with a couple of thousand pages that is thousands of copies of the same
 * thing, rewritten on every save, and each one froze the schema as it was at
 * that moment: editing a schema left old posts being checked against the old one.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Core\Upgrader;
use YamlCF\Tests\WPTestCase;

class PostSchemaLocatorTest extends WPTestCase {
	const SCHEMA = "fields:\n  - name: hero\n    type: image\n";
	const SCHEMA_WITH_SECOND_IMAGE = "fields:\n  - name: hero\n    type: image\n  - name: banner\n    type: image\n";

	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA], false);
		update_option('yaml_cf_template_settings', ['single.php' => true], false);
	}

	public function tearDown(): void {
		delete_option('yaml_cf_db_version');
		$_POST = [];
		parent::tearDown();
	}

	private function locator() {
		return \YamlCF\Core\Plugin::getInstance()->get('post_schema_locator');
	}

	private function saveThroughTheMetaBox($post_id, array $fields) {
		$_POST = wp_slash([
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => $fields,
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);
	}

	// ------------------------------------------------------ nothing is stored

	public function testSavingAPostDoesNotCopyTheSchemaIntoIt() {
		$this->actingAs($this->createTestUser('administrator'));
		$post_id = $this->createTestPost();

		$this->saveThroughTheMetaBox($post_id, ['hero' => '12']);

		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_schema', true));
	}

	public function testImportingAPostDoesNotCopyTheSchemaIntoIt() {
		$post_id = $this->createTestPost();
		$post = get_post($post_id);

		$importer = \YamlCF\Core\Plugin::getInstance()->get('page_data_importer');
		$importer->importEntry($post, [
			'id' => $post_id,
			'data' => ['hero' => '12'],
			'schema' => ['fields' => [['name' => 'hero', 'type' => 'string']]],
		]);

		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_schema', true));
	}

	// ------------------------------------------------------ it is worked out

	public function testTheSchemaIsFoundFromThePostsTemplate() {
		$post_id = $this->createTestPost();

		$fields = $this->locator()->fieldsForPost(get_post($post_id));

		$this->assertSame('hero', $fields[0]['name']);
	}

	public function testAPostWhoseTemplateHasNoSchemaGetsNothing() {
		delete_option('yaml_cf_schemas');
		$post_id = $this->createTestPost();

		$this->assertNull($this->locator()->fieldsForPost(get_post($post_id)));
	}

	/**
	 * Editing a schema has to take effect at once. This is what the stored copy
	 * got wrong: a post kept being checked against the schema as it was when the
	 * post was last saved, which could be months earlier.
	 */
	public function testEditingASchemaTakesEffectWithoutResavingThePost() {
		$post_id = $this->createTestPost();

		$this->assertCount(1, $this->locator()->fieldsForPost(get_post($post_id)));

		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA_WITH_SECOND_IMAGE], false);

		$this->assertCount(2, $this->locator()->fieldsForPost(get_post($post_id)), 'An edited schema should be seen straight away.');
	}

	/**
	 * A post saved by an older version, on a site the cleanup has not reached
	 * yet, still has its stored copy read.
	 */
	public function testAStoredCopyIsStillUsedWhenTheTemplateHasNoSchema() {
		delete_option('yaml_cf_schemas');
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_schema', ['fields' => [['name' => 'legacy', 'type' => 'image']]]);

		$fields = $this->locator()->fieldsForPost(get_post($post_id));

		$this->assertSame('legacy', $fields[0]['name']);
	}

	// --------------------------------------------------------- the cleanup

	public function testUpgradeRemovesTheStoredCopies() {
		$post_id = $this->createTestPost();
		update_post_meta($post_id, '_yaml_cf_schema', ['fields' => [['name' => 'hero', 'type' => 'image']]]);

		(new Upgrader())->maybeUpgrade();

		$this->assertSame('', get_post_meta($post_id, '_yaml_cf_schema', true));
	}

	/**
	 * Removing the copies must not cost the post its content.
	 */
	public function testUpgradeLeavesTheFieldContentAlone() {
		$post_id = $this->createTestPost([], ['hero' => '12']);
		update_post_meta($post_id, '_yaml_cf_schema', ['fields' => [['name' => 'hero', 'type' => 'image']]]);

		(new Upgrader())->maybeUpgrade();

		$this->assertSame(['hero' => '12'], get_post_meta($post_id, '_yaml_cf_data', true));
	}

	// ------------------------------------------------------------- exporting

	public function testAnExportCarriesTheSchemaTheTemplateUsesNow() {
		$post_id = $this->createTestPost([], ['hero' => '12']);

		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA_WITH_SECOND_IMAGE], false);

		$exporter = \YamlCF\Core\Plugin::getInstance()->get('page_data_exporter');
		$export = $exporter->exportPost($post_id);

		$this->assertCount(2, $export['post']['schema']['fields'], 'The export should carry the current schema.');
	}
}
