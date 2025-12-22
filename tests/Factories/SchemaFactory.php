<?php
/**
 * Schema Factory - Generates test schemas for testing
 */

namespace YamlCF\Tests\Factories;

class SchemaFactory {
	/**
	 * Create a simple schema with basic fields
	 *
	 * @return string YAML schema
	 */
	public static function createSimpleSchema() {
		return "fields:\n" .
			"  - name: title\n" .
			"    type: string\n" .
			"    label: Title\n" .
			"  - name: description\n" .
			"    type: text\n" .
			"    label: Description\n";
	}

	/**
	 * Create a schema with all field types
	 *
	 * @return string YAML schema
	 */
	public static function createCompleteSchema() {
		return "fields:\n" .
			"  - name: title\n" .
			"    type: string\n" .
			"    label: Title\n" .
			"    required: true\n" .
			"  - name: subtitle\n" .
			"    type: string\n" .
			"    label: Subtitle\n" .
			"  - name: description\n" .
			"    type: text\n" .
			"    label: Description\n" .
			"  - name: content\n" .
			"    type: rich-text\n" .
			"    label: Content\n" .
			"  - name: code\n" .
			"    type: code\n" .
			"    label: Code\n" .
			"    language: php\n" .
			"  - name: featured_image\n" .
			"    type: image\n" .
			"    label: Featured Image\n" .
			"  - name: attachment\n" .
			"    type: file\n" .
			"    label: Attachment\n" .
			"  - name: category\n" .
			"    type: taxonomy\n" .
			"    label: Category\n" .
			"    taxonomy: category\n" .
			"  - name: related_post\n" .
			"    type: post_type\n" .
			"    label: Related Post\n" .
			"    post_type: post\n";
	}

	/**
	 * Create a schema with block fields
	 *
	 * @return string YAML schema
	 */
	public static function createBlockSchema() {
		return "fields:\n" .
			"  - name: features\n" .
			"    type: block\n" .
			"    label: Features\n" .
			"    list: true\n" .
			"    blocks:\n" .
			"      - name: feature\n" .
			"        label: Feature\n" .
			"        fields:\n" .
			"          - name: title\n" .
			"            type: string\n" .
			"            label: Feature Title\n" .
			"          - name: description\n" .
			"            type: text\n" .
			"            label: Feature Description\n" .
			"          - name: icon\n" .
			"            type: image\n" .
			"            label: Icon\n";
	}

	/**
	 * Create a schema with nested object fields
	 *
	 * @return string YAML schema
	 */
	public static function createNestedSchema() {
		return "fields:\n" .
			"  - name: settings\n" .
			"    type: object\n" .
			"    label: Settings\n" .
			"    fields:\n" .
			"      - name: enabled\n" .
			"        type: string\n" .
			"        label: Enabled\n" .
			"      - name: options\n" .
			"        type: object\n" .
			"        label: Options\n" .
			"        fields:\n" .
			"          - name: color\n" .
			"            type: string\n" .
			"            label: Color\n" .
			"          - name: size\n" .
			"            type: string\n" .
			"            label: Size\n";
	}

	/**
	 * Create an invalid schema (missing required fields)
	 *
	 * @return string Invalid YAML schema
	 */
	public static function createInvalidSchema() {
		return "fields:\n" .
			"  - type: string\n" .
			"    label: Missing Name Field\n" .
			"  - name: missing_type\n" .
			"    label: Missing Type Field\n";
	}

	/**
	 * Create a schema with data object field
	 *
	 * @return string YAML schema
	 */
	public static function createDataObjectSchema() {
		return "fields:\n" .
			"  - name: university\n" .
			"    type: data_object\n" .
			"    label: University\n" .
			"    data_object_type: universities\n";
	}

	/**
	 * Create a schema from array
	 *
	 * @param array $fields Field definitions
	 * @return string YAML schema
	 */
	public static function createFromArray($fields) {
		$yaml = "fields:\n";
		foreach ($fields as $field) {
			$yaml .= "  - name: " . $field['name'] . "\n";
			$yaml .= "    type: " . $field['type'] . "\n";
			$yaml .= "    label: " . $field['label'] . "\n";
			if (isset($field['required']) && $field['required']) {
				$yaml .= "    required: true\n";
			}
		}
		return $yaml;
	}
}
