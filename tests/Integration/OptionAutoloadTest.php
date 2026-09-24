<?php
/**
 * Integration: plugin options stay out of the autoloaded set
 *
 * Schemas, shared content and site details are only read when something asks
 * for them, but they used to be stored as autoloaded options, so WordPress read
 * every one of them into memory on every request, front end included. On a site
 * with a handful of templates that is tens of kilobytes of YAML and page content
 * per page view.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Core\Upgrader;
use YamlCF\Tests\WPTestCase;

class OptionAutoloadTest extends WPTestCase {
	public function tearDown(): void {
		delete_option('yaml_cf_db_version');
		delete_option('yaml_cf_data_object_entries_people');
		delete_option('yaml_cf_partial_data');
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Whether WordPress would load this option on every request.
	 *
	 * The set of values that count is asked of WordPress rather than written out
	 * here. It is wider than it looks: an option saved with no autoload argument
	 * is stored as 'auto', and 'auto' is autoloaded.
	 *
	 * Reading the column directly is the only way to see this: no WordPress
	 * function reports an option's autoload state. Tests are outside the
	 * plugin's no-direct-SQL rule, which is about the shipped code.
	 *
	 * @param string $option Option name
	 * @return bool
	 */
	private function isAutoloaded($option) {
		global $wpdb;

		$raw = $wpdb->get_var(
			$wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option)
		);

		$autoloaded = function_exists('wp_autoload_values_to_autoload')
			? wp_autoload_values_to_autoload()
			: ['yes'];

		return in_array($raw, $autoloaded, true);
	}

	/**
	 * Put an option in the state a pre-2.0.0 install left it in
	 *
	 * @param string $option Option name
	 * @param mixed $value Stored value
	 */
	private function givenAutoloadedOption($option, $value) {
		delete_option($option);
		add_option($option, $value, '', true);
		$this->assertTrue($this->isAutoloaded($option), 'Test setup should have produced an autoloaded option.');
	}

	// ------------------------------------------------- saving from the plugin

	public function testSavingASchemaDoesNotAutoloadIt() {
		update_option('yaml_cf_schemas', ['single.php' => "fields:\n  - name: a\n    type: string\n"], false);

		$this->assertFalse($this->isAutoloaded('yaml_cf_schemas'));
	}

	public function testSavingSiteDetailsDoesNotAutoloadThem() {
		update_option('yaml_cf_global_data', ['phone' => '555-0100'], false);

		$this->assertFalse($this->isAutoloaded('yaml_cf_global_data'));
	}

	public function testImportingSettingsDoesNotAutoloadThem() {
		$importer = \YamlCF\Core\Plugin::getInstance()->get('settings_importer');

		$importer->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => [
				'schemas' => ['page.php' => "fields:\n  - name: a\n    type: string\n"],
				'global_data' => ['phone' => '555-0100'],
				'template_settings' => ['page.php' => true],
			],
		]);

		foreach (['yaml_cf_schemas', 'yaml_cf_global_data', 'yaml_cf_template_settings'] as $option) {
			$this->assertFalse($this->isAutoloaded($option), $option . ' should not be autoloaded after an import.');
		}
	}

	// ------------------------------------------------------- the one-off fix

	public function testUpgradeStopsAutoloadingOptionsLeftByAnOlderVersion() {
		$this->givenAutoloadedOption('yaml_cf_schemas', ['page.php' => "fields:\n  - name: a\n    type: string\n"]);
		$this->givenAutoloadedOption('yaml_cf_global_data', ['phone' => '555-0100']);

		(new Upgrader())->maybeUpgrade();

		$this->assertFalse($this->isAutoloaded('yaml_cf_schemas'));
		$this->assertFalse($this->isAutoloaded('yaml_cf_global_data'));
	}

	/**
	 * The values have to survive the change, whichever route was taken to make it.
	 */
	public function testUpgradeKeepsTheStoredValues() {
		$schemas = ['page.php' => "fields:\n  - name: headline\n    type: string\n"];
		$this->givenAutoloadedOption('yaml_cf_schemas', $schemas);

		(new Upgrader())->maybeUpgrade();

		$this->assertSame($schemas, get_option('yaml_cf_schemas'));
	}

	/**
	 * A collection's items move out of the single option they used to share, and
	 * neither the items nor the index they are listed in may be autoloaded.
	 */
	public function testUpgradeAlsoCoversCollectionEntries() {
		update_option('yaml_cf_data_object_types', ['people' => ['name' => 'People', 'schema' => '']], false);
		$this->givenAutoloadedOption('yaml_cf_data_object_entries_people', ['1' => ['name' => 'Ada']]);

		(new Upgrader())->maybeUpgrade();

		// The old option is gone, its content carried across
		$this->assertFalse(get_option('yaml_cf_data_object_entries_people', false));

		$repository = new \YamlCF\Data\Repositories\DataObjectRepository();
		$this->assertSame(['name' => 'Ada'], $repository->getEntry('people', '1'));

		$this->assertFalse($this->isAutoloaded(\YamlCF\Data\EntryStore::INDEX_PREFIX . 'people'));
	}

	// ------------------------------------- site parts stranded by a full path

	/**
	 * Before 2.0.0 a child theme's own site parts were keyed by their whole
	 * absolute path, because the scan measured them against the parent theme's
	 * directory. The scan is right now, so those entries have to be moved to the
	 * key it produces or the schema and its content simply vanish.
	 */
	public function testUpgradeMovesChildThemeSitePartsToTheirPlainName() {
		$absolute = get_stylesheet_directory() . '/header-promo.php';

		update_option('yaml_cf_schemas', [$absolute => "fields:\n  - name: phone\n    type: string\n"], false);
		update_option('yaml_cf_partial_data', [$absolute => ['phone' => '555-0100']], false);
		update_option('yaml_cf_template_settings', [$absolute => true], false);

		(new Upgrader())->maybeUpgrade();

		$schemas = get_option('yaml_cf_schemas');
		$this->assertArrayHasKey('header-promo.php', $schemas);
		$this->assertArrayNotHasKey($absolute, $schemas);

		$data = get_option('yaml_cf_partial_data');
		$this->assertSame(['phone' => '555-0100'], $data['header-promo.php']);

		$settings = get_option('yaml_cf_template_settings');
		$this->assertTrue($settings['header-promo.php']);
	}

	/**
	 * A path from a theme that is no longer installed still has a usable name.
	 */
	public function testUpgradeFallsBackToTheFileNameForAnUnknownTheme() {
		update_option('yaml_cf_schemas', ['/var/www/old-theme/footer-extra.php' => 'fields: []'], false);

		(new Upgrader())->maybeUpgrade();

		$this->assertArrayHasKey('footer-extra.php', get_option('yaml_cf_schemas'));
	}

	/**
	 * A site part in a sub-folder is legitimately stored as "parts/header.php",
	 * and must not be flattened.
	 */
	public function testUpgradeLeavesRelativeKeysAlone() {
		update_option('yaml_cf_schemas', [
			'page.php' => 'fields: []',
			'parts/header.php' => 'fields: []',
		], false);

		(new Upgrader())->maybeUpgrade();

		$schemas = get_option('yaml_cf_schemas');
		$this->assertArrayHasKey('page.php', $schemas);
		$this->assertArrayHasKey('parts/header.php', $schemas);
	}

	/**
	 * The template settings option keys the site details flag separately, and
	 * that suffix has to survive the move.
	 */
	public function testUpgradeKeepsTheSiteDetailsFlagWithItsTemplate() {
		$absolute = get_stylesheet_directory() . '/header-promo.php';
		update_option('yaml_cf_template_settings', [
			$absolute => true,
			$absolute . '_use_global' => true,
		], false);

		(new Upgrader())->maybeUpgrade();

		$settings = get_option('yaml_cf_template_settings');
		$this->assertTrue($settings['header-promo.php']);
		$this->assertTrue($settings['header-promo.php_use_global']);
	}

	public function testUpgradeRecordsThatItHasRun() {
		(new Upgrader())->maybeUpgrade();

		$this->assertSame(Upgrader::CURRENT_VERSION, (int) get_option('yaml_cf_db_version'));
		$this->assertFalse($this->isAutoloaded('yaml_cf_db_version'));
	}

	/**
	 * Running it again must not undo a change an administrator made on purpose.
	 */
	public function testUpgradeDoesNothingOnceItHasRun() {
		update_option('yaml_cf_db_version', Upgrader::CURRENT_VERSION, false);
		$this->givenAutoloadedOption('yaml_cf_schemas', ['page.php' => '']);

		(new Upgrader())->maybeUpgrade();

		$this->assertTrue($this->isAutoloaded('yaml_cf_schemas'), 'A finished upgrade should not touch options again.');
	}

	/**
	 * An option the site never had must not be brought into existence by the fix.
	 */
	public function testUpgradeDoesNotCreateOptionsThatWereNeverThere() {
		delete_option('yaml_cf_partial_data');

		(new Upgrader())->maybeUpgrade();

		$this->assertFalse(get_option('yaml_cf_partial_data', false));
	}
}
