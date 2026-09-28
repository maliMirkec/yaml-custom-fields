<?php

/**
 * Tests for the field renderer: what every field shares, whatever its type
 *
 * The inputs of the richer types (taxonomies, collections, media, blocks) need
 * WordPress and are covered by the integration tests. These pin down the parts
 * decided here: names and IDs, nesting, read-only copies, labels, defaults,
 * section wrappers, snippets and unknown types.
 */

namespace YamlCF\Tests\Unit\Rendering;

use YamlCF\Rendering\FieldRenderer;
use YamlCF\Tests\TestCase;

class FieldRendererTest extends TestCase {
  /** @var FieldRenderer */
  private $renderer;

  protected function setUp(): void {
    parent::setUp();
    $this->renderer = new FieldRenderer();
    $GLOBALS['yaml_cf_test_user_caps'] = [];
  }

  protected function tearDown(): void {
    $GLOBALS['yaml_cf_test_user_caps'] = [];
    parent::tearDown();
  }

  private function render(array $fields, array $values = [], $prefix = '', $context = null) {
    ob_start();
    $this->renderer->renderFields($fields, $values, $prefix, $context);
    return ob_get_clean();
  }

  /**
   * Every type the schema validator accepts has an input, and nothing else does
   */
  public function testEveryValidFieldTypeHasARenderer() {
    foreach (\YamlCF\Schema\SchemaValidator::FIELD_TYPES as $type) {
      $this->assertTrue($this->renderer->supports($type), $type . ' has no renderer');
    }

    $this->assertFalse($this->renderer->supports('wobble'));
    $this->assertFalse($this->renderer->supports(['string']));
    $this->assertNull($this->renderer->rendererFor('wobble'));
  }

  public function testAStringFieldPostsUnderItsName() {
    $html = $this->render([['name' => 'title', 'label' => 'Title', 'type' => 'string']], ['title' => 'Hello']);

    $this->assertStringContainsString('<label for="ycf_title">Title</label>', $html);
    $this->assertStringContainsString('name="yaml_cf[title]"', $html);
    $this->assertStringContainsString('value="Hello"', $html);
    $this->assertStringContainsString('data-type="string"', $html);
  }

  /**
   * A prefix ending in "[" nests the field: yaml_cf[hero][title]
   */
  public function testNestedFieldsPostUnderTheirParent() {
    $html = $this->render([['name' => 'title', 'label' => 'Title', 'type' => 'string']], ['title' => 'Nested'], 'hero[');

    $this->assertStringContainsString('name="yaml_cf[hero][title]"', $html);
    $this->assertStringContainsString('id="ycf_hero_title"', $html);
  }

  public function testAGroupNestsItsChildren() {
    $html = $this->render([[
      'name' => 'hero',
      'label' => 'Hero',
      'type' => 'object',
      'fields' => [['name' => 'summary', 'label' => 'Summary', 'type' => 'text']],
    ]], ['hero' => ['summary' => 'Body']]);

    $this->assertStringContainsString('<h4 class="yaml-cf-field-heading">Hero</h4>', $html);
    $this->assertStringContainsString('name="yaml_cf[hero][summary]"', $html);
    $this->assertStringContainsString('>Body</textarea>', $html);
  }

  public function testTheDefaultFillsAnEmptyField() {
    $field = [['name' => 'title', 'label' => 'Title', 'type' => 'string', 'default' => 'Preset']];

    $this->assertStringContainsString('value="Preset"', $this->render($field));
    $this->assertStringContainsString('value="Own"', $this->render($field, ['title' => 'Own']));
  }

  public function testAFieldWithoutALabelIsLabelledByItsName() {
    $this->assertStringContainsString('>Subtitle</label>', $this->render([['name' => 'subtitle', 'type' => 'string']]));
  }

  public function testHelpTextFollowsTheLabel() {
    $html = $this->render([['name' => 'title', 'label' => 'Title', 'type' => 'string', 'description' => 'Keep it <short>.']]);

    $this->assertStringContainsString('<p class="yaml-cf-field-help">Keep it &lt;short&gt;.</p>', $html);
  }

  /**
   * A read-only copy shares its yaml_cf[...] names with the editable field, so
   * it must never be submitted: a disabled fieldset sees to that.
   */
  public function testAReadOnlyCopyIsInADisabledFieldset() {
    $html = $this->render(
      [['name' => 'title', 'label' => 'Title', 'type' => 'string']],
      ['title' => 'Shared'],
      '',
      ['type' => 'template_global', 'readonly' => true, 'id_suffix' => '_global']
    );

    $this->assertStringStartsWith('<fieldset class="yaml-cf-readonly-fieldset" disabled="disabled">', $html);
    $this->assertStringContainsString('class="yaml-cf-field yaml-cf-readonly"', $html);
    $this->assertStringContainsString('id="ycf_title_global"', $html);
  }

