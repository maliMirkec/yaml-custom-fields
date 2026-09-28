<?php
/**
 * Integration: who sets off the one-off data upgrade
 *
 * It is hooked to admin_init, which also fires for every admin-ajax.php
 * request, logged-out ones included. The upgrade moves every collection item
 * and cleans every post, so it waits for an administrator's page load.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Core\Upgrader;
use YamlCF\Tests\WPTestCase;

class UpgradeTriggerTest extends WPTestCase {
	public function setUp(): void {
		parent::setUp();
		delete_option(Upgrader::VERSION_OPTION);
		delete_transient(Upgrader::RUNNING_TRANSIENT);
	}

	public function tearDown(): void {
		remove_all_filters('wp_doing_ajax');
		parent::tearDown();
	}

	private function trigger() {
		(new \YamlCF\Core\HookManager(\YamlCF\Core\Plugin::getInstance()))->maybeUpgrade();
	}

	private function upgraded() {
		return (int) get_option(Upgrader::VERSION_OPTION, 0) === Upgrader::CURRENT_VERSION;
	}

	public function testAnAdministratorsPageLoadRunsIt() {
		$this->actingAs($this->createTestUser('administrator'));

		$this->trigger();

		$this->assertTrue($this->upgraded());
		$this->assertFalse(get_transient(Upgrader::RUNNING_TRANSIENT), 'The running marker is cleared afterwards.');
	}

	public function testAVisitorsAjaxRequestDoesNot() {
		wp_set_current_user(0);
		add_filter('wp_doing_ajax', '__return_true');

		$this->trigger();

		$this->assertFalse($this->upgraded());
	}

	public function testAnAdministratorsAjaxRequestDoesNot() {
		$this->actingAs($this->createTestUser('administrator'));
		add_filter('wp_doing_ajax', '__return_true');

		$this->trigger();

		$this->assertFalse($this->upgraded());
	}

	public function testAWriterOpeningTheAdminDoesNot() {
		$this->actingAs($this->createTestUser('author'));

		$this->trigger();

		$this->assertFalse($this->upgraded());
	}

	public function testItDoesNotStartWhileAnotherPageLoadIsRunningIt() {
		$this->actingAs($this->createTestUser('administrator'));
		set_transient(Upgrader::RUNNING_TRANSIENT, 1, 60);

		$this->trigger();

		$this->assertFalse($this->upgraded());
	}
}
