<?php
/**
 * Integration: removing a field keeps its content on every screen, not just pages
 *
 * `RemovedFieldRetentionTest` covers pages and posts. Fields also live on site
 * parts, site details, shared fields and collection entries, and each of those
 * screens rebuilt its data from the form in the same way — worse, in fact: they
 * wrote an empty array whenever a form sent nothing, so "nothing rendered" and
 * "delete everything" were the same thing.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\FailsOnPluginWarnings;
use YamlCF\Tests\WPTestCase;

class RemovedFieldRetentionEverywhereTest extends WPTestCase {
	use FailsOnPluginWarnings;

	const TEMPLATE = 'single.php';
	const PARTIAL = 'header.php';
	const COLLECTION = 'people';

	const BOTH = "fields:\n"
		. "  - name: headline\n    label: Headline\n    type: string\n"
		. "  - name: subtitle\n    label: Subtitle\n    type: string\n";

	const ONE = "fields:\n  - name: headline\n    label: Headline\n    type: string\n";

	public function setUp(): void {
		parent::setUp();
		$this->failOnPluginWarnings();
		$this->actingAs($this->createTestUser('administrator'));
	}

	public function tearDown(): void {
		$this->stopFailingOnPluginWarnings();
		$_POST = [];
		delete_option('yaml_cf_data_object_entries_' . self::COLLECTION);
		parent::tearDown();
	}

	/**
	 * Run something that ends in wp_safe_redirect() and exit
	 *
	 * These handlers finish by redirecting, and the exit that follows would take
	 * the test process down with it. Throwing from the wp_redirect filter stops
	 * the handler at the same point a real request would end, before the exit.
	 *
	 * @param callable $run What to run
	 */
	private function runUntilRedirect(callable $run) {
		$thrower = function ($location) {
			throw new \RuntimeException('redirect:' . $location);
		};

		add_filter('wp_redirect', $thrower, 1);

		try {
			$run();
		} catch (\RuntimeException $e) {
			if (strpos($e->getMessage(), 'redirect:') !== 0) {
				throw $e;
			}
		} catch (\WPDieException $e) {
			$this->fail('The handler died: ' . $e->getMessage());
		} finally {
			remove_filter('wp_redirect', $thrower, 1);
			$_POST = [];
			$_GET = [];
		}
	}

	/**
	 * Run the legacy admin_init form handler with a given POST body
	 */
	private function submitForm(array $post) {
		$_POST = wp_slash($post);

		$this->runUntilRedirect(function () {
			\YAML_Custom_Fields::get_instance()->handle_form_submissions();
		});
	}

	// ------------------------------------------------------------ site parts

	public function testASitePartKeepsTheContentOfARemovedField() {
		update_option('yaml_cf_schemas', [self::PARTIAL => self::BOTH], false);
		update_option('yaml_cf_partial_data', [
			self::PARTIAL => ['headline' => 'Headline', 'subtitle' => 'Must survive'],
		], false);

		update_option('yaml_cf_schemas', [self::PARTIAL => self::ONE], false);

		$this->submitForm([
			'yaml_cf_partial_nonce' => wp_create_nonce('yaml_cf_save_partial'),
			'template' => self::PARTIAL,
			'yaml_cf' => ['headline' => 'Edited'],
		]);

		$stored = get_option('yaml_cf_partial_data')[self::PARTIAL];
		$this->assertSame('Edited', $stored['headline']);
		$this->assertSame('Must survive', $stored['subtitle']);
	}

	/**
	 * The screens used to write an empty array when a form sent nothing, which
	 * erased the whole entry rather than leaving it alone.
	 */
	public function testASitePartIsNotWipedWhenTheFormSendsNothing() {
		update_option('yaml_cf_schemas', [self::PARTIAL => self::BOTH], false);
		update_option('yaml_cf_partial_data', [
			self::PARTIAL => ['headline' => 'Headline', 'subtitle' => 'Subtitle'],
		], false);

		// No schema any more, so the form renders and sends nothing at all
		update_option('yaml_cf_schemas', [], false);

		$this->submitForm([
			'yaml_cf_partial_nonce' => wp_create_nonce('yaml_cf_save_partial'),
			'template' => self::PARTIAL,
		]);

		$stored = get_option('yaml_cf_partial_data')[self::PARTIAL];
		$this->assertSame('Headline', $stored['headline']);
		$this->assertSame('Subtitle', $stored['subtitle']);
	}

	// The AJAX save of the same screen is covered in AjaxHandlersTest, which has
	// the harness that AJAX handlers need: calling one directly from here makes
	// check_ajax_referer() end the process.

	// --------------------------------------------------------- site details

	public function testSiteDetailsKeepTheContentOfARemovedField() {
		update_option('yaml_cf_global_schema', self::BOTH, false);
		update_option('yaml_cf_global_data', ['headline' => 'Headline', 'subtitle' => 'Must survive'], false);

		update_option('yaml_cf_global_schema', self::ONE, false);

		$this->submitForm([
			'yaml_cf_global_data_nonce' => wp_create_nonce('yaml_cf_save_global_data'),
			'yaml_cf' => ['headline' => 'Edited'],
		]);

		$stored = get_option('yaml_cf_global_data');
		$this->assertSame('Edited', $stored['headline']);
		$this->assertSame('Must survive', $stored['subtitle']);
	}

	/**
	 * Saving the site details schema said nothing at all when a field was
	 * dropped, so content quietly stopped showing with no explanation.
	 */
	public function testSavingTheSiteDetailsSchemaWarnsAboutDroppedFields() {
		update_option('yaml_cf_global_schema', self::BOTH, false);

		$this->submitForm([
			'yaml_cf_save_global_schema_nonce' => wp_create_nonce('yaml_cf_save_global_schema'),
			'global_schema' => self::ONE,
		]);

		$this->assertStringContainsString('subtitle', $this->queuedNotices());
	}

	// -------------------------------------------------------- shared fields

	public function testSharedFieldsKeepTheContentOfARemovedField() {
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => self::BOTH], false);
		update_option('yaml_cf_template_global_data', [
			self::TEMPLATE => ['headline' => 'Headline', 'subtitle' => 'Must survive'],
		], false);

		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => self::ONE], false);

		$this->submitForm([
			'yaml_cf_save_template_global_data_nonce' => wp_create_nonce('yaml_cf_save_template_global_data'),
			'template' => self::TEMPLATE,
			'yaml_cf' => ['headline' => 'Edited'],
		]);

		$stored = get_option('yaml_cf_template_global_data')[self::TEMPLATE];
		$this->assertSame('Edited', $stored['headline']);
		$this->assertSame('Must survive', $stored['subtitle']);
	}

	public function testSavingTheSharedFieldsSchemaWarnsAboutDroppedFields() {
		update_option('yaml_cf_template_global_schemas', [self::TEMPLATE => self::BOTH], false);

		$this->submitForm([
			'yaml_cf_save_template_global_schema_nonce' => wp_create_nonce('yaml_cf_save_template_global_schema'),
			'template' => self::TEMPLATE,
			'template_global_schema' => self::ONE,
		]);

		$this->assertStringContainsString('subtitle', $this->queuedNotices());
	}

	// ----------------------------------------------------- collection entries

	public function testACollectionEntryKeepsTheContentOfARemovedField() {
		update_option('yaml_cf_data_object_types', [
			self::COLLECTION => ['name' => 'People', 'schema' => self::ONE],
		], false);
		update_option('yaml_cf_data_object_entries_' . self::COLLECTION, [
			'entry_1' => ['headline' => 'Ada', 'subtitle' => 'Must survive'],
		], false);

		$_POST = wp_slash([
			'yaml_cf_save_entry_nonce' => wp_create_nonce('yaml_cf_save_entry'),
			'entry_id' => 'entry_1',
			'yaml_cf' => ['headline' => 'Ada Lovelace'],
		]);
		$_GET = ['type_id' => self::COLLECTION, 'entry' => 'entry_1'];

		ob_start();
		try {
			$this->runUntilRedirect(function () {
				\YamlCF\Core\Plugin::getInstance()->get('data_object_controller')->renderEntries();
			});
		} finally {
			ob_end_clean();
		}

		$entries = get_option('yaml_cf_data_object_entries_' . self::COLLECTION);
		$this->assertSame('Must survive', $entries['entry_1']['subtitle']);
	}

	// ------------------------------------------------------------- importing

	/**
	 * A file exported from a site with a smaller schema should not take content
	 * with it that it says nothing about.
	 */
	public function testImportingDoesNotDestroyFieldsTheFileSaysNothingAbout() {
		update_option('yaml_cf_schemas', [self::TEMPLATE => self::ONE], false);
		update_option('yaml_cf_template_settings', [self::TEMPLATE => true], false);

		$post_id = $this->createTestPost([], ['headline' => 'Headline', 'subtitle' => 'Must survive']);

		$importer = \YamlCF\Core\Plugin::getInstance()->get('page_data_importer');
		$importer->importEntry(get_post($post_id), [
			'id' => $post_id,
			'data' => ['headline' => 'Imported headline'],
		]);

		$stored = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame('Imported headline', $stored['headline']);
		$this->assertSame('Must survive', $stored['subtitle']);
	}

	/**
	 * Everything the notice queue is holding, as one string
	 */
	private function queuedNotices() {
		ob_start();
		try {
			do_action('admin_notices');
		} finally {
			$html = ob_get_clean();
		}

		return $html;
	}
}
