<?php
/**
 * Integration: fields inside a block (a section of a page) that are not plain inputs
 *
 * Inside a block, a type with no input of its own used to get a plain text box.
 * For a group or for sections nested in a section, saving the page then wrote
 * the word "Array" over everything they held. A code field showed its stored,
 * base64-encoded form, and a note became an empty input.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class BlockNestedFieldsTest extends WPTestCase {
	const SCHEMA = <<<YAML
fields:
  - name: sections
    label: Sections
    type: block
    list: true
    blocks:
      - name: card
        label: Card
        fields:
          - name: title
            label: Title
            type: string
          - name: embed
            label: Embed
            type: code
          - info: "Read **this**."
          - name: cta
            label: Call to action
            type: object
            fields:
              - name: text
                label: Text
                type: string
              - name: url
                label: Link
                type: string
          - name: inner
            label: Inner sections
            type: block
            list: true
            blocks:
              - name: line
                label: Line
                fields:
                  - name: words
                    type: string
YAML;

	private function stored() {
		return [
			'sections' => [[
				'type' => 'card',
				'title' => 'First',
				'embed' => \YAML_Custom_Fields::CODE_FIELD_MARKER . base64_encode('<b>code</b>'),
				'cta' => ['text' => 'Go', 'url' => '/go'],
				'inner' => [['type' => 'line', 'words' => 'One'], ['type' => 'line', 'words' => 'Two']],
			]],
		];
	}

	private function render($context = ['type' => 'page']) {
		$schema = \YamlCF\Core\Plugin::getInstance()->get('schema_parser')->parse(self::SCHEMA);

		ob_start();
		\YAML_Custom_Fields::get_instance()->render_schema_fields($schema['fields'], $this->stored(), '', $context);
		return ob_get_clean();
	}

	/**
	 * What the browser would post: every enabled input, parsed the way PHP does
	 */
	private function posted($html) {
		$doc = new \DOMDocument();
		libxml_use_internal_errors(true);
		$doc->loadHTML('<html><body>' . $html . '</body></html>');
		libxml_clear_errors();

		$pairs = [];
		// A browser submits nothing that is disabled, or inside a disabled fieldset
		$query = '//input[@name][not(ancestor::fieldset[@disabled])] | //textarea[@name][not(ancestor::fieldset[@disabled])]';
		foreach ((new \DOMXPath($doc))->query($query) as $node) {
			if ($node->hasAttribute('disabled') || in_array($node->getAttribute('type'), ['checkbox', 'button'], true)) {
				continue;
			}
			$value = $node->nodeName === 'textarea' ? $node->textContent : $node->getAttribute('value');
			$pairs[] = rawurlencode($node->getAttribute('name')) . '=' . rawurlencode($value);
		}

		parse_str(implode('&', $pairs), $data);
		return isset($data['yaml_cf']) ? $data['yaml_cf'] : [];
	}

	public function testACodeFieldInABlockShowsTheCode() {
		$html = $this->render();

		$this->assertStringContainsString('>&lt;b&gt;code&lt;/b&gt;</textarea>', $html);
		$this->assertStringNotContainsString(\YAML_Custom_Fields::CODE_FIELD_MARKER, $html);
	}

	public function testANoteInABlockIsANoteNotAnInput() {
		$html = $this->render();

		$this->assertStringContainsString('<strong>this</strong>', $html);
		$this->assertStringNotContainsString('[info_', $html);
	}

	/**
	 * The group's own inputs, under yaml_cf[sections][0][cta][...]
	 */
	public function testAGroupInABlockHasItsOwnInputs() {
		$posted = $this->posted($this->render());

		$this->assertSame(['text' => 'Go', 'url' => '/go'], $posted['sections'][0]['cta']);
	}

	/**
	 * No editor for these yet, but saving must not lose them
	 */
	public function testSectionsInsideASectionSurviveASave() {
		$html = $this->render();
		$posted = $this->posted($html);

		$this->assertStringContainsString('cannot be edited here yet', $html);
		$this->assertSame($this->stored()['sections'][0]['inner'], $posted['sections'][0]['inner']);
	}

	/**
	 * The whole item comes back as it went in, once the code field is decoded
	 * and sanitised again, the way save_schema_data() stores it
	 */
	public function testAWholeItemRoundTripsThroughTheSanitiser() {
		wp_set_current_user($this->createTestUser('administrator'));
		$schema = \YamlCF\Core\Plugin::getInstance()->get('schema_parser')->parse(self::SCHEMA);

		$saved = \YamlCF\Core\Plugin::getInstance()->get('data_sanitizer')->sanitize($this->posted($this->render()), $schema);

		$this->assertSame($this->stored(), $saved);
	}

	/**
	 * A display-only copy (shared content shown next to a page's own) posts
	 * nothing, so it can never overwrite the page's values
	 */
	public function testADisplayOnlyCopyPostsNothing() {
		$html = $this->render(['type' => 'template_global', 'readonly' => true, 'id_suffix' => '_global']);

		$this->assertSame([], $this->posted($html));
		$this->assertStringContainsString('id="ycf_sections_0_cta_text_global"', $html);
	}
}
