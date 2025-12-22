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

		// Clean up known transient patterns using WordPress API
		$transient_patterns = [
			'yaml_cf_import_error_',
			'yaml_cf_import_success_',
			'yaml_cf_export_error_',
			'yaml_cf_export_success_',
		];

		// WordPress stores transients with user ID and post ID suffixes
		// Clean up for common test user IDs (1-10) and post IDs (1-100)
		foreach ($transient_patterns as $pattern) {
			for ($user_id = 1; $user_id <= 10; $user_id++) {
				for ($post_id = 1; $post_id <= 100; $post_id++) {
					delete_transient($pattern . $user_id . '_' . $post_id);
				}
			}
		}
	}

	/**
	 * Clean up test posts
	 */
	protected function cleanUpPosts() {
		// Get all posts and pages created during tests
		$posts = get_posts([
			'post_type' => ['post', 'page'],
			'posts_per_page' => -1,
			'post_status' => 'any',
		]);

		// Delete each post using WordPress API (automatically handles post meta)
		foreach ($posts as $post) {
			wp_delete_post($post->ID, true);
		}
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
}
