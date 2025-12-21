<?php
/**
 * Tests for DataSanitizer
 */

namespace YamlCF\Tests\Unit\Form;

use YamlCF\Tests\TestCase;
use YamlCF\Form\DataSanitizer;

class DataSanitizerTest extends TestCase {
	private $sanitizer;

	protected function setUp(): void {
		parent::setUp();
		$this->sanitizer = new DataSanitizer();
	}

	/**
	 * Test sanitizes simple string
	 */
	public function testSanitizesSimpleString() {
		$result = $this->sanitizer->sanitize('Hello World');
		$this->assertEquals('Hello World', $result);
	}

	/**
	 * Test sanitizes string with HTML tags
	 */
	public function testSanitizesStringWithHtmlTags() {
		$result = $this->sanitizer->sanitize('<p>Text with <script>alert("XSS")</script> tags</p>');

		// sanitize_text_field strips all HTML tags
		$this->assertStringNotContains('<p>', $result);
		$this->assertStringNotContains('<script>', $result);
		$this->assertStringContains('Text with', $result);
	}

	/**
	 * Test sanitizes array of strings
	 */
	public function testSanitizesArrayOfStrings() {
		$data = [
			'field1' => 'Value 1',
			'field2' => 'Value 2',
			'field3' => 'Value 3'
		];

		$result = $this->sanitizer->sanitize($data);

		$this->assertIsArray($result);
		$this->assertEquals('Value 1', $result['field1']);
		$this->assertEquals('Value 2', $result['field2']);
		$this->assertEquals('Value 3', $result['field3']);
	}

	/**
	 * Test sanitizes nested arrays
	 */
	public function testSanitizesNestedArrays() {
		$data = [
			'group' => [
				'nested' => [
					'deep' => 'Deep Value'
				]
			]
		];

		$result = $this->sanitizer->sanitize($data);

		$this->assertIsArray($result);
		$this->assertEquals('Deep Value', $result['group']['nested']['deep']);
	}

	/**
	 * Test sanitizes array with HTML in values
	 */
	public function testSanitizesArrayWithHtml() {
		$data = [
			'title' => '<h1>Title</h1>',
			'content' => '<p>Paragraph with <b>bold</b></p>'
		];

		$result = $this->sanitizer->sanitize($data);

		$this->assertIsArray($result);
		$this->assertStringNotContains('<h1>', $result['title']);
		$this->assertStringNotContains('<p>', $result['content']);
		$this->assertStringContains('Title', $result['title']);
		$this->assertStringContains('Paragraph with bold', $result['content']);
	}

	/**
	 * Test sanitizes empty string
	 */
	public function testSanitizesEmptyString() {
		$result = $this->sanitizer->sanitize('');
		$this->assertEquals('', $result);
	}

	/**
	 * Test sanitizes empty array
	 */
	public function testSanitizesEmptyArray() {
		$result = $this->sanitizer->sanitize([]);
		$this->assertIsArray($result);
		$this->assertCount(0, $result);
	}

	/**
	 * Test sanitizes string with special characters
	 */
	public function testSanitizesStringWithSpecialCharacters() {
		$result = $this->sanitizer->sanitize('Text with & ampersand < less than > greater than');

		// sanitize_text_field handles special characters
		$this->assertIsString($result);
		$this->assertStringContains('ampersand', $result);
	}

	/**
	 * Test sanitizes with schema parameter (currently ignored)
	 */
	public function testSanitizesWithSchemaParameter() {
		$schema = [
			'type' => 'string',
			'name' => 'title'
		];

		$result = $this->sanitizer->sanitize('Test Value', $schema);
		$this->assertEquals('Test Value', $result);
	}

	/**
	 * Test sanitizes with field_name parameter (currently ignored)
	 */
	public function testSanitizesWithFieldNameParameter() {
		$result = $this->sanitizer->sanitize('Test Value', null, 'my_field');
		$this->assertEquals('Test Value', $result);
	}

