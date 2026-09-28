<?php
/**
 * Integration: a field type the editor cannot render
 *
 * A typo in a type used to pass validation silently, render a label with no
 * input under it, and then drop the field's content on the next save, because
 * saving replaces the stored data with whatever the form posted.
 *
 * Schemas saved from now on cannot hold an unknown type (SchemaValidator rejects
 * them), but schemas saved by earlier versions can, so the editor says what is
 * wrong and the stored value is carried over untouched.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class UnknownFieldTypeTest extends WPTestCase {
	/** "strng" is a typo for "string" that older versions accepted */
	const SCHEMA_WITH_TYPO = "fields:\n  - name: headline\n    label: Headline\n    type: string\n  - name: subtitle\n    label: Subtitle\n    type: strng\n";

	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA_WITH_TYPO]);
		update_option('yaml_cf_template_settings', ['single.php' => true]);
	}

	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	private function submit($post_id, array $fields) {
		$_POST = wp_slash([
			'post_ID' => $post_id,
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			'yaml_cf' => $fields,
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);
	}

	/**
	 * The whole point: saving the page must not take the field's content with it.
	 */
	public function testSavingAPageKeepsTheValueOfAFieldItCannotRender() {
		$user_id = $this->createTestUser('administrator');
		$this->actingAs($user_id);

		$post_id = $this->createTestPost([], [
			'headline' => 'Old headline',
			'subtitle' => 'Content that must survive',
		]);

		// The editor renders no input for "subtitle", so the form posts only "headline"
		$this->submit($post_id, ['headline' => 'New headline']);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);

		$this->assertSame('New headline', $stored['headline']);
		$this->assertSame('Content that must survive', $stored['subtitle']);
	}

	/**
	 * Carrying the value over must not resurrect fields the writer really cleared.
	 */
	public function testClearingARenderableFieldStillClearsIt() {
		$user_id = $this->createTestUser('administrator');
		$this->actingAs($user_id);

		$post_id = $this->createTestPost([], ['headline' => 'Old headline']);

		$this->submit($post_id, ['headline' => '']);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);

		$this->assertSame('', $stored['headline']);
	}

	/**
	 * The value stays readable through both accessors while the schema is wrong.
	 */
	public function testTheValueIsStillReadableByTheTheme() {
		$post_id = $this->createTestPost([], ['subtitle' => 'Content that must survive']);

		$this->assertSame('Content that must survive', yaml_cf_get_field('subtitle', $post_id));

		$all = yaml_cf_get_fields($post_id);
		$this->assertSame('Content that must survive', $all['subtitle']);
	}

	/**
	 * The writer is told what is wrong instead of being shown an empty label.
	 */
	public function testTheEditorSaysTheTypeIsUnknown() {
		$user_id = $this->createTestUser('administrator');
		$this->actingAs($user_id);
		set_current_screen('post');

		$post_id = $this->createTestPost([], ['subtitle' => 'Content that must survive']);
		$post = get_post($post_id);

		ob_start();
		\YAML_Custom_Fields::get_instance()->render_schema_meta_box($post);
		$html = ob_get_clean();

		$this->assertStringContainsString('yaml-cf-unknown-type', $html);
		$this->assertStringContainsString('strng', $html);
	}

	/**
	 * A schema with the typo cannot be saved any more, so this state is only
	 * ever reached through data left by an earlier version.
	 */
	public function testTheSchemaCannotBeSavedWithTheTypo() {
		$validator = \YamlCF\Core\Plugin::getInstance()->get('schema_validator');

		$result = $validator->validate(self::SCHEMA_WITH_TYPO, 'single.php');

		$this->assertFalse($result['valid']);
		$this->assertStringContainsString('Did you mean "string"', $result['message']);
	}

	/**
	 * A bundle made on an older site may carry a schema with a type this version
	 * refuses to save. Importing must still bring the template across: dropping
	 * the whole schema would lose every other field on it.
	 */
	public function testImportKeepsASchemaWithAnUnknownTypeAndSaysSo() {
		$importer = \YamlCF\Core\Plugin::getInstance()->get('settings_importer');

		$result = $importer->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => [
				'schemas' => ['page.php' => self::SCHEMA_WITH_TYPO],
			],
		]);

		$this->assertTrue($result['success']);

		$schemas = get_option('yaml_cf_schemas', []);
		$this->assertArrayHasKey('page.php', $schemas, 'The schema should have been imported, not skipped.');
		$this->assertStringContainsString('headline', $schemas['page.php']);

		$this->assertNotEmpty($result['warnings']);
		$this->assertStringContainsString('strng', implode(' ', $result['warnings']));
	}

	/**
	 * A schema that is broken outright is still refused, warnings or not.
	 */
	public function testImportStillSkipsASchemaThatCannotBeRead() {
		$importer = \YamlCF\Core\Plugin::getInstance()->get('settings_importer');

		$result = $importer->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => [
				'schemas' => ['page.php' => "fields:\n  - name: headline\n"],
			],
		]);

		$schemas = get_option('yaml_cf_schemas', []);
		$this->assertArrayNotHasKey('page.php', $schemas);
		$this->assertNotEmpty($result['errors']);
	}

}
