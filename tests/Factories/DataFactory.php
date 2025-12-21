<?php
/**
 * Data Factory - Generates test data for testing
 */

namespace YamlCF\Tests\Factories;

class DataFactory {
	/**
	 * Create simple field data
	 *
	 * @return array Field data
	 */
	public static function createSimpleData() {
		return [
			'title' => 'Test Title',
			'description' => 'Test Description',
		];
	}

	/**
	 * Create complete field data matching all field types
	 *
	 * @return array Field data
	 */
	public static function createCompleteData() {
		return [
			'title' => 'Complete Test Title',
			'subtitle' => 'Test Subtitle',
			'description' => 'This is a longer test description with multiple sentences.',
			'content' => '<p>Rich text content with <strong>HTML</strong> formatting.</p>',
			'code' => '<?php echo "Hello World"; ?>',
			'featured_image' => 123, // Mock attachment ID
			'attachment' => 456, // Mock attachment ID
			'category' => 1, // Mock term ID
			'related_post' => 789, // Mock post ID
		];
	}

	/**
	 * Create block field data
	 *
	 * @return array Block data
	 */
	public static function createBlockData() {
		return [
			'features' => [
				[
					'block_type' => 'feature',
					'title' => 'Feature 1',
					'description' => 'Description of feature 1',
					'icon' => 111,
				],
				[
					'block_type' => 'feature',
					'title' => 'Feature 2',
					'description' => 'Description of feature 2',
					'icon' => 222,
				],
			],
		];
	}

	/**
	 * Create nested object data
	 *
	 * @return array Nested data
	 */
	public static function createNestedData() {
		return [
			'settings' => [
				'enabled' => 'yes',
				'options' => [
					'color' => 'blue',
					'size' => 'large',
				],
			],
		];
	}

	/**
	 * Create data with HTML content (for sanitization testing)
	 *
	 * @return array Data with HTML
	 */
	public static function createHtmlData() {
		return [
			'content' => '<p>Safe HTML</p><script>alert("XSS")</script>',
			'description' => 'Text with <b>bold</b> and <script>evil</script> tags',
		];
	}

	/**
	 * Create data with special characters
	 *
	 * @return array Data with special chars
	 */
	public static function createSpecialCharsData() {
		return [
			'title' => 'Title with "quotes" and \'apostrophes\'',
			'description' => 'Text with & ampersand < less than > greater than',
			'code' => '<?php $var = "value"; ?>',
		];
	}

	/**
	 * Create empty data
	 *
	 * @return array Empty data
	 */
	public static function createEmptyData() {
		return [
			'title' => '',
			'description' => '',
		];
	}

	/**
	 * Create data with missing required fields
	 *
	 * @return array Incomplete data
	 */
	public static function createIncompleteData() {
		return [
			'description' => 'Only description, missing required title',
		];
	}

	/**
	 * Create random string
	 *
	 * @param int $length String length
	 * @return string Random string
	 */
	public static function randomString($length = 10) {
		return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
	}

	/**
	 * Create random email
	 *
	 * @return string Random email
	 */
	public static function randomEmail() {
		return self::randomString(10) . '@example.com';
	}

	/**
	 * Create data from array
	 *
	 * @param array $fields Field names and values
	 * @return array Field data
	 */
	public static function createFromArray($fields) {
		$data = [];
		foreach ($fields as $name => $value) {
			$data[$name] = $value;
		}
		return $data;
	}

	/**
	 * Create export data structure
	 *
	 * @param array $settings Settings data
	 * @return array Export structure
	 */
	public static function createExportData($settings = []) {
		return [
			'plugin' => 'yaml-custom-fields',
			'version' => '1.0.0',
			'exported_at' => current_time('mysql'),
			'site_url' => 'http://example.com',
			'settings' => $settings,
		];
	}

	/**
	 * Create import data structure
	 *
	 * @param array $posts Posts data
	 * @return array Import structure
	 */
	public static function createImportData($posts = []) {
		return [
			'plugin' => 'yaml-custom-fields',
			'version' => '1.0.0',
			'type' => 'page_data',
			'posts' => $posts,
		];
	}
}