	/**
	 * Test sanitizeCodeField preserves code formatting
	 */
	public function testSanitizeCodeFieldPreservesCode() {
		$code = 'function hello() {
	return "world";
}';

		$result = $this->sanitizer->sanitizeCodeField($code, 'javascript');

		// Code should be preserved (wp_kses_post allows safe HTML)
		$this->assertIsString($result);
		$this->assertStringContains('function hello', $result);
	}

	/**
	 * Test sanitizeCodeField returns string result
	 */
	public function testSanitizeCodeFieldReturnsStringResult() {
		$code = '<script>alert("XSS")</script><p>Safe content</p>';

		$result = $this->sanitizer->sanitizeCodeField($code, 'html');

		// Note: In unit test environment, wp_kses_post is mocked and returns data as-is
		// In real WordPress, it would sanitize HTML
		$this->assertIsString($result);
		$this->assertStringContains('Safe content', $result);
	}

	/**
	 * Test sanitizeCodeField with different languages
	 */
	public function testSanitizeCodeFieldWithDifferentLanguages() {
		$php_code = '<?php echo "test"; ?>';
		$js_code = 'console.log("test");';
		$css_code = 'body { color: red; }';

		$php_result = $this->sanitizer->sanitizeCodeField($php_code, 'php');
		$js_result = $this->sanitizer->sanitizeCodeField($js_code, 'javascript');
		$css_result = $this->sanitizer->sanitizeCodeField($css_code, 'css');

		$this->assertIsString($php_result);
		$this->assertIsString($js_result);
		$this->assertIsString($css_result);
	}

	/**
	 * Test sanitizeCodeField with empty code
	 */
	public function testSanitizeCodeFieldWithEmptyCode() {
		$result = $this->sanitizer->sanitizeCodeField('', 'javascript');
		$this->assertEquals('', $result);
	}

	/**
	 * Test sanitizes numeric values
	 */
	public function testSanitizesNumericValues() {
		$result = $this->sanitizer->sanitize('12345');
		$this->assertEquals('12345', $result);
	}

	/**
	 * Test sanitizes array with mixed types
	 */
	public function testSanitizesArrayWithMixedTypes() {
		$data = [
			'string' => 'text',
			'number' => '123',
			'nested' => [
				'key' => 'value'
			]
		];

		$result = $this->sanitizer->sanitize($data);

		$this->assertIsArray($result);
		$this->assertEquals('text', $result['string']);
		$this->assertEquals('123', $result['number']);
		$this->assertEquals('value', $result['nested']['key']);
	}

	/**
	 * Test sanitizes array preserves keys
	 */
	public function testSanitizesArrayPreservesKeys() {
		$data = [
			'custom_key_1' => 'Value 1',
			'custom_key_2' => 'Value 2'
		];

		$result = $this->sanitizer->sanitize($data);

		$this->assertArrayHasKey('custom_key_1', $result);
		$this->assertArrayHasKey('custom_key_2', $result);
	}

	/**
	 * Test sanitizes deeply nested structure
	 */
	public function testSanitizesDeeplyNestedStructure() {
		$data = [
			'level1' => [
				'level2' => [
					'level3' => [
						'level4' => 'Deep value'
					]
				]
			]
		];

		$result = $this->sanitizer->sanitize($data);

		$this->assertEquals('Deep value', $result['level1']['level2']['level3']['level4']);
	}

	/**
	 * Test sanitizes repeater-like structure
	 */
	public function testSanitizesRepeaterStructure() {
		$data = [
			'items' => [
				['title' => 'Item 1', 'description' => 'Desc 1'],
				['title' => 'Item 2', 'description' => 'Desc 2'],
				['title' => 'Item 3', 'description' => 'Desc 3']
			]
		];

		$result = $this->sanitizer->sanitize($data);

		$this->assertCount(3, $result['items']);
		$this->assertEquals('Item 1', $result['items'][0]['title']);
		$this->assertEquals('Desc 3', $result['items'][2]['description']);
	}
}
