<?php
/**
 * Integration: what a writer's browser is allowed to ask for
 *
 * Every AJAX handler already checked a nonce and a capability, which is what
 * actually kept these actions safe. These tests cover the layer under that:
 * a writer's page should not carry a token that unlocks administrator actions,
 * and the two pickers writers do use should not answer questions about things
 * that are none of their business.
 */

namespace YamlCF\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

class AjaxHardeningTest extends WP_Ajax_UnitTestCase {
	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Call an action and return the decoded response
	 */
	private function callAjax($action, array $params) {
		$_POST = wp_slash(array_merge([
			'nonce' => wp_create_nonce('yaml_cf_nonce'),
			'admin_nonce' => wp_create_nonce('yaml_cf_admin_nonce'),
		], $params));

		try {
			$this->_handleAjax($action);
		} catch (WPAjaxDieContinueException $e) {
			unset($e);
		}

		$response = json_decode($this->_last_response, true);
		$this->_last_response = '';

		return $response;
	}

	// ------------------------------------------------------- the two nonces

	/**
	 * The token a writer's post editor carries must not open an administrator
	 * action, even before the capability check has its say.
	 */
	public function testTheWritersNonceDoesNotOpenAnAdministratorAction() {
		$this->_setRole('administrator');

		$_POST = wp_slash([
			// Only the writer-level token, as a contributor's page would carry
			'nonce' => wp_create_nonce('yaml_cf_nonce'),
			'template' => 'single.php',
			'schema' => "fields:\n  - name: headline\n    type: string\n",
		]);

		$this->expectException(WPAjaxDieStopException::class);
		$this->_handleAjax('yaml_cf_save_schema');
	}

	/**
	 * The writer-level pickers keep working on the writer-level token.
	 */
	public function testAWriterCanStillUseThePickerWithTheWritersNonce() {
		$this->_setRole('contributor');

		$_POST = wp_slash([
			'nonce' => wp_create_nonce('yaml_cf_nonce'),
			'taxonomy' => 'category',
		]);

		try {
			$this->_handleAjax('yaml_cf_search_terms');
		} catch (WPAjaxDieContinueException $e) {
			unset($e);
		}

		$response = json_decode($this->_last_response, true);
		$this->_last_response = '';

		$this->assertTrue($response['success']);
	}

	// ------------------------------------------------- the taxonomy picker

	public function testTheTaxonomyPickerAnswersForCategories() {
		$this->_setRole('contributor');
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Reports']);

		$response = $this->callAjax('yaml_cf_search_terms', ['taxonomy' => 'category', 'search' => 'Rep']);

		$this->assertTrue($response['success']);
		$this->assertSame('Reports', $response['data']['results'][0]['label']);
	}

	/**
	 * Another plugin's internal taxonomy is not the plugin's to list. It used to
	 * check only that the taxonomy existed.
	 */
	public function testTheTaxonomyPickerRefusesAPrivateTaxonomy() {
		register_taxonomy('ycf_internal', 'post', [
			'public' => false,
			'show_ui' => false,
		]);
		self::factory()->term->create(['taxonomy' => 'ycf_internal', 'name' => 'Secret tier']);

		$this->_setRole('contributor');

		$response = $this->callAjax('yaml_cf_search_terms', ['taxonomy' => 'ycf_internal']);

		$this->assertFalse($response['success']);

		unregister_taxonomy('ycf_internal');
	}

	/**
	 * A taxonomy whose terms only an administrator may assign is not listed to a
	 * writer either.
	 */
	public function testTheTaxonomyPickerRespectsTheAssignCapability() {
		register_taxonomy('ycf_restricted', 'post', [
			'public' => true,
			'show_ui' => true,
			'capabilities' => [
				'manage_terms' => 'manage_options',
				'edit_terms' => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			],
		]);

		$this->_setRole('contributor');
		$response = $this->callAjax('yaml_cf_search_terms', ['taxonomy' => 'ycf_restricted']);
		$this->assertFalse($response['success']);

		$this->_setRole('administrator');
		$response = $this->callAjax('yaml_cf_search_terms', ['taxonomy' => 'ycf_restricted']);
		$this->assertTrue($response['success']);

		unregister_taxonomy('ycf_restricted');
	}

	// ------------------------------------------- rendering a blank section

	/**
	 * Adding a section while editing a post is a writer's job, and keeps working.
	 */
	public function testAWriterCanAddASectionToAPost() {
		update_option('yaml_cf_schemas', ['single.php' => $this->blockSchema()], false);
		$this->_setRole('contributor');

		$response = $this->callAjax('yaml_cf_render_block_item', [
			'source' => 'template',
			'key' => 'single.php',
			'field_name' => 'sections',
			'block_type' => 'banner',
			'index' => '0',
		]);

		$this->assertTrue($response['success']);
	}

	/**
	 * The shape of a site details or collection schema belongs to the screens an
	 * administrator opens, not to a writer's post editor.
	 */
	public function testAWriterCannotAskForAnAdministratorOnlySchema() {
		update_option('yaml_cf_global_schema', $this->blockSchema(), false);
		$this->_setRole('contributor');

		$response = $this->callAjax('yaml_cf_render_block_item', [
			'source' => 'global',
			'key' => '',
			'field_name' => 'sections',
			'block_type' => 'banner',
			'index' => '0',
		]);

		$this->assertFalse($response['success']);
	}

	public function testAnAdministratorCanStillAskForThoseSchemas() {
		update_option('yaml_cf_global_schema', $this->blockSchema(), false);
		$this->_setRole('administrator');

		$response = $this->callAjax('yaml_cf_render_block_item', [
			'source' => 'global',
			'key' => '',
			'field_name' => 'sections',
			'block_type' => 'banner',
			'index' => '0',
		]);

		$this->assertTrue($response['success']);
	}

	private function blockSchema() {
		return "fields:\n"
			. "  - name: sections\n"
			. "    type: block\n"
			. "    list: true\n"
			. "    blocks:\n"
			. "      - name: banner\n"
			. "        fields:\n"
			. "          - name: title\n"
			. "            type: string\n";
	}
}
