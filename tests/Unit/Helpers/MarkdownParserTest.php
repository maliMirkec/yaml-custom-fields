<?php
/**
 * Tests for MarkdownParser
 */

namespace YamlCF\Tests\Unit\Helpers;

use YamlCF\Tests\TestCase;
use YamlCF\Helpers\MarkdownParser;

class MarkdownParserTest extends TestCase {
	/**
	 * Test parsing bold syntax
	 */
	public function testParsesBoldSyntax() {
		$input = 'This is **bold** text';
		$expected = 'This is <strong>bold</strong> text';
		$this->assertEquals($expected, MarkdownParser::parse($input));
	}

	/**
	 * Test parsing italic syntax
	 */
	public function testParsesItalicSyntax() {
		$input = 'This is _italic_ text';
		$expected = 'This is <em>italic</em> text';
		$this->assertEquals($expected, MarkdownParser::parse($input));
	}

	/**
	 * Test parsing link syntax
	 */
	public function testParsesLinkSyntax() {
		$input = 'Click [here](https://example.com) for more';
		$result = MarkdownParser::parse($input);

		$this->assertStringContains('<a href="https://example.com"', $result);
		$this->assertStringContains('>here</a>', $result);
		$this->assertStringContains('target="_blank"', $result);
		$this->assertStringContains('rel="noopener noreferrer"', $result);
	}

	/**
	 * Test parsing combined markdown
	 */
	public function testParsesCombinedMarkdown() {
		$input = 'Text with **bold** and _italic_ and [link](https://example.com)';
		$result = MarkdownParser::parse($input);

		$this->assertStringContains('<strong>bold</strong>', $result);
		$this->assertStringContains('<em>italic</em>', $result);
		$this->assertStringContains('<a href="https://example.com"', $result);
	}

	/**
	 * Test HTML escaping for XSS prevention
	 */
	public function testEscapesHtmlForXssPrevention() {
		$input = 'Text with <script>alert("XSS")</script> tags';
		$result = MarkdownParser::parse($input);

		$this->assertStringNotContains('<script>', $result);
		$this->assertStringContains('&lt;script&gt;', $result);
	}

	/**
	 * Test dangerous URL protocols are blocked
	 */
	public function testBlocksDangerousUrlProtocols() {
		$input = 'Click [here](javascript:alert("XSS"))';
		$result = MarkdownParser::parse($input);

		// Should not contain the javascript: link
		$this->assertStringNotContains('javascript:', $result);
		// Should still contain the text
		$this->assertStringContains('here', $result);
	}

	/**
	 * Test empty input
	 */
	public function testHandlesEmptyInput() {
		$this->assertEquals('', MarkdownParser::parse(''));
		$this->assertEquals('', MarkdownParser::parse(null));
	}

	/**
	 * Test plain text without markdown
	 */
	public function testHandlesPlainText() {
		$input = 'Just plain text';
		$this->assertEquals($input, MarkdownParser::parse($input));
	}

	/**
	 * Test nested markdown (parsed correctly)
	 */
	public function testParsesNestedMarkdown() {
		$input = '**bold with _italic_ inside**';
		$result = MarkdownParser::parse($input);

		// Should parse both bold and italic
		$this->assertStringContains('<strong>', $result);
		$this->assertStringContains('<em>', $result);
		$this->assertStringContains('italic', $result);
	}

	/**
	 * Test mailto links
	 */
	public function testParsesMailtoLinks() {
		$input = 'Email [us](mailto:test@example.com)';
		$result = MarkdownParser::parse($input);

		$this->assertStringContains('mailto:test@example.com', $result);
		$this->assertStringContains('>us</a>', $result);
	}

	/**
	 * Test multiple bold instances
	 */
	public function testParsesMultipleBoldInstances() {
		$input = '**First** bold and **second** bold';
		$result = MarkdownParser::parse($input);

		// Count occurrences of <strong>
		$count = substr_count($result, '<strong>');
		$this->assertEquals(2, $count);
	}

	/**
	 * Test multiple italic instances
	 */
	public function testParsesMultipleItalicInstances() {
		$input = '_First_ italic and _second_ italic';
		$result = MarkdownParser::parse($input);

		// Count occurrences of <em>
		$count = substr_count($result, '<em>');
		$this->assertEquals(2, $count);
	}

	/**
	 * Test special characters in text
	 */
	public function testHandlesSpecialCharacters() {
		$input = 'Text with & ampersand < less than > greater than';
		$result = MarkdownParser::parse($input);

		$this->assertStringContains('&amp;', $result);
		$this->assertStringContains('&lt;', $result);
		$this->assertStringContains('&gt;', $result);
	}
}