  /**
   * An unticked box sends nothing, so the hidden empty value is what lets a
   * writer clear it. A read-only copy must not send one.
   */
  public function testATickBoxCanBeCleared() {
    $field = [['name' => 'featured', 'label' => 'Featured', 'type' => 'boolean']];

    $editable = $this->render($field, ['featured' => '1']);
    $this->assertStringContainsString('<input type="hidden" name="yaml_cf[featured]" value="" />', $editable);
    $this->assertStringContainsString("checked='checked'", $editable);

    $readonly = $this->render($field, ['featured' => '1'], '', ['type' => 'global', 'readonly' => true]);
    $this->assertStringNotContainsString('type="hidden"', $readonly);
  }

  public function testAnUnknownTypeSaysSoInsteadOfPrintingNothing() {
    $html = $this->render([['name' => 'mystery', 'label' => 'Mystery', 'type' => 'wobble']]);

    $this->assertStringContainsString('yaml-cf-unknown-type', $html);
    $this->assertStringContainsString('&quot;wobble&quot;', $html);
    $this->assertStringNotContainsString('name="yaml_cf[mystery]"', $html);
  }

  public function testSectionBreaksWrapTheFieldsAfterThem() {
    $html = $this->render([
      ['name' => 'before', 'label' => 'Before', 'type' => 'string'],
      // The long form; "- section: Details" carries only the heading
      ['name' => 'details', 'type' => 'section', 'label' => 'Details', 'description' => 'About it'],
      ['name' => 'after', 'label' => 'After', 'type' => 'string'],
    ]);

    // The field before the first section keeps the old, unwrapped markup
    $this->assertLessThan(strpos($html, '<section'), strpos($html, 'yaml_cf[before]'));
    $this->assertStringContainsString('<section class="yaml-cf-field-group"><div class="yaml-cf-field-group-intro"><h3>Details</h3><p>About it</p></div><div class="yaml-cf-field-group-fields">', $html);
    $this->assertGreaterThan(strpos($html, '<section'), strpos($html, 'yaml_cf[after]'));
    $this->assertStringEndsWith('</div></section>', $html);
  }

  public function testAnInfoBoxHasNoInput() {
    $html = $this->render([['info' => 'Read **this** first.']]);

    $this->assertStringContainsString('yaml-cf-info-box', $html);
    $this->assertStringContainsString('<strong>this</strong>', $html);
    $this->assertStringNotContainsString('<label', $html);
    $this->assertStringNotContainsString('<input', $html);
  }

  /**
   * Administrators get the PHP that reads each field; writers do not edit
   * theme files, so they get nothing.
   */
  public function testSnippetsAreForAdministratorsOnly() {
    $field = [['name' => 'title', 'label' => 'Title', 'type' => 'string']];

    $this->assertStringNotContainsString('yaml-cf-copy-snippet', $this->render($field, [], '', ['type' => 'page']));

    $GLOBALS['yaml_cf_test_user_caps'] = ['manage_options' => true];
    $html = $this->render($field, [], '', ['type' => 'page']);
    $this->assertStringContainsString('data-snippet="ycf_get_field(&#039;title&#039;, null)"', $html);
  }

  public function testSnippetsNameTheSourceOfTheValue() {
    $GLOBALS['yaml_cf_test_user_caps'] = ['manage_options' => true];
    $field = [['name' => 'logo', 'label' => 'Logo', 'type' => 'string']];

    $partial = $this->render($field, [], '', ['type' => 'partial', 'template' => 'header.php']);
    $this->assertStringContainsString("ycf_get_field(&#039;logo&#039;, &#039;partial:header.php&#039;)", $partial);

    $site = $this->render($field, [], '', ['type' => 'global']);
    $this->assertStringContainsString('ycf_get_global_field(&#039;logo&#039;)', $site);

    // A collection item is read through its collection, not field by field
    $collection = $this->render($field, [], '', ['type' => 'data_object', 'object_type' => 'people']);
    $this->assertStringNotContainsString('yaml-cf-copy-snippet', $collection);
  }

  public function testSomethingThatIsNotAListOfFieldsPrintsNothing() {
    $this->assertSame('', $this->render([]));

    ob_start();
    $this->renderer->renderFields('not fields', []);
    $this->assertSame('', ob_get_clean());
  }
}
