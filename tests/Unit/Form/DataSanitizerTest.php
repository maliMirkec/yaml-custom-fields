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
		$GLOBALS['yaml_cf_test_user_caps'] = [];
	}

	protected function tearDown(): void {
		$GLOBALS['yaml_cf_test_user_caps'] = [];
		parent::tearDown();
	}

	/**
	 * Build a schema node from field definitions
	 */
	private function schema(array $fields) {
		return ['fields' => $fields];
	}

	/**
	 * Encode a value the way code fields are stored
	 */
	private function encoded($code) {
		return DataSanitizer::CODE_FIELD_MARKER . base64_encode($code);
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
	 * Test sanitizes with a schema node that has no fields
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
	 * Test sanitizes with a field name missing from the schema
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

	/**
	 * Test a marker-prefixed value in a non-code field is not stored as encoded code
	 */
	public function testMarkerPrefixInStringFieldIsStripped() {
		$schema = $this->schema([['name' => 'title', 'type' => 'string']]);
		$payload = $this->encoded('<script>alert(1)</script>');

		$result = $this->sanitizer->sanitize(['title' => $payload], $schema);

		$this->assertStringNotContains(DataSanitizer::CODE_FIELD_MARKER, $result['title']);
		$this->assertStringNotContains('<script>', $result['title']);
	}

	/**
	 * Test a repeated marker prefix is fully removed from non-code fields
	 */
	public function testRepeatedMarkerPrefixIsStripped() {
		$schema = $this->schema([['name' => 'title', 'type' => 'string']]);
		$value = DataSanitizer::CODE_FIELD_MARKER . DataSanitizer::CODE_FIELD_MARKER . 'abc';

		$result = $this->sanitizer->sanitize(['title' => $value], $schema);

		$this->assertEquals('abc', $result['title']);
	}

	/**
	 * Test code fields keep raw code for users with unfiltered_html
	 */
	public function testCodeFieldKeepsScriptForUnfilteredHtmlUsers() {
		$GLOBALS['yaml_cf_test_user_caps'] = ['unfiltered_html' => true];
		$schema = $this->schema([['name' => 'embed', 'type' => 'code']]);
		$code = '<script>track();</script><div>Form</div>';

		$result = $this->sanitizer->sanitize(['embed' => $code], $schema);

		$this->assertEquals($this->encoded($code), $result['embed']);
	}

	/**
	 * Test code fields are kses-filtered for users without unfiltered_html
	 */
	public function testCodeFieldStripsScriptWithoutUnfilteredHtml() {
		$schema = $this->schema([['name' => 'embed', 'type' => 'code']]);

		$result = $this->sanitizer->sanitize(['embed' => '<script>steal();</script><div>Form</div>'], $schema);

		$this->assertEquals($this->encoded('<div>Form</div>'), $result['embed']);
	}

	/**
	 * Test already-encoded code values (e.g. from an export file) are decoded and re-sanitized
	 */
	public function testEncodedCodeValueIsResanitized() {
		$schema = $this->schema([['name' => 'embed', 'type' => 'code']]);

		$result = $this->sanitizer->sanitize(['embed' => $this->encoded('<script>x()</script><p>ok</p>')], $schema);

		$this->assertEquals($this->encoded('<p>ok</p>'), $result['embed']);
	}

	/**
	 * Test CSS code fields lose dangerous patterns for users without unfiltered_html
	 */
	public function testCssCodeFieldRemovesDangerousPatterns() {
		$schema = $this->schema([['name' => 'css', 'type' => 'code', 'options' => ['language' => 'css']]]);

		$result = $this->sanitizer->sanitize(['css' => "body { background: url(javascript:alert(1)); }\n@import url(x.css);"], $schema);
		$decoded = base64_decode(substr($result['css'], strlen(DataSanitizer::CODE_FIELD_MARKER)));

		$this->assertStringNotContains('javascript:', $decoded);
		$this->assertStringNotContains('@import', $decoded);
		$this->assertStringContains('body {', $decoded);
	}

	/**
	 * Test empty code fields are stored as empty strings
	 */
	public function testEmptyCodeFieldIsEmptyString() {
		$schema = $this->schema([['name' => 'embed', 'type' => 'code']]);

		$result = $this->sanitizer->sanitize(['embed' => ''], $schema);

		$this->assertSame('', $result['embed']);
	}

	/**
	 * Test a top-level string field is not treated as code because a nested field shares its name
	 */
	public function testNestedCodeFieldNameDoesNotAffectTopLevelField() {
		$schema = $this->schema([
			['name' => 'content', 'type' => 'string'],
			['name' => 'section', 'type' => 'object', 'fields' => [
				['name' => 'content', 'type' => 'code'],
			]],
		]);

		$result = $this->sanitizer->sanitize([
			'content' => '<b>Title</b>',
			'section' => ['content' => '<div>Code</div>'],
		], $schema);

		$this->assertEquals('Title', $result['content']);
		$this->assertEquals($this->encoded('<div>Code</div>'), $result['section']['content']);
	}

	/**
	 * Test rich-text fields keep safe HTML
	 */
	public function testRichTextKeepsSafeHtml() {
		$schema = $this->schema([['name' => 'body', 'type' => 'rich-text']]);

		$result = $this->sanitizer->sanitize(['body' => '<p><strong>Hi</strong></p><script>x()</script>'], $schema);

		$this->assertEquals('<p><strong>Hi</strong></p>', $result['body']);
	}

	/**
	 * Test block list items are sanitized with their own block schema
	 */
	public function testBlockListUsesBlockSchema() {
		$schema = $this->schema([
			['name' => 'sections', 'type' => 'block', 'list' => true, 'blocks' => [
				['name' => 'hero', 'fields' => [
					['name' => 'title', 'type' => 'string'],
					['name' => 'body', 'type' => 'rich-text'],
				]],
				['name' => 'embed', 'fields' => [
					['name' => 'html', 'type' => 'code'],
				]],
			]],
		]);

		$result = $this->sanitizer->sanitize(['sections' => [
			['type' => 'hero', 'title' => '<i>Hello</i>', 'body' => '<p>Text</p>'],
			['type' => 'embed', 'html' => '<div>Widget</div>'],
		]], $schema);

		$this->assertEquals('hero', $result['sections'][0]['type']);
		$this->assertEquals('Hello', $result['sections'][0]['title']);
		$this->assertEquals('<p>Text</p>', $result['sections'][0]['body']);
		$this->assertEquals($this->encoded('<div>Widget</div>'), $result['sections'][1]['html']);
	}

	/**
	 * Test block items with an unknown block type are sanitized as plain text
	 */
	public function testUnknownBlockTypeIsPlainText() {
		$schema = $this->schema([
			['name' => 'sections', 'type' => 'block', 'list' => true, 'blocks' => [
				['name' => 'embed', 'fields' => [['name' => 'html', 'type' => 'code']]],
			]],
		]);

		$result = $this->sanitizer->sanitize(['sections' => [
			['type' => 'other', 'html' => '<div>Widget</div>'],
		]], $schema);

		$this->assertEquals('Widget', $result['sections'][0]['html']);
	}

	/**
	 * Test empty placeholders are removed from multiple taxonomy and file list fields
	 */
	public function testMultipleFieldsDropEmptyPlaceholders() {
		$schema = $this->schema([
			['name' => 'cats', 'type' => 'taxonomy', 'multiple' => true],
			['name' => 'docs', 'type' => 'file', 'list' => true],
			['name' => 'partners', 'type' => 'data_object', 'list' => true],
		]);

		$result = $this->sanitizer->sanitize([
			'cats' => ['', '4', '7'],
			'docs' => ['', '12'],
			'partners' => ['', 'entry_1'],
		], $schema);

		$this->assertSame(['4', '7'], $result['cats']);
		$this->assertSame(['12'], $result['docs']);
		$this->assertSame(['entry_1'], $result['partners']);
	}

	/**
	 * Test list items never become code even if they carry the marker
	 */
	public function testListItemsAreNeverCode() {
		$schema = $this->schema([['name' => 'tags', 'type' => 'select', 'multiple' => true]]);

		$result = $this->sanitizer->sanitize(['tags' => [$this->encoded('<b>x</b>')]], $schema);

		$this->assertStringNotContains(DataSanitizer::CODE_FIELD_MARKER, $result['tags'][0]);
	}

	/**
	 * Test getFieldDefinition only matches direct fields
	 */
	public function testGetFieldDefinitionMatchesDirectFieldsOnly() {
		$schema = $this->schema([
			['name' => 'section', 'type' => 'object', 'fields' => [['name' => 'inner', 'type' => 'code']]],
		]);

		$this->assertNull($this->sanitizer->getFieldDefinition($schema, 'inner'));
		$this->assertEquals('object', $this->sanitizer->getFieldDefinition($schema, 'section')['type']);
		$this->assertNull($this->sanitizer->getFieldDefinition(null, 'section'));
	}
}
