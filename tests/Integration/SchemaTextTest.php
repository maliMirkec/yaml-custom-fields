<?php
/**
 * Integration: schema YAML survives being saved and imported
 *
 * Schemas used to be cleaned with sanitize_textarea_field(), which is meant for
 * plain text. A choice such as "<10 people" turned the rest of its line into
 * entities and broke the schema, and %20 vanished from links in info boxes.
 *
 * @package YamlCF
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Schema\SchemaParser;
use YamlCF\Tests\WPTestCase;

class SchemaTextTest extends WPTestCase {
	const SCHEMA = "fields:\n"
		. "  - info: \"See [the guide](https://example.com/a%20guide).\"\n"
		. "  - name: size\n    label: Company size\n    type: select\n    options: [\"<10 people\", \"10-50\"]\n"
		. "  - name: intro\n    label: Intro\n    type: rich-text\n    default: \"<p>Hi</p>\"\n";

	private function service($name) {
		return \YamlCF\Core\Plugin::getInstance()->get($name);
	}

	public function testSchemaTextIsKeptAsWritten() {
		// Only the trailing newline goes, as it always has
		$this->assertSame(rtrim(self::SCHEMA), SchemaParser::sanitizeSource(self::SCHEMA));

		$parsed = (new SchemaParser())->parse(SchemaParser::sanitizeSource(self::SCHEMA));
		$this->assertSame(['<10 people', '10-50'], $parsed['fields'][1]['options']);
	}

	public function testWhatNoYamlNeedsIsTakenOut() {
		$this->assertSame("fields:\n  - name: a", SchemaParser::sanitizeSource("\n\nfields:\r\n  - name: a\x00\x07  \n\n"));
		$this->assertSame('', SchemaParser::sanitizeSource(['not', 'text']));
	}

	public function testAnImportedSchemaKeepsItsText() {
		$this->actingAs($this->createTestUser('administrator'));

		$this->service('settings_importer')->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => ['schemas' => ['page.php' => self::SCHEMA]],
		]);

		$this->assertSame(rtrim(self::SCHEMA), get_option('yaml_cf_schemas')['page.php']);
	}

	/**
	 * Replacing every schema still leaves one the file got wrong as it was
	 */
	public function testASkippedSchemaLeavesTheCurrentOneInPlace() {
		$this->actingAs($this->createTestUser('administrator'));
		update_option('yaml_cf_schemas', ['page.php' => "fields:\n  - name: title\n    type: string\n", 'old.php' => "fields: []\n"]);

		$result = $this->service('settings_importer')->import([
			'plugin' => 'yaml-custom-fields',
			'settings' => ['schemas' => [
				'page.php' => "fields:\n  - name: [unclosed\n",
				'single.php' => "fields:\n  - name: body\n    type: string\n",
			]],
		]);

		$schemas = get_option('yaml_cf_schemas');
		$this->assertNotEmpty($result['errors']);
		$this->assertSame("fields:\n  - name: title\n    type: string\n", $schemas['page.php']);
		$this->assertArrayHasKey('single.php', $schemas);
		$this->assertArrayNotHasKey('old.php', $schemas, 'Replacing still removes what the file does not mention.');
	}
}
