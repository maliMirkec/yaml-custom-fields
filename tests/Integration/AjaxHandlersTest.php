<?php
/**
 * Integration: AJAX handlers (src/Ajax/Handlers) through admin-ajax
 */

namespace YamlCF\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;
use YamlCF\Tests\FailsOnPluginWarnings;

class AjaxHandlersTest extends WP_Ajax_UnitTestCase {
	// WP_Ajax_UnitTestCase switches E_WARNING off for every test, so without
	// this a warning raised inside a handler passes unnoticed here.
	use FailsOnPluginWarnings;

	const SCHEMA = "fields:\n  - name: title\n    type: string\n  - name: body\n    type: rich-text\n  - name: embed\n    type: code\n";

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
		update_option('yaml_cf_schemas', ['single.php' => self::SCHEMA], false);
	}

	public function tearDown(): void {
		$this->stopFailingOnPluginWarnings();
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Call an AJAX action and return the decoded JSON response
	 */
	private function callAjax($action, array $params, $nonce = null) {
		// Both tokens travel with every request; the handler picks the one that
		// matches the capability its action needs.
		$_POST = wp_slash(array_merge([
			'nonce' => $nonce === null ? wp_create_nonce('yaml_cf_nonce') : $nonce,
			'admin_nonce' => $nonce === null ? wp_create_nonce('yaml_cf_admin_nonce') : $nonce,
		], $params));
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
		$_POST = ['nonce' => 'invalid', 'admin_nonce' => 'invalid', 'template' => 'single.php', 'enabled' => 'true'];

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
		// The file said "title" was a code field, which would have let the marker
		// through. The site's own schema says string, so the marker was stripped
		// and what is left is inert text that nothing will ever decode.
		$this->assertSame(base64_encode('<script>x()</script>'), $stored['title']);
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
	/**
	 * Saving a schema is what creates yaml_cf_schemas on most sites, and an
	 * option created without saying otherwise is loaded on every page view,
	 * front end included. The schema editor's save is the path that matters.
	 */
	public function testSavingASchemaDoesNotLeaveItLoadedOnEveryRequest() {
		global $wpdb;

		$this->_setRole('administrator');
		delete_option('yaml_cf_schemas');

		$this->callAjax('yaml_cf_save_schema', [
			'template' => 'single.php',
			'schema' => "fields:\n  - name: headline\n    type: string\n",
		]);

		$schemas = get_option('yaml_cf_schemas', []);
		$this->assertArrayHasKey('single.php', $schemas, 'The schema should have been saved.');

		// No WordPress function reports an option's autoload state, so the column
		// is read directly. Tests are outside the plugin's no-direct-SQL rule.
		$autoload = $wpdb->get_var(
			$wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'yaml_cf_schemas')
		);

		// An option saved with no autoload argument is stored as 'auto', and 'auto'
		// is autoloaded, so the list has to come from WordPress rather than a guess.
		$autoloaded = function_exists('wp_autoload_values_to_autoload')
			? wp_autoload_values_to_autoload()
			: ['yes'];

		$this->assertNotContains($autoload, $autoloaded, 'Schemas should not be autoloaded.');
	}

	/**
	 * The AJAX save of a site part has to agree with the form that saves the
	 * same screen: a field dropped from the schema keeps its content either way,
	 * or the site part gains or loses values depending on which one saved last.
	 */
	public function testSavingASitePartKeepsTheContentOfARemovedField() {
		$this->_setRole('administrator');

		update_option('yaml_cf_schemas', ['header.php' => "fields:\n  - name: headline\n    type: string\n"], false);
		update_option('yaml_cf_partial_data', [
			'header.php' => ['headline' => 'Headline', 'subtitle' => 'Must survive'],
		], false);

		$response = $this->callAjax('yaml_cf_save_partial_data', [
			'template' => 'header.php',
			'data' => wp_json_encode(['headline' => 'Edited']),
		]);

		$this->assertTrue($response['success']);

		$stored = get_option('yaml_cf_partial_data')['header.php'];
		$this->assertSame('Edited', $stored['headline']);
		$this->assertSame('Must survive', $stored['subtitle']);
	}

}
