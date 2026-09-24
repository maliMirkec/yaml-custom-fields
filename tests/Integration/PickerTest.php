<?php
/**
 * Integration: the search-backed picker a taxonomy or collection field shows
 * once it has more choices than the threshold
 */

namespace YamlCF\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

class PickerTest extends WP_Ajax_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		update_option('yaml_cf_template_settings', ['single.php' => true]);
		// A low threshold keeps these tests from having to create 50 terms
		add_filter('yaml_cf_picker_threshold', [$this, 'lowThreshold']);
	}

	public function tearDown(): void {
		remove_filter('yaml_cf_picker_threshold', [$this, 'lowThreshold']);
		$_POST = [];
		parent::tearDown();
	}

	public function lowThreshold() {
		return 2;
	}

	private function callAjax($action, array $params) {
		$_POST = wp_slash(array_merge(['nonce' => wp_create_nonce('yaml_cf_nonce')], $params));
		try {
			$this->_handleAjax($action);
		} catch (WPAjaxDieContinueException $e) {
			// Expected: wp_send_json_* ends the request
		}
		$response = json_decode($this->_last_response, true);
		$this->_last_response = '';
		return $response;
	}

	private function render($post_id) {
		ob_start();
		\YAML_Custom_Fields::get_instance()->render_schema_meta_box(get_post($post_id));
		return ob_get_clean();
	}

	private function setTaxonomySchema() {
		update_option('yaml_cf_schemas', ['single.php' => "fields:\n  - name: topics\n    label: Topics\n    type: taxonomy\n    multiple: true\n    options:\n      taxonomy: category\n"]);
	}

	/**
	 * Under the threshold nothing changes: checkboxes are friendlier than a
	 * search box for a handful of choices.
	 */
	public function testSmallTaxonomyStillRendersEveryChoice() {
		remove_filter('yaml_cf_picker_threshold', [$this, 'lowThreshold']);
		$this->_setRole('administrator');
		$this->setTaxonomySchema();
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Alpha']);

		$post_id = self::factory()->post->create(['post_type' => 'post']);
		$html = $this->render($post_id);

		$this->assertStringContainsString('yaml-cf-taxonomy-checkboxes', $html);
		$this->assertStringNotContainsString('yaml-cf-picker', $html);
		$this->assertStringContainsString('Alpha', $html);
	}

	/**
	 * Over the threshold only the chosen terms are in the markup, so the page
	 * stays the same size no matter how big the taxonomy is.
	 */
	public function testLargeTaxonomyRendersOnlyTheChosenTerms() {
		$this->_setRole('administrator');
		$this->setTaxonomySchema();

		$chosen = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Chosen topic']);
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Unchosen one']);
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Unchosen two']);
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Unchosen three']);

		$post_id = self::factory()->post->create(['post_type' => 'post']);
		update_post_meta($post_id, '_yaml_cf_data', ['topics' => [$chosen]]);

		$html = $this->render($post_id);

		$this->assertStringContainsString('yaml-cf-picker', $html);
		$this->assertStringContainsString('Chosen topic', $html);
		$this->assertStringNotContainsString('Unchosen one', $html);
		$this->assertStringNotContainsString('yaml-cf-taxonomy-checkboxes', $html);
	}

	public function testTermSearchReturnsMatchesOnly() {
		$this->_setRole('administrator');
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Architecture']);
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Biology']);

		$response = $this->callAjax('yaml_cf_search_terms', [
			'taxonomy' => 'category',
			'search' => 'Archi',
		]);

		$this->assertTrue($response['success']);
		$labels = wp_list_pluck($response['data']['results'], 'label');
		$this->assertContains('Architecture', $labels);
		$this->assertNotContains('Biology', $labels);
	}

	public function testTermSearchRejectsAnUnknownTaxonomy() {
		$this->_setRole('administrator');

		$response = $this->callAjax('yaml_cf_search_terms', ['taxonomy' => 'not_a_taxonomy']);

		$this->assertFalse($response['success']);
	}

	/**
	 * Writers use this picker in the post editor, so it must not require the
	 * manage_options capability the settings screens use.
	 */
	public function testAuthorCanSearchTerms() {
		$this->_setRole('author');
		self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Allowed']);

		$response = $this->callAjax('yaml_cf_search_terms', [
			'taxonomy' => 'category',
			'search' => 'Allowed',
		]);

		$this->assertTrue($response['success']);
	}

	public function testSubscriberCannotSearchTerms() {
		$this->_setRole('subscriber');

		$response = $this->callAjax('yaml_cf_search_terms', ['taxonomy' => 'category']);

		$this->assertFalse($response['success']);
	}

	public function testCollectionSearchMatchesOnTheFirstFieldAsLabel() {
		$this->_setRole('administrator');
		update_option('yaml_cf_data_object_types', [
			'unis' => ['name' => 'Universities', 'schema' => "fields:\n  - name: title\n    type: string\n"],
		]);
		update_option('yaml_cf_data_object_entries_unis', [
			'entry_1' => ['title' => 'Zagreb University'],
			'entry_2' => ['title' => 'Split University'],
		]);

		$response = $this->callAjax('yaml_cf_search_data_objects', [
			'object_type' => 'unis',
			'search' => 'Zagreb',
		]);

		$this->assertTrue($response['success']);
		$this->assertCount(1, $response['data']['results']);
		$this->assertSame('Zagreb University', $response['data']['results'][0]['label']);
		$this->assertSame('entry_1', $response['data']['results'][0]['id']);
	}

	public function testCollectionSearchRejectsAnUnknownCollection() {
		$this->_setRole('administrator');

		$response = $this->callAjax('yaml_cf_search_data_objects', ['object_type' => 'nope']);

		$this->assertFalse($response['success']);
	}

	/**
	 * A picked term still saves through the normal meta box path
	 */
	public function testPickedTermSaves() {
		$this->_setRole('administrator');
		$this->setTaxonomySchema();
		$term = self::factory()->term->create(['taxonomy' => 'category', 'name' => 'Saved topic']);
		$post_id = self::factory()->post->create(['post_type' => 'post']);

		$_POST = wp_slash([
			'yaml_cf_meta_box_nonce' => wp_create_nonce('yaml_cf_meta_box'),
			// The leading '' is the picker's clearing placeholder
			'yaml_cf' => ['topics' => ['', (string) $term]],
		]);
		\YAML_Custom_Fields::get_instance()->save_schema_data($post_id);

		$data = get_post_meta($post_id, '_yaml_cf_data', true);
		$this->assertSame([(string) $term], $data['topics']);
	}
}
