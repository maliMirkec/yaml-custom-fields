<?php
/**
 * Integration: a form submitted after its nonce expired says so
 *
 * The admin forms post back to the screen they are on. They used to check
 * "isset($nonce) && wp_verify_nonce(...)", so an expired nonce looked exactly
 * like no submission: the screen drew itself again from what was stored, and
 * everything typed into it was lost without a message.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class StaleFormTest extends WPTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->actingAs($this->createTestUser('administrator'));
	}

	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Every form handled by FormHandler::handleSubmissions(), by its nonce field
	 *
	 * @return array<string, array{0: string}>
	 */
	public function staleForms() {
		return [
			'site part content' => ['yaml_cf_partial_nonce'],
			'site details' => ['yaml_cf_global_data_nonce'],
			'shared content' => ['yaml_cf_save_template_global_data_nonce'],
			'template schema' => ['yaml_cf_save_schema_nonce'],
			'site details schema' => ['yaml_cf_save_global_schema_nonce'],
			'shared schema' => ['yaml_cf_save_template_global_schema_nonce'],
			'import into a post' => ['yaml_cf_import_post_nonce'],
		];
	}

	/**
	 * @dataProvider staleForms
	 *
	 * @param string $nonce_field POST field the form's nonce is sent in
	 */
	public function testAStaleFormStopsWithAMessage($nonce_field) {
		$_POST = wp_slash([
			$nonce_field => 'expired-nonce',
			'template' => 'header.php',
			'yaml_cf' => ['headline' => 'Typed before lunch'],
		]);

		try {
			\YamlCF\Core\Plugin::getInstance()->get('form_handler')->handleSubmissions();
			$this->fail('A stale form went through as if nothing had been posted.');
		} catch (\WPDieException $e) {
			$this->assertStringContainsString('Nothing was saved', $e->getMessage());
		}
	}

	public function testAStaleFormSavesNothing() {
		update_option('yaml_cf_global_data', ['headline' => 'Stored'], false);
		$_POST = wp_slash([
			'yaml_cf_global_data_nonce' => 'expired-nonce',
			'yaml_cf' => ['headline' => 'Typed before lunch'],
		]);

		try {
			\YamlCF\Core\Plugin::getInstance()->get('form_handler')->handleSubmissions();
		} catch (\WPDieException $e) {
			// Expected: see testAStaleFormStopsWithAMessage()
			unset($e);
		}

		$this->assertSame(['headline' => 'Stored'], get_option('yaml_cf_global_data'));
	}

	public function testOtherAdminRequestsAreLeftAlone() {
		$_POST = [];

		\YamlCF\Core\Plugin::getInstance()->get('form_handler')->handleSubmissions();

		$this->addToAssertionCount(1);
	}
}
