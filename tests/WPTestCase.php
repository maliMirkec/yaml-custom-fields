<?php
/**
 * Base test case for integration tests with WordPress
 */

namespace YamlCF\Tests;

use WP_UnitTestCase;

class WPTestCase extends WP_UnitTestCase {
	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->cleanUpOptions();
	}

	/**
	 * Tear down after each test
	 */
	public function tearDown(): void {
		$this->cleanUpOptions();
		$this->cleanUpPosts();
		parent::tearDown();
	}

	/**
	 * Clean up plugin options
	 */
	protected function cleanUpOptions() {
		delete_option('yaml_cf_schemas');
		delete_option('yaml_cf_global_schema');
		delete_option('yaml_cf_template_schemas');
		delete_option('yaml_cf_template_global_schemas');
		delete_option('yaml_cf_partial_data');
		delete_option('yaml_cf_global_data');
		delete_option('yaml_cf_template_global_data');
		delete_option('yaml_cf_data_object_types');

		// Clean up transients
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_yaml_cf_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_yaml_cf_%'");
	}

	/**
	 * Clean up test posts
	 */
	protected function cleanUpPosts() {
		global $wpdb;
		$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type IN ('post', 'page')");
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yaml_cf_%'");
	}

	/**
	 * Create a test post with YAML data
	 *
	 * @param array $args Post arguments
	 * @param array $yaml_data YAML custom field data
	 * @return int Post ID
	 */
	protected function createTestPost($args = [], $yaml_data = []) {
		$defaults = [
			'post_title' => 'Test Post',
			'post_content' => 'Test content',
			'post_status' => 'publish',
			'post_type' => 'post',
		];

		$args = wp_parse_args($args, $defaults);
		$post_id = wp_insert_post($args);

		if (!empty($yaml_data)) {
			update_post_meta($post_id, '_yaml_cf_data', $yaml_data);
		}

		return $post_id;
	}

	/**
	 * Create a test user
	 *
	 * @param string $role User role
	 * @return int User ID
	 */
	protected function createTestUser($role = 'administrator') {
		return $this->factory()->user->create(['role' => $role]);
	}

	/**
	 * Set current user
	 *
	 * @param int $user_id User ID
	 */
	protected function actingAs($user_id) {
		wp_set_current_user($user_id);
	}

	/**
	 * Simulate AJAX request
	 *
	 * @param string $action AJAX action name
	 * @param array $data POST data
	 * @param bool $expect_success Whether to expect success
	 */
	protected function ajaxRequest($action, $data = [], $expect_success = true) {
		$_POST = array_merge($_POST, $data);
		$_REQUEST = array_merge($_REQUEST, $data);

		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			// Expected for successful AJAX
		} catch (\WPAjaxDieStopException $e) {
			// Error case
			if ($expect_success) {
				$this->fail('AJAX request failed when success was expected');
			}
		}
	}

	/**
	 * Get the last AJAX response
	 *
	 * @return array|null Decoded JSON response
	 */
	protected function getAjaxResponse() {
		$output = $this->_last_response;
		if (empty($output)) {
			return null;
		}

		return json_decode($output, true);
	}

	/**
	 * Assert AJAX success
	 *
	 * @param string $message Optional message
	 */
	protected function assertAjaxSuccess($message = '') {
		$response = $this->getAjaxResponse();
		$this->assertNotNull($response, 'AJAX response is null');
		$this->assertTrue($response['success'], $message ?: 'AJAX request should succeed');
	}

	/**
	 * Assert AJAX error
	 *
	 * @param string $message Optional message
	 */
	protected function assertAjaxError($message = '') {
		$response = $this->getAjaxResponse();
		$this->assertNotNull($response, 'AJAX response is null');
		$this->assertFalse($response['success'], $message ?: 'AJAX request should fail');
	}
}
