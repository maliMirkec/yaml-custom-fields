<?php
/**
 * Integration: dashboard shows clear status for every template
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class DashboardTest extends WPTestCase {
	private function render() {
		// Theme scan result, as cached by YamlCF\Template\TemplateCache
		set_transient('yaml_cf_templates_' . get_stylesheet(), [
			'templates' => [
				['file' => 'page.php', 'name' => 'Page', 'path' => ''],
				['file' => 'single.php', 'name' => 'Single', 'path' => ''],
				['file' => 'page-about.php', 'name' => 'About', 'path' => ''],
			],
			'partials' => [
				['file' => 'header.php', 'name' => 'Header', 'path' => ''],
			],
		], HOUR_IN_SECONDS);

		ob_start();
		try {
			\YamlCF\Core\Plugin::getInstance()->get('template_schema_controller')->render();
		} finally {
			$html = ob_get_clean();
		}
		return $html;
	}

	public function testFirstRunShowsGettingStartedAndNoGlobalSchema() {
		$this->actingAs($this->createTestUser('administrator'));

		$html = $this->render();

		$this->assertStringContainsString('Get started in four steps', $html);
		$this->assertStringContainsString('Not set up', $html);
		$this->assertStringContainsString('Create site details', $html);
		$this->assertStringContainsString('class="wp-header-end"', $html);
	}

	public function testTemplatesAreListedByPriorityThenAlphabetically() {
		$this->actingAs($this->createTestUser('administrator'));

		$html = $this->render();

		// Page, then Single, then the rest A–Z (About)
		$page = strpos($html, '<code class="yaml-cf-file">page.php</code>');
		$single = strpos($html, '<code class="yaml-cf-file">single.php</code>');
		$about = strpos($html, '<code class="yaml-cf-file">page-about.php</code>');
		$this->assertTrue($page < $single && $single < $about);
	}

	public function testStatusBadgesDescribeEachTemplate() {
		$this->actingAs($this->createTestUser('administrator'));
		update_option('yaml_cf_template_settings', ['page.php' => true, 'single.php' => true, 'header.php' => true]);
		update_option('yaml_cf_schemas', [
			'page.php' => "fields:\n  - name: title\n    type: string\n  - name: intro\n    type: text\n",
			'single.php' => "not a fields list",
		]);
		update_option('yaml_cf_global_schema', "fields:\n  - name: phone\n    type: string\n");

		$html = $this->render();

		$this->assertStringNotContainsString('Get started in four steps', $html);
		$this->assertStringContainsString('2 fields', $html);
		$this->assertStringContainsString('Fix schema', $html);
		$this->assertStringContainsString('No fields yet', $html);
		$this->assertStringContainsString('1 field', $html);
		$this->assertStringContainsString('Edit site details', $html);
		$this->assertSame(3, substr_count($html, 'yaml-cf-row is-enabled'));
		$this->assertSame(1, substr_count($html, 'yaml-cf-row is-disabled'));
	}
	/**
	 * An empty table has to say why it is empty. "The active theme has no page
	 * or post templates yet" told a block-theme user nothing, and was simply
	 * untrue on a child theme whose parent had plenty.
	 */
	private function renderWithNoTemplates() {
		set_transient('yaml_cf_templates_' . get_stylesheet(), [
			'templates' => [],
			'partials' => [],
		], HOUR_IN_SECONDS);

		ob_start();
		try {
			\YamlCF\Core\Plugin::getInstance()->get('template_schema_controller')->render();
		} finally {
			$html = ob_get_clean();
		}
		return $html;
	}

	public function testAnEmptyTableNamesTheThemeItLookedIn() {
		$this->actingAs($this->createTestUser('administrator'));

		$html = $this->renderWithNoTemplates();
		$theme_name = wp_get_theme()->get('Name');

		$this->assertStringContainsString(esc_html($theme_name), $html);
	}

	public function testABlockThemeIsToldWhereItsTemplatesComeFrom() {
		$this->actingAs($this->createTestUser('administrator'));

		$was = get_stylesheet();
		switch_theme('twentytwentyfour');
		if (!wp_is_block_theme()) {
			switch_theme($was);
			$this->markTestSkipped('No block theme is installed in this test environment.');
		}

		try {
			$html = $this->renderWithNoTemplates();
		} finally {
			switch_theme($was);
		}

		$this->assertStringContainsString('builds its pages out of blocks', $html);
		$this->assertStringContainsString('Site Editor', $html);
		// The @ycf marker hint is meaningless without .php files to put it in
		$this->assertStringNotContainsString('put @ycf in a comment', $html);
	}

	/**
	 * The dashboard used to say "The theme shows what was typed", which reads as
	 * though nothing else is needed. Someone would set up a schema, fill it in,
	 * look at the page and find nothing there. The readme was always honest that
	 * it takes a line in the theme; the screen people actually read was not.
	 */
	public function testOnboardingSaysTheThemeNeedsALineOfCode() {
		$this->actingAs($this->createTestUser('administrator'));

		$html = $this->render();

		$this->assertStringContainsString('Show it in the theme', $html);
		$this->assertStringContainsString('ycf_get_field', $html);
		$this->assertStringNotContainsString('The theme shows what was typed', $html);
	}

}
