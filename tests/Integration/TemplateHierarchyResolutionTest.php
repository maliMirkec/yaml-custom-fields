<?php
/**
 * Integration: which template's schema a post gets, following WordPress's own order
 *
 * The template list offers page-about.php as something a schema can be written
 * for, but a page was only ever resolved to page.php or to the template chosen
 * in the editor, so that schema never reached a single page. A more specific
 * template now takes over, but only once it has a schema switched on, so a page
 * that has been showing page.php's fields does not lose them in an update.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class TemplateHierarchyResolutionTest extends WPTestCase {
	const SCHEMA = "fields:\n  - name: headline\n    type: string\n";

	/** @var string Theme the site was on before the test switched it */
	private $original_theme;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		register_theme_directory(dirname(__DIR__) . '/themes');
		wp_clean_themes_cache();
	}

	public function setUp(): void {
		parent::setUp();
		$this->original_theme = get_stylesheet();

		switch_theme('ycf-parent');
		$this->refreshThemeState();
		if (get_stylesheet() !== 'ycf-parent') {
			$this->markTestSkipped('The test theme "ycf-parent" is not installed in this test environment.');
		}
	}

	public function tearDown(): void {
		switch_theme($this->original_theme);
		$this->refreshThemeState();
		parent::tearDown();
	}

	private function refreshThemeState() {
		wp_clean_themes_cache();
		\YamlCF\Core\Plugin::getInstance()->get('template_resolver')->flushTemplateExistsCache();
	}

	private function withSchemas(array $templates) {
		update_option('yaml_cf_schemas', array_fill_keys($templates, self::SCHEMA), false);
		update_option('yaml_cf_template_settings', array_fill_keys($templates, true), false);
	}

	private function templateFor($post_id) {
		return \YamlCF\Core\Plugin::getInstance()->get('template_resolver')->getTemplateForPost(get_post($post_id));
	}

	public function testAPageUsesItsSlugTemplateOnceThatHasASchema() {
		$this->withSchemas(['page.php', 'page-about.php']);
		$page = $this->createTestPost(['post_type' => 'page', 'post_name' => 'about']);

		$this->assertSame('page-about.php', $this->templateFor($page));
	}

	/**
	 * Nothing changes for a page whose specific template has no schema: it
	 * keeps page.php's fields, as before.
	 */
	public function testAPageKeepsPagePhpWhileItsSlugTemplateHasNoSchema() {
		$this->withSchemas(['page.php']);
		$page = $this->createTestPost(['post_type' => 'page', 'post_name' => 'about']);

		$this->assertSame('page.php', $this->templateFor($page));
	}

	public function testATemplateChosenInTheEditorStillWinsForAPage() {
		$this->withSchemas(['page-about.php']);
		$page = $this->createTestPost(['post_type' => 'page', 'post_name' => 'about']);
		update_post_meta($page, '_wp_page_template', 'custom-landing.php');

		$this->assertSame('custom-landing.php', $this->templateFor($page));
	}

	/**
	 * A post can be given a template too. It is followed when that template has
	 * a schema, and ignored otherwise, as it always was.
	 */
	public function testAPostFollowsItsChosenTemplateOnlyWhenThatHasASchema() {
		$post = $this->createTestPost(['post_type' => 'post']);
		update_post_meta($post, '_wp_page_template', 'page-about.php');

		$this->withSchemas(['single.php']);
		$this->assertSame('single.php', $this->templateFor($post));

		$this->withSchemas(['single.php', 'page-about.php']);
		$this->assertSame('page-about.php', $this->templateFor($post));
	}
}
