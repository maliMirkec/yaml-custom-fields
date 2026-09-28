<?php
/**
 * Integration: each screen that shows fields names the schema they come from
 *
 * The "Add section" button sends this name to the server, which renders the
 * new section from that schema. Three screens used to send nothing, so the
 * button only said the schema could not be found: the shared content screen
 * (looked for under a page name that does not exist), the collection item
 * screen (never looked for), and a new post (no post ID in the address yet).
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class SchemaSourceTest extends WPTestCase {
	const SCHEMA = "fields:\n  - name: title\n    type: string\n";

	public function tearDown(): void {
		$_GET = [];
		$GLOBALS['post'] = null;
		parent::tearDown();
	}

	private function source($is_post_edit, $page) {
		return \YamlCF\Core\Plugin::getInstance()->get('asset_manager')->schemaSource($is_post_edit, $page);
	}

	public function testThePostEditorNamesThePostsTemplate() {
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA]);
		$post_id = $this->createTestPost();
		$_GET = ['post' => (string) $post_id];

		// The post goes along so the server can check the writer may edit it
		$this->assertSame(['source' => 'template', 'key' => 'single.php', 'post_id' => $post_id], $this->source(true, ''));
	}

	/**
	 * post-new.php has no ?post= yet; WordPress has already made the draft
	 */
	public function testANewPostNamesItsTemplate() {
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA]);
		$post_id = $this->createTestPost(['post_status' => 'auto-draft']);
		$GLOBALS['post'] = get_post($post_id);

		$this->assertSame(['source' => 'template', 'key' => 'single.php', 'post_id' => $post_id], $this->source(true, ''));
	}

	public function testTheSharedContentScreenNamesItsTemplate() {
		update_option('yaml_cf_template_global_schemas', ['page.php' => self::SCHEMA]);
		$_GET = ['page' => 'yaml-cf-manage-template-global', 'template' => 'page.php'];

		$this->assertSame(['source' => 'template_global', 'key' => 'page.php'], $this->source(false, 'yaml-cf-manage-template-global'));
	}

	public function testTheCollectionItemScreenNamesItsCollection() {
		update_option('yaml_cf_data_object_types', ['people' => ['name' => 'People', 'schema' => self::SCHEMA]]);
		$_GET = ['page' => 'yaml-cf-manage-data-object-entries', 'type_id' => 'people'];

		$this->assertSame(['source' => 'data_object', 'key' => 'people'], $this->source(false, 'yaml-cf-manage-data-object-entries'));
	}

	public function testSiteDetailsAndSitePartsKeepTheirSources() {
		update_option('yaml_cf_global_schema', self::SCHEMA);
		update_option('yaml_cf_schemas', ['header.php' => self::SCHEMA]);

		$this->assertSame(['source' => 'global', 'key' => ''], $this->source(false, 'yaml-cf-manage-global-data'));

		$_GET = ['page' => 'yaml-cf-edit-partial', 'template' => 'header.php'];
		$this->assertSame(['source' => 'partial', 'key' => 'header.php'], $this->source(false, 'yaml-cf-edit-partial'));
	}

	public function testAScreenWithoutASchemaNamesNone() {
		$_GET = ['page' => 'yaml-cf-manage-data-object-entries', 'type_id' => 'nothing'];

		$this->assertNull($this->source(false, 'yaml-cf-manage-data-object-entries'));
		$this->assertNull($this->source(false, 'yaml-cf-docs'));
	}
}
