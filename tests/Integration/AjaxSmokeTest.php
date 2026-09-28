<?php
/**
 * Integration: every AJAX handler survives every shape of schema
 *
 * The companion to AdminScreenSmokeTest. That one covers what the admin screens
 * draw; this covers what the browser asks for once they are on screen — saving,
 * importing, exporting, adding a section, and the two pickers.
 *
 * Every handler is called against each schema in the matrix, as an administrator
 * and again as a writer. WP_DEBUG is on and phpunit-integration.xml.dist turns
 * notices and warnings into exceptions, so a PHP notice inside any handler fails
 * the test and names the line that raised it.
 *
 * A handler answering "no" is fine — a writer is *supposed* to be refused the
 * administrator actions. What is not fine is a PHP warning, a crash, or a reply
 * that is not JSON, because the browser cannot do anything sensible with those.
 */

namespace YamlCF\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;
use YamlCF\Tests\FailsOnPluginWarnings;

class AjaxSmokeTest extends WP_Ajax_UnitTestCase {
	/**
	 * WP_Ajax_UnitTestCase switches E_WARNING off for every test, which would
	 * hide the very warnings this file is here to catch.
	 */
	use FailsOnPluginWarnings;

	const TEMPLATE = 'single.php';
	const PARTIAL = 'header.php';
	const COLLECTION = 'people';

