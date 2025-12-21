<?php
/**
 * Tests for SchemaParser
 */

namespace YamlCF\Tests\Unit\Schema;

use YamlCF\Tests\TestCase;
use YamlCF\Schema\SchemaParser;

class SchemaParserTest extends TestCase {
	private $parser;

	protected function setUp(): void {
		parent::setUp();
		$this->parser = new SchemaParser();
	}

	/**
	 * Test parsing valid YAML
	 */
	public function testParsesValidYaml() {
		$yaml = <<<YAML
fields:
  - name: title
    type: string
    label: Title
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('fields', $result);
		$this->assertIsArray($result['fields']);
		$this->assertCount(1, $result['fields']);
	}

	/**
	 * Test parsing returns null for invalid YAML
	 */
	public function testReturnsNullForInvalidYaml() {
		$yaml = "invalid:\n\tyaml:\nstructure";
		$result = $this->parser->parse($yaml);

		$this->assertNull($result);
	}

	/**
	 * Test parsing empty string
	 */
	public function testParsesEmptyString() {
		$result = $this->parser->parse('');
		// Empty YAML typically returns null
		$this->assertNull($result);
	}

	/**
	 * Test parsing complex nested structure
	 */
	public function testParsesComplexStructure() {
		$yaml = <<<YAML
fields:
  - name: group
    type: group
    fields:
      - name: title
        type: string
      - name: items
        type: repeater
        fields:
          - name: text
            type: text
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('fields', $result);
		$this->assertIsArray($result['fields'][0]['fields']);
		$this->assertEquals('group', $result['fields'][0]['type']);
	}

	/**
	 * Test parsing with special characters
	 */
	public function testParsesSpecialCharacters() {
		$yaml = <<<YAML
fields:
  - name: title
    label: "Title with \"quotes\" and 'apostrophes'"
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertStringContains('quotes', $result['fields'][0]['label']);
		$this->assertStringContains('apostrophes', $result['fields'][0]['label']);
	}

	/**
	 * Test parseWithError returns success for valid YAML
	 */
	public function testParseWithErrorReturnsSuccessForValidYaml() {
		$yaml = <<<YAML
fields:
  - name: title
    type: string
YAML;

		$result = $this->parser->parseWithError($yaml);

		$this->assertIsArray($result);
		$this->assertTrue($result['success']);
		$this->assertIsArray($result['data']);
		$this->assertNull($result['error']);
	}

	/**
	 * Test parseWithError returns error for invalid YAML
	 */
	public function testParseWithErrorReturnsErrorForInvalidYaml() {
		$yaml = "invalid:\n\tyaml:\nstructure";

		$result = $this->parser->parseWithError($yaml);

		$this->assertIsArray($result);
		$this->assertFalse($result['success']);
		$this->assertNull($result['data']);
		$this->assertIsString($result['error']);
		$this->assertNotEmpty($result['error']);
	}

	/**
	 * Test parseWithError structure
	 */
	public function testParseWithErrorHasCorrectStructure() {
		$yaml = "test: value";

		$result = $this->parser->parseWithError($yaml);

		$this->assertArrayHasKey('success', $result);
		$this->assertArrayHasKey('data', $result);
		$this->assertArrayHasKey('error', $result);
	}

	/**
	 * Test parsing YAML with arrays
	 */
	public function testParsesArrayValues() {
		$yaml = <<<YAML
fields:
  - name: colors
    type: select
    options:
      - red
      - green
      - blue
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertIsArray($result['fields'][0]['options']);
		$this->assertCount(3, $result['fields'][0]['options']);
	}

	/**
	 * Test parsing YAML with key-value options
	 */
	public function testParsesKeyValuePairs() {
		$yaml = <<<YAML
fields:
  - name: size
    type: select
    options:
      small: Small Size
      medium: Medium Size
      large: Large Size
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertIsArray($result['fields'][0]['options']);
		$this->assertEquals('Small Size', $result['fields'][0]['options']['small']);
	}

	/**
	 * Test parsing multiline text
	 */
	public function testParsesMultilineText() {
		$yaml = <<<YAML
fields:
  - name: description
    help: |
      This is a multiline
      help text that spans
      multiple lines
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertStringContains('multiline', $result['fields'][0]['help']);
		$this->assertStringContains('multiple lines', $result['fields'][0]['help']);
	}

	/**
	 * Test parsing boolean values
	 */
	public function testParsesBooleanValues() {
		$yaml = <<<YAML
fields:
  - name: active
    type: checkbox
    required: true
    default: false
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertTrue($result['fields'][0]['required']);
		$this->assertFalse($result['fields'][0]['default']);
	}

	/**
	 * Test parsing numeric values
	 */
	public function testParsesNumericValues() {
		$yaml = <<<YAML
fields:
  - name: count
    min: 0
    max: 100
    step: 5
YAML;

		$result = $this->parser->parse($yaml);

		$this->assertIsArray($result);
		$this->assertEquals(0, $result['fields'][0]['min']);
		$this->assertEquals(100, $result['fields'][0]['max']);
		$this->assertEquals(5, $result['fields'][0]['step']);
	}
}
