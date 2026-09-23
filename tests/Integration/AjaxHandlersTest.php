<?php
/**
 * Integration: AJAX handlers (src/Ajax/Handlers) through admin-ajax
 */

namespace YamlCF\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

class AjaxHandlersTest extends WP_Ajax_UnitTestCase {
	const SCHEMA = "fields:\n  - name: title\n    type: string\n  - name: body\n    type: rich-text\n  - name: embed\n    type: code\n";

	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA]);
	}

	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Call an AJAX action and return the decoded JSON response
	 */
	private function callAjax($action, array $params, $nonce = null) {
		$_POST = wp_slash(array_merge(['nonce' => $nonce === null ? wp_create_nonce('yaml_cf_nonce') : $nonce], $params));
		try {
			$this->_handleAjax($action);
		} catch (WPAjaxDieContinueException $e) {
			// Expected: wp_send_json_* ends the request
		}
		$response = json_decode($this->_last_response, true);
		$this->_last_response = '';
		return $response;
	}

	private function importFile(array $posts) {
		return wp_json_encode([
			'plugin' => 'yaml-custom-fields',
			'match_by' => 'id',
			'posts' => $posts,
		]);
	}

	public function testInvalidNonceIsRejected() {
		$this->_setRole('administrator');
		$_POST = ['nonce' => 'invalid', 'template' => 'single.php', 'enabled' => 'true'];

		$this->expectException(WPAjaxDieStopException::class);
		$this->_handleAjax('yaml_cf_save_template_settings');
	}

	public function testEditorWithoutManageOptionsIsRejected() {
		$this->_setRole('editor');

		$response = $this->callAjax('yaml_cf_import_page_data', ['data' => $this->importFile([])]);

		$this->assertFalse($response['success']);
		$this->assertSame('This action needs administrator permissions.', $response['data']);
	}

	public function testSaveTemplateSettings() {
		$this->_setRole('administrator');

		$response = $this->callAjax('yaml_cf_save_template_settings', ['template' => 'single.php', 'enabled' => 'true']);

		$this->assertTrue($response['success']);
		$this->assertTrue($response['data']['has_schema']);
		$this->assertTrue(get_option('yaml_cf_template_settings')['single.php']);
	}

	public function testImportPageDataSanitizesAgainstSiteSchema() {
		$this->_setRole('administrator');
		$post_id = self::factory()->post->create();

		$response = $this->callAjax('yaml_cf_import_page_data', [
			'data' => $this->importFile([[
				'id' => $post_id,
				'data' => [
					'title' => '__YAMLCF_B64__' . base64_encode('<script>x()</script>'),
					'body' => '<p>Keep</p><script>drop()</script>',
				],
				// A schema inside the file must be ignored
				'schema' => ['fields' => [['name' => 'title', 'type' => 'code']]],
			]]),
		]);

		$this->assertTrue($response['success']);
		$this->assertSame(1, $response['data']['imported']);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertStringNotContainsString('__YAMLCF_B64__', $stored['title']);
		$this->assertStringNotContainsString('<script', $stored['body']);
		$this->assertStringContainsString('<p>Keep</p>', $stored['body']);
		$this->assertSame('string', get_post_meta($post_id, '_yaml_cf_schema', true)['fields'][0]['type']);
	}

	public function testImportSettingsSkipsInvalidSchemas() {
		$this->_setRole('administrator');

		$response = $this->callAjax('yaml_cf_import_settings', [
			'merge' => 'false',
			'data' => wp_json_encode([
				'plugin' => 'yaml-custom-fields',
				'settings' => [
					'schemas' => [
						'page.php' => "fields:\n  - name: intro\n    type: text\n",
						'broken.php' => "not: [valid",
					],
					'partial_data' => ['page.php' => ['intro' => '<b>Hi</b>']],
				],
			]),
		]);

		$this->assertTrue($response['success']);
		$this->assertCount(1, $response['data']['errors']);
		$schemas = get_option('yaml_cf_schemas');
		$this->assertArrayHasKey('page.php', $schemas);
		$this->assertArrayNotHasKey('broken.php', $schemas);
		$this->assertSame('Hi', get_option('yaml_cf_partial_data')['page.php']['intro']);
	}

	public function testImportDataObjectsSanitizesSlugsAndEntries() {
		$this->_setRole('administrator');

		$response = $this->callAjax('yaml_cf_import_data_objects', [
			'data' => wp_json_encode([
				'plugin' => 'yaml-custom-fields',
				'type' => 'data_objects',
				'types' => [
					'Uni<script>' => [
						'name' => 'Universities',
						'schema' => "fields:\n  - name: name\n    type: string\n",
						'entries' => ['entry_1' => ['name' => '<i>MIT</i>']],
					],
				],
			]),
		]);

		$this->assertTrue($response['success']);
		$types = get_option('yaml_cf_data_object_types');
		$this->assertArrayHasKey('uniscript', $types);
		$this->assertSame('MIT', get_option('yaml_cf_data_object_entries_uniscript')['entry_1']['name']);
	}

	public function testGetPostsWithDataIsPaginated() {
		$this->_setRole('administrator');
		$post_ids = self::factory()->post->create_many(25);
		foreach ($post_ids as $post_id) {
			update_post_meta($post_id, '_yaml_cf_data', ['title' => 'x']);
		}
		// A post without data is not listed
		self::factory()->post->create();

		$page1 = $this->callAjax('yaml_cf_get_posts_with_data', ['page' => '1', 'per_page' => '20']);
		$page2 = $this->callAjax('yaml_cf_get_posts_with_data', ['page' => '2', 'per_page' => '20']);

		$this->assertTrue($page1['success']);
		$this->assertCount(20, $page1['data']['posts']);
		$this->assertCount(5, $page2['data']['posts']);
		$this->assertSame(25, $page1['data']['total']);
		$this->assertSame(2, $page1['data']['total_pages']);
	}
}
