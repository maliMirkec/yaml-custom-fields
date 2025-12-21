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
		return <<<YAML
fields:
  - name: title
    type: string
    label: Title
  - name: description
    type: text
    label: Description
YAML;
	}

	/**
	 * Create a schema with all field types
	 *
	 * @return string YAML schema
	 */
	public static function createCompleteSchema() {
		return <<<YAML
fields:
  - name: title
    type: string
    label: Title
    required: true
  - name: subtitle
    type: string
    label: Subtitle
  - name: description
    type: text
    label: Description
  - name: content
    type: rich-text
    label: Content
  - name: code
    type: code
    label: Code
    language: php
  - name: featured_image
    type: image
    label: Featured Image
  - name: attachment
    type: file
    label: Attachment
  - name: category
    type: taxonomy
    label: Category
    taxonomy: category
  - name: related_post
    type: post_type
    label: Related Post
    post_type: post
YAML;
	}

	/**
	 * Create a schema with block fields
	 *
	 * @return string YAML schema
	 */
	public static function createBlockSchema() {
		return <<<YAML
fields:
  - name: features
    type: block
    label: Features
    list: true
    blocks:
      - name: feature
        label: Feature
        fields:
          - name: title
            type: string
            label: Feature Title
          - name: description
            type: text
            label: Feature Description
          - name: icon
            type: image
            label: Icon
YAML;
	}

	/**
	 * Create a schema with nested object fields
	 *
	 * @return string YAML schema
	 */
	public static function createNestedSchema() {
		return <<<YAML
fields:
  - name: settings
    type: object
    label: Settings
    fields:
      - name: enabled
        type: string
        label: Enabled
      - name: options
        type: object
        label: Options
        fields:
          - name: color
            type: string
            label: Color
          - name: size
            type: string
            label: Size
YAML;
	}

	/**
	 * Create an invalid schema (missing required fields)
	 *
	 * @return string Invalid YAML schema
	 */
	public static function createInvalidSchema() {
		return <<<YAML
fields:
  - type: string
    label: Missing Name Field
  - name: missing_type
    label: Missing Type Field
YAML;
	}

	/**
	 * Create a schema with data object field
	 *
	 * @return string YAML schema
	 */
	public static function createDataObjectSchema() {
		return <<<YAML
fields:
  - name: university
    type: data_object
    label: University
    data_object_type: universities
YAML;
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
