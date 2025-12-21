<?php
/**
 * Tests for HtmlHelper
 */

namespace YamlCF\Tests\Unit\Helpers;

use YamlCF\Tests\TestCase;
use YamlCF\Helpers\HtmlHelper;

class HtmlHelperTest extends TestCase {
	/**
	 * Test buildAttrs() with simple attributes
	 */
	public function testBuildAttrsWithSimpleAttributes() {
		$attrs = [
			'class' => 'my-class',
			'id' => 'my-id',
		];

		$result = HtmlHelper::buildAttrs($attrs);

		$this->assertStringContains('class="my-class"', $result);
		$this->assertStringContains('id="my-id"', $result);
	}

	/**
	 * Test buildAttrs() starts with space
	 */
	public function testBuildAttrsStartsWithSpace() {
		$attrs = ['class' => 'test'];
		$result = HtmlHelper::buildAttrs($attrs);

		$this->assertStringStartsWith(' ', $result);
	}

	/**
	 * Test buildAttrs() with empty array
	 */
	public function testBuildAttrsWithEmptyArray() {
		$result = HtmlHelper::buildAttrs([]);
		$this->assertEquals('', $result);
	}

	/**
	 * Test buildAttrs() with boolean true
	 */
	public function testBuildAttrsWithBooleanTrue() {
		$attrs = ['disabled' => true];
		$result = HtmlHelper::buildAttrs($attrs);

		// Boolean true should output just the attribute name
		$this->assertStringContains('disabled', $result);
		$this->assertStringNotContains('disabled="', $result);
	}

	/**
	 * Test buildAttrs() with boolean false
	 */
	public function testBuildAttrsWithBooleanFalse() {
		$attrs = [
			'class' => 'test',
			'hidden' => false,
		];

		$result = HtmlHelper::buildAttrs($attrs);

		// Boolean false should be skipped
		$this->assertStringNotContains('hidden', $result);
		$this->assertStringContains('class="test"', $result);
	}

	/**
	 * Test buildAttrs() with null value
	 */
	public function testBuildAttrsWithNullValue() {
		$attrs = [
			'class' => 'test',
			'data-value' => null,
		];

		$result = HtmlHelper::buildAttrs($attrs);

		// Null values should be skipped
		$this->assertStringNotContains('data-value', $result);
		$this->assertStringContains('class="test"', $result);
	}

	/**
	 * Test buildAttrs() with empty string value
	 */
	public function testBuildAttrsWithEmptyStringValue() {
		$attrs = [
			'class' => 'test',
			'data-empty' => '',
		];

		$result = HtmlHelper::buildAttrs($attrs);

		// Empty strings should be skipped
		$this->assertStringNotContains('data-empty', $result);
		$this->assertStringContains('class="test"', $result);
	}

	/**
	 * Test buildAttrs() escapes special characters
	 */
	public function testBuildAttrsEscapesSpecialCharacters() {
		$attrs = [
			'data-value' => 'value with "quotes"',
		];

		$result = HtmlHelper::buildAttrs($attrs);

		// Should escape quotes
		$this->assertStringContains('&quot;', $result);
		$this->assertStringNotContains('"quotes"', $result);
	}

	/**
	 * Test buildAttrs() escapes HTML entities
	 */
	public function testBuildAttrsEscapesHtmlEntities() {
		$attrs = [
			'data-value' => '<script>alert("XSS")</script>',
		];

		$result = HtmlHelper::buildAttrs($attrs);

		// Should escape HTML
		$this->assertStringContains('&lt;', $result);
		$this->assertStringContains('&gt;', $result);
		$this->assertStringNotContains('<script>', $result);
	}

	/**
	 * Test buildAttrs() with data attributes
	 */
	public function testBuildAttrsWithDataAttributes() {
		$attrs = [
			'data-id' => '123',
			'data-name' => 'test',
		];

		$result = HtmlHelper::buildAttrs($attrs);

		$this->assertStringContains('data-id="123"', $result);
		$this->assertStringContains('data-name="test"', $result);
	}

	/**
	 * Test buildAttrs() with numeric values
	 */
	public function testBuildAttrsWithNumericValues() {
		$attrs = [
			'width' => 100,
			'height' => 200,
		];

		$result = HtmlHelper::buildAttrs($attrs);

		$this->assertStringContains('width="100"', $result);
		$this->assertStringContains('height="200"', $result);
	}

	/**
	 * Test buildAttrs() with zero value
	 */
	public function testBuildAttrsWithZeroValue() {
		$attrs = [
			'tabindex' => 0,
		];

		$result = HtmlHelper::buildAttrs($attrs);

		// Zero should be included (not skipped)
		$this->assertStringContains('tabindex="0"', $result);
	}

	/**
	 * Test buildAttrs() preserves attribute order
	 */
	public function testBuildAttrsPreservesOrder() {
		$attrs = [
			'first' => '1',
			'second' => '2',
			'third' => '3',
		];

		$result = HtmlHelper::buildAttrs($attrs);

		// Check that attributes appear in order
		$pos_first = strpos($result, 'first');
		$pos_second = strpos($result, 'second');
		$pos_third = strpos($result, 'third');

		$this->assertLessThan($pos_second, $pos_first);
		$this->assertLessThan($pos_third, $pos_second);
	}

	/**
	 * Test outputAttrs() outputs escaped content
	 */
	public function testOutputAttrsOutputsEscapedContent() {
		$attrs = ['class' => 'test'];

		ob_start();
		HtmlHelper::outputAttrs($attrs);
		$output = ob_get_clean();

		$this->assertStringContains('class="test"', $output);
	}
}