	/** @var int */
	private $post_id;

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
	}

	public function tearDown(): void {
		$this->stopFailingOnPluginWarnings();
		$_POST = [];
		$_GET = [];
		parent::tearDown();
	}

	/**
	 * The same matrix AdminScreenSmokeTest uses, so both cover the same ground
	 *
	 * @return array<string, array{0: string}>
	 */
	public function schemas() {
		return (new AdminScreenSmokeTest('schemas'))->schemas();
	}

	private function applyEverywhere($yaml) {
		update_option('yaml_cf_schemas', [
			self::TEMPLATE => $yaml,
			self::PARTIAL => $yaml,
		], false);
		update_option('yaml_cf_template_settings', [
			self::TEMPLATE => true,
			self::PARTIAL => true,
			self::TEMPLATE . '_use_global' => true,
		], false);
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => $yaml], false);
		update_option('yaml_cf_template_global_data', [self::TEMPLATE => ['headline' => 'Shared']], false);
		update_option('yaml_cf_global_schema', $yaml, false);
		update_option('yaml_cf_global_data', ['headline' => 'Site'], false);
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => $yaml],
		], false);
		update_option('yaml_cf_data_object_entries_' . self::COLLECTION, [
			'entry_1' => ['headline' => 'Ada', 'a_string' => 'Ada'],
		], false);

		set_transient('yaml_cf_templates_' . get_stylesheet(), [
			'templates' => [['file' => self::TEMPLATE, 'name' => 'Single', 'path' => '']],
			'partials' => [['file' => self::PARTIAL, 'name' => 'Header', 'path' => '']],
		], HOUR_IN_SECONDS);

		$this->post_id = self::factory()->post->create(['post_title' => 'Smoke']);
		update_post_meta($this->post_id, '_yaml_cf_data', ['headline' => 'Page', 'a_string' => 'Page']);
		update_post_meta($this->post_id, '_yaml_cf_imported', true);
	}

	/**
	 * Every action, with parameters plausible enough to get past the front door
	 *
	 * @param string $yaml Schema in play, so saves send something valid
	 * @return array<string, array<string, string>>
	 */
	private function actions($yaml) {
		$import_file = wp_json_encode([
			'plugin' => 'yaml-custom-fields',
			'match_by' => 'id',
			'posts' => [['id' => $this->post_id, 'data' => ['headline' => 'Imported']]],
			'settings' => [
				'schemas' => [self::TEMPLATE => $yaml],
				'global_data' => ['headline' => 'Imported site'],
				'template_settings' => [self::TEMPLATE => true],
			],
			'types' => [self::COLLECTION => ['name' => 'People', 'schema' => $yaml, 'entries' => []]],
		]);

		return [
			'yaml_cf_save_template_settings' => ['template' => self::TEMPLATE, 'enabled' => 'true'],
			'yaml_cf_refresh_templates' => [],
			'yaml_cf_toggle_use_global' => ['template' => self::TEMPLATE, 'use_global' => 'true'],
			'yaml_cf_get_posts_with_data' => ['page' => '1', 'per_page' => '20'],
			'yaml_cf_export_bundle' => [
				'match_by' => 'id',
				'post_ids' => wp_json_encode([$this->post_id]),
				'selection' => wp_json_encode(['setup' => true, 'posts' => true, 'lists' => true]),
			],
			'yaml_cf_inspect_bundle' => ['data' => $import_file],
			'yaml_cf_import_bundle' => [
				'data' => $import_file,
				'selection' => wp_json_encode(['setup' => true, 'posts' => true, 'lists' => true]),
			],
			'yaml_cf_render_block_item' => [
				'source' => 'template',
				'key' => self::TEMPLATE,
				'post_id' => (string) $this->post_id,
				'field_name' => 'sections',
				'block_type' => 'banner',
				'index' => '0',
			],
			'yaml_cf_search_terms' => ['taxonomy' => 'category', 'search' => 'a', 'limit' => '10'],
			'yaml_cf_search_data_objects' => ['object_type' => self::COLLECTION, 'search' => 'a', 'limit' => '10'],
		];
	}

	/**
	 * Call an action and fail on anything the browser could not make sense of
	 *
	 * @param string $action Action name
	 * @param array $params POST parameters
	 */
	private function callQuietly($action, array $params) {
		$_POST = wp_slash(array_merge([
			'nonce' => wp_create_nonce('yaml_cf_nonce'),
			'admin_nonce' => wp_create_nonce('yaml_cf_admin_nonce'),
			'action' => $action,
		], $params));

		try {
			$this->_handleAjax($action);
		} catch (WPAjaxDieContinueException $e) {
			// wp_send_json_* ends the request here, which is the normal path
			unset($e);
		} catch (WPAjaxDieStopException $e) {
			// A refusal. Expected for a writer calling an administrator action.
			$this->_last_response = '';
			return;
		} catch (\Throwable $e) {
			$raw = $this->_last_response;
			$this->_last_response = '';
			// The file and line are the point: they name the code that warned
			$this->fail($action . ' raised ' . get_class($e) . ': ' . $e->getMessage()
				. ' at ' . $e->getFile() . ':' . $e->getLine()
				. ($raw === '' ? '' : ' (partial output: ' . substr($raw, 0, 200) . ')'));
		}

		$raw = $this->_last_response;
		$this->_last_response = '';

		if ($raw === '') {
			// Nothing sent: the handler refused before replying
			return;
		}

		$decoded = json_decode($raw, true);

		$this->assertNotNull(
			$decoded,
			$action . ' replied with something that is not JSON: ' . substr($raw, 0, 200)
		);
		$this->assertArrayHasKey('success', $decoded, $action . ' replied without a success flag');
	}

	/**
	 * @dataProvider schemas
	 */
	public function testEveryHandlerAnswersAnAdministratorCleanly($yaml) {
		$this->_setRole('administrator');
		$this->applyEverywhere($yaml);

		foreach ($this->actions($yaml) as $action => $params) {
			$this->callQuietly($action, $params);
		}
	}

	/**
	 * A writer reaches the same endpoints. Most must refuse, and refusing is not
	 * the same as falling over: no warnings, and a reply the browser can read.
	 *
	 * @dataProvider schemas
	 */
	public function testEveryHandlerAnswersAWriterCleanly($yaml) {
		$this->applyEverywhere($yaml);
		$this->_setRole('contributor');

		foreach ($this->actions($yaml) as $action => $params) {
			$this->callQuietly($action, $params);
		}
	}

	/**
	 * The parameters a real browser sends are not the only ones a handler can
	 * receive. Missing and nonsense values must not produce warnings either.
	 *
	 * @dataProvider schemas
	 */
	public function testEveryHandlerCopesWithMissingParameters($yaml) {
		$this->_setRole('administrator');
		$this->applyEverywhere($yaml);

		foreach (array_keys($this->actions($yaml)) as $action) {
			$this->callQuietly($action, []);
		}
	}

	/**
	 * @dataProvider schemas
	 */
	public function testEveryHandlerCopesWithNonsenseParameters($yaml) {
		$this->_setRole('administrator');
		$this->applyEverywhere($yaml);

		$nonsense = [
			'template' => 'no-such-template.php',
			'schema' => 'not: [valid: yaml',
			'data' => '{not json',
			'selection' => 'null',
			'post_ids' => 'null',
			'taxonomy' => 'no_such_taxonomy',
			'object_type' => 'no_such_collection',
			'source' => 'no_such_source',
			'key' => 'nothing',
			'field_name' => '',
			'block_type' => '',
			'index' => '-1',
			'page' => '0',
			'per_page' => '-5',
			'limit' => 'abc',
			'enabled' => 'maybe',
			'use_global' => 'maybe',
			'match_by' => 'nothing',
			'merge' => 'maybe',
			'search' => str_repeat('x', 300),
		];

		foreach (array_keys($this->actions($yaml)) as $action) {
			$this->callQuietly($action, $nonsense);
		}
	}
}
