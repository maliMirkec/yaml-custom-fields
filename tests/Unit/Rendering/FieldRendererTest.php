<?php

/**
 * Tests for the field renderer factory.
 *
 * The factory is registered in the service container but nothing resolves it:
 * the post editor renders fields through YAML_Custom_Fields::render_fields_flat().
 * These tests pin down that a partly finished factory refuses to render rather
 * than substituting a text input, which would submit over the stored value.
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
  }

  public function testSupportsOnlyFinishedFieldTypes() {
    foreach (['string', 'text', 'rich-text', 'code', 'info'] as $type) {
      $this->assertTrue($this->renderer->supports($type), $type . ' should be supported');
    }

    foreach (['image', 'file', 'taxonomy', 'post_type', 'data_object', 'object', 'block'] as $type) {
      $this->assertFalse($this->renderer->supports($type), $type . ' should not be supported yet');
    }
  }

  /**
   * A boolean rendered as <input type="text"> would post "on"/"" over the real
   * value, so an unimplemented type has to stop rather than fall back.
   */
  /**
   * The message is escaped before it is thrown, because an uncaught exception
   * message ends up rendered to the page, so the quotes arrive as entities.
   */
  public function testUnregisteredTypeThrowsInsteadOfFallingBackToText() {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('has no renderer for the &quot;boolean&quot; field type');

    $this->renderer->render(['name' => 'is_featured', 'type' => 'boolean'], '1', []);
  }

  public function testStubRendererThrowsRatherThanEmittingAPlaceholderComment() {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('cannot render the &quot;image&quot; field type yet');

    $this->renderer->render(['name' => 'hero_image', 'type' => 'image'], '42', []);
  }

  public function testImplementedTypeStillRenders() {
    ob_start();
    $this->renderer->render(['name' => 'title', 'label' => 'Title', 'type' => 'string'], 'Hello', []);
    $html = ob_get_clean();

    $this->assertStringContainsString('name="yaml_cf[title]"', $html);
    $this->assertStringContainsString('value="Hello"', $html);
  }

  /**
   * Nested fields inside an object carry a prefix ending in '[', matching
   * YAML_Custom_Fields::render_fields_flat(). Ignoring it made every nested
   * field post to the top-level key instead.
   */
  public function testNestedFieldUsesPrefixedInputName() {
    ob_start();
    $this->renderer->render(
      ['name' => 'title', 'label' => 'Title', 'type' => 'string'],
      'Nested',
      ['prefix' => 'hero[']
    );
    $html = ob_get_clean();

    $this->assertStringContainsString('name="yaml_cf[hero][title]"', $html);
    $this->assertStringContainsString('id="ycf_hero_title"', $html);
  }

  public function testNestedTextareaUsesPrefixedInputName() {
    ob_start();
    $this->renderer->render(
      ['name' => 'summary', 'label' => 'Summary', 'type' => 'text'],
      'Body',
      ['prefix' => 'hero[']
    );
    $html = ob_get_clean();

    $this->assertStringContainsString('name="yaml_cf[hero][summary]"', $html);
  }
}
