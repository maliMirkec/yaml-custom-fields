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

		$response = $this->callAjax('yaml_cf_import_bundle', ['data' => $this->importFile([]), 'selection' => ['posts:1']]);

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

	/**
	 * Template switches and site details switches share one option, keyed by
	 * template name and "<name>_use_global". A name that is empty, or already
	 * ends in _use_global, would write a key that means something else.
	 */
	public function testTemplateTogglesRefuseUnusableNames() {
		$this->_setRole('administrator');
		$before = get_option('yaml_cf_template_settings', []);

		foreach (['', 'single.php_use_global'] as $template) {
			$save = $this->callAjax('yaml_cf_save_template_settings', ['template' => $template, 'enabled' => 'true']);
			$toggle = $this->callAjax('yaml_cf_toggle_use_global', ['template' => $template, 'use_global' => 'true']);

			$this->assertFalse($save['success'], 'Saved a switch for "' . $template . '"');
			$this->assertFalse($toggle['success'], 'Saved a site details switch for "' . $template . '"');
		}

		$this->assertSame($before, get_option('yaml_cf_template_settings', []));
	}

	/**
	 * The importers below are what the import wizard (yaml_cf_import_bundle)
	 * runs; they are called directly so each is tested on its own.
	 */
	public function testImportPageDataSanitizesAgainstSiteSchema() {
		$this->_setRole('administrator');
		$post_id = self::factory()->post->create();

		$result = \YamlCF\Core\Plugin::getInstance()->get('page_data_importer')->importMany(json_decode($this->importFile([[
			'id' => $post_id,
			'data' => [
				'title' => '__YAMLCF_B64__' . base64_encode('<script>x()</script>'),
				'body' => '<p>Keep</p><script>drop()</script>',
			],
			// A schema inside the file must be ignored
			'schema' => ['fields' => [['name' => 'title', 'type' => 'code']]],
		]]), true));

		$this->assertTrue($result['success']);
		$this->assertSame(1, $result['imported']);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertStringNotContainsString('__YAMLCF_B64__', $stored['title']);
		$this->assertStringNotContainsString('<script', $stored['body']);
		$this->assertStringContainsString('<p>Keep</p>', $stored['body']);
		// The file said "title" was a code field, which would have let the marker
		// through. The site's own schema says string, so the marker was stripped
		// and what is left is inert text that nothing will ever decode.
		$this->assertSame(base64_encode('<script>x()</script>'), $stored['title']);
	}

	/**
	 * A file that names only some fields changes those and leaves the rest. It
	 * used to empty every field of the schema that the file did not mention.
	 */
	public function testImportingPageDataLeavesFieldsTheFileDoesNotName() {
		$this->_setRole('administrator');
		$post_id = self::factory()->post->create();
		update_post_meta($post_id, '_yaml_cf_data', ['title' => 'Kept title', 'body' => '<p>Kept body</p>']);

		\YamlCF\Core\Plugin::getInstance()->get('page_data_importer')->importMany(json_decode($this->importFile([[
			'id' => $post_id,
			'data' => ['title' => 'Imported title', 'embed' => ''],
		]]), true));

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('Imported title', $stored['title']);
		$this->assertSame('<p>Kept body</p>', $stored['body'], 'A field the file does not name is left alone.');
		$this->assertSame('', $stored['embed'], 'A field the file names empty is emptied.');
	}

	/**
	 * The import wizard always merges settings. Merging kept every template the
	 * file did not name, but replaced a named one's values wholesale.
	 */
	public function testMergingSitePartContentKeepsFieldsTheFileDoesNotName() {
		$this->_setRole('administrator');
		update_option('yaml_cf_schemas', ['header.php' => "fields:\n  - name: tagline\n    type: string\n  - name: phone\n    type: string\n"], false);
		update_option('yaml_cf_partial_data', ['header.php' => ['tagline' => 'Old tagline', 'phone' => '555-0100']], false);

		\YamlCF\Core\Plugin::getInstance()->get('settings_importer')->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => ['partial_data' => ['header.php' => ['tagline' => 'New tagline']]],
		], true);

		$this->assertSame(['tagline' => 'New tagline', 'phone' => '555-0100'], get_option('yaml_cf_partial_data')['header.php']);
	}

	public function testImportSettingsSkipsInvalidSchemas() {
		$this->_setRole('administrator');

		$result = \YamlCF\Core\Plugin::getInstance()->get('settings_importer')->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => [
				'schemas' => [
					'page.php' => "fields:\n  - name: intro\n    type: text\n",
					'broken.php' => "not: [valid",
				],
				'partial_data' => ['page.php' => ['intro' => '<b>Hi</b>']],
			],
		], false);

		$this->assertTrue($result['success']);
		$this->assertCount(1, $result['errors']);
		$schemas = get_option('yaml_cf_schemas');
		$this->assertArrayHasKey('page.php', $schemas);
		$this->assertArrayNotHasKey('broken.php', $schemas);
		$this->assertSame('Hi', get_option('yaml_cf_partial_data')['page.php']['intro']);
	}

	/**
	 * Image IDs in an export belong to the site it came from. The page and
	 * collection imports already cleared those that point at nothing here; the
	 * site details, site parts and shared content now do too.
	 */
	public function testImportSettingsClearsImagesThatAreNotHere() {
		$this->_setRole('administrator');
		$image = self::factory()->attachment->create_object(['file' => 'logo.jpg', 'post_mime_type' => 'image/jpeg']);
		$schema = "fields:\n  - name: logo\n    type: image\n  - name: year\n    type: string\n";

		\YamlCF\Core\Plugin::getInstance()->get('settings_importer')->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => [
				'global_schema' => $schema,
				'global_data' => ['logo' => '999999', 'year' => '2024'],
				'schemas' => ['header.php' => $schema],
				'partial_data' => ['header.php' => ['logo' => (string) $image, 'year' => '2024']],
			],
		], false);

		$site = get_option('yaml_cf_global_data');
		$this->assertSame('', $site['logo'], 'An image from the other site is cleared.');
		$this->assertSame('2024', $site['year'], 'A number in a text field is not an image.');
		$this->assertSame((string) $image, get_option('yaml_cf_partial_data')['header.php']['logo']);
	}

	public function testImportDataObjectsSanitizesSlugsAndEntries() {
		$this->_setRole('administrator');

		$result = \YamlCF\Core\Plugin::getInstance()->get('data_object_importer')->import([
			'plugin' => 'yaml-custom-fields',
			'type' => 'data_objects',
			'types' => [
				'Uni<script>' => [
					'name' => 'Universities',
					'schema' => "fields:\n  - name: name\n    type: string\n",
					'entries' => ['entry_1' => ['name' => '<i>MIT</i>']],
				],
			],
		]);

		$this->assertTrue($result['success']);
		$types = get_option('yaml_cf_data_object_types');
		$this->assertArrayHasKey('uniscript', $types);
		// Read through the repository: items live in an option each since 2.0.0
		$entry = (new \YamlCF\Data\Repositories\DataObjectRepository())->getEntry('uniscript', 'entry_1');
		$this->assertSame('MIT', $entry['name']);
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

		$_POST = wp_slash([
			'yaml_cf_save_schema_nonce' => wp_create_nonce('yaml_cf_save_schema'),
			'template' => 'single.php',
			'schema' => "fields:\n  - name: headline\n    type: string\n",
		]);
		// The form ends by redirecting; stop there instead of at exit
		$stop = function () {
			throw new \RuntimeException('redirected');
		};
		add_filter('wp_redirect', $stop, 1);
		try {
			\YamlCF\Core\Plugin::getInstance()->get('form_handler')->handleSubmissions();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirected', $e->getMessage());
		} finally {
			remove_filter('wp_redirect', $stop, 1);
		}

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
	 * The actions nothing in the plugin called were removed in 2.0.0. Each was
	 * a second copy of a save the admin screens do through their forms, and
	 * they had drifted apart: one wiped a site part when sent bad JSON.
	 */
	public function testTheRemovedActionsAreNotRegistered() {
		foreach (['save_schema', 'get_schema', 'get_partial_data', 'save_partial_data', 'export_settings', 'import_settings', 'export_page_data', 'import_page_data', 'import_data_objects'] as $action) {
			$this->assertFalse(has_action('wp_ajax_yaml_cf_' . $action), 'yaml_cf_' . $action . ' is still registered');
		}
	}
}
