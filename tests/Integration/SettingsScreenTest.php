<?php
/**
 * Integration: the Settings screen
 *
 * Page caches, reading over the REST API, and what happens to the content if
 * the plugin is deleted used to sit at the bottom of the main screen, under the
 * template tables. They have a screen of their own now.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Admin\Controllers\SettingsController;
use YamlCF\Tests\WPTestCase;

class SettingsScreenTest extends WPTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->actingAs($this->createTestUser('administrator'));
	}

	public function tearDown(): void {
		$_POST = [];
		$_GET = [];
		\YamlCF\Core\Plugin::getInstance()->get('notification_manager')->clear();
		parent::tearDown();
	}

	private function render($service) {
		ob_start();
		try {
			\YamlCF\Core\Plugin::getInstance()->get($service)->render();
		} finally {
			$html = ob_get_clean();
		}

		return $html;
	}

	public function testTheSettingsScreenHoldsTheThreeChoices() {
		$html = $this->render('settings_controller');

		$this->assertStringContainsString('yaml_cf_clear_caches_nonce', $html);
		$this->assertStringContainsString('yaml_cf_rest_access_nonce', $html);
		$this->assertStringContainsString('yaml_cf_keep_data_nonce', $html);
		$this->assertStringContainsString('dashicons-performance', $html, 'Each choice has its icon.');
	}

	public function testTheMainScreenNoLongerHoldsThem() {
		set_transient('yaml_cf_templates_' . get_stylesheet(), ['templates' => [], 'partials' => []], HOUR_IN_SECONDS);

		$html = $this->render('template_schema_controller');

		$this->assertStringNotContainsString('yaml_cf_clear_caches_nonce', $html);
		$this->assertStringNotContainsString('yaml_cf_rest_access_nonce', $html);
		$this->assertStringNotContainsString('yaml_cf_keep_data_nonce', $html);
	}

	public function testTheScreenShowsWhatIsSaved() {
		update_option(SettingsController::KEEP_DATA_OPTION, true, false);
		update_option(\YamlCF\Rest\RestApi::PUBLIC_READ_OPTION, '1', false);
		update_option(\YamlCF\Cache\CacheManager::CLEAR_ALL_POSTS_OPTION, '0', false);

		$html = $this->render('settings_controller');

		$this->assertMatchesRegularExpression('/name="yaml_cf_keep_data" value="1"\s+checked/', $html);
		$this->assertMatchesRegularExpression('/name="yaml_cf_rest_public_read" value="1"\s+checked/', $html);
		$this->assertDoesNotMatchRegularExpression('/name="yaml_cf_clear_caches" value="1"\s+checked/', $html);
	}

	/**
	 * Saving comes back to the Settings screen, not to the main screen
	 */
	public function testSavingComesBackToTheSettingsScreen() {
		$_GET = ['page' => SettingsController::PAGE];
		$_POST = wp_slash([
			'yaml_cf_keep_data_nonce' => wp_create_nonce('yaml_cf_keep_data'),
			'yaml_cf_keep_data' => '1',
		]);
		$location = null;
		$stop = function ($to) use (&$location) {
			$location = $to;
			throw new \RuntimeException('redirected');
		};
		add_filter('wp_redirect', $stop, 1);

		try {
			\YamlCF\Core\Plugin::getInstance()->get('settings_controller')->handleFormSubmissions();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirected', $e->getMessage());
		} finally {
			remove_filter('wp_redirect', $stop, 1);
		}

		$this->assertStringContainsString('page=yaml-cf-settings', (string) $location);
		$this->assertTrue((bool) get_option(SettingsController::KEEP_DATA_OPTION));
	}

	public function testAStaleFormSaysSo() {
		$_GET = ['page' => SettingsController::PAGE];
		$_POST = ['yaml_cf_rest_access_nonce' => 'expired', 'yaml_cf_rest_public_read' => '1'];

		try {
			\YamlCF\Core\Plugin::getInstance()->get('settings_controller')->handleFormSubmissions();
			$this->fail('A stale form went through.');
		} catch (\WPDieException $e) {
			$this->assertStringContainsString('Nothing was saved', $e->getMessage());
		}
	}

	public function testTheScreenIsInTheMenu() {
		global $submenu;
		$submenu = [];
		do_action('admin_menu');

		$slugs = array_column(isset($submenu['yaml-custom-fields']) ? $submenu['yaml-custom-fields'] : [], 2);
		$this->assertContains(SettingsController::PAGE, $slugs);
		$this->assertLessThan(array_search('yaml-cf-docs', $slugs, true), array_search(SettingsController::PAGE, $slugs, true), 'Settings comes before Documentation.');
	}
}
