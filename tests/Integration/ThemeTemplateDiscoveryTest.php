<?php
/**
 * Integration: finding the templates of the theme that is switched on
 *
 * Two kinds of theme used to come up empty:
 *
 *   - A child theme. The scan asked WP_Theme::get_files() for the theme's files
 *     without asking it to include the parent's, so a child theme that overrides
 *     only style.css reported no templates at all.
 *   - A block theme. Its templates are .html files, and the scan only looked at
 *     .php, so the dashboard showed "no templates" on the default WordPress theme.
 */

namespace YamlCF\Tests\Integration;

use YamlCF\Tests\WPTestCase;

class ThemeTemplateDiscoveryTest extends WPTestCase {
	/** @var string Theme the site was on before a test switched it */
	private $original_theme;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		register_theme_directory(dirname(__DIR__) . '/themes');
		wp_clean_themes_cache();
	}

	public function setUp(): void {
		parent::setUp();
		$this->original_theme = get_stylesheet();
	}

	public function tearDown(): void {
		switch_theme($this->original_theme);
		$this->refreshThemeState();
		parent::tearDown();
	}

	/**
	 * Forget everything worked out from the theme that was on a moment ago
	 */
	private function refreshThemeState() {
		wp_clean_themes_cache();
		delete_transient('yaml_cf_templates_' . get_stylesheet());
		\YamlCF\Core\Plugin::getInstance()->get('template_resolver')->flushTemplateExistsCache();
	}

	private function useTheme($stylesheet) {
		switch_theme($stylesheet);
		$this->refreshThemeState();

		if (get_stylesheet() !== $stylesheet) {
			$this->markTestSkipped('The theme "' . $stylesheet . '" is not installed in this test environment.');
		}
	}

	private function scan() {
		return \YamlCF\Core\Plugin::getInstance()->get('template_scanner')->scan();
	}

	/**
	 * @param array $entries Result rows from the scanner
	 * @return string[] Just the file names or slugs
	 */
	private function files($entries) {
		return array_column($entries, 'file');
	}

	// ------------------------------------------------------- classic themes

	public function testAClassicThemeListsItsPostTemplates() {
		$this->useTheme('ycf-parent');

		$found = $this->files($this->scan()['templates']);

		$this->assertContains('page.php', $found);
		$this->assertContains('single.php', $found);
	}

	public function testAClassicThemeListsItsPartials() {
		$this->useTheme('ycf-parent');

		$found = $this->files($this->scan()['partials']);

		$this->assertContains('header.php', $found);
		$this->assertContains('footer.php', $found);
		$this->assertContains('archive.php', $found);
	}

	public function testACustomPageTemplateIsListed() {
		$this->useTheme('ycf-parent');

		$found = $this->files($this->scan()['templates']);

		$this->assertContains('page-about.php', $found);
	}

	// --------------------------------------------------------- child themes

	/**
	 * The bug this whole file exists for: the child theme overrides almost
	 * nothing, and the parent's templates have to be found through it.
	 */
	public function testAChildThemeFindsTheParentsTemplates() {
		$this->useTheme('ycf-child');

		$found = $this->files($this->scan()['templates']);

		$this->assertContains('single.php', $found, "The parent theme's single.php should be found through the child.");
		$this->assertContains('page-about.php', $found, "The parent theme's custom page template should be found through the child.");
	}

	public function testAChildThemeFindsTheParentsPartials() {
		$this->useTheme('ycf-child');

		$found = $this->files($this->scan()['partials']);

		$this->assertContains('header.php', $found);
		$this->assertContains('footer.php', $found);
	}

	/**
	 * A partial that exists only in the child theme is listed under its plain
	 * name. Its path used to be measured against the parent theme's directory,
	 * which left the whole absolute path as the key a schema is stored under.
	 */
	public function testAChildThemesOwnPartialIsListedByName() {
		$this->useTheme('ycf-child');

		$found = $this->files($this->scan()['partials']);

		$this->assertContains('header-promo.php', $found);
	}

	public function testAChildThemesOwnTemplateIsListed() {
		$this->useTheme('ycf-child');

		$found = $this->files($this->scan()['templates']);

		$this->assertContains('single-event.php', $found);
	}

	/**
	 * A template the child overrides is listed once, under its plain name, so a
	 * schema saved against it applies whichever theme file ends up running.
	 */
	public function testAnOverriddenTemplateIsListedOnceByName() {
		$this->useTheme('ycf-child');

		$found = $this->files($this->scan()['templates']);

		$this->assertSame(1, count(array_keys($found, 'page.php')), 'page.php exists in both themes and should be listed once.');
	}

	/**
	 * Every entry is named relative to its own theme. Measuring a child theme's
	 * file against the parent directory left an absolute path, which then became
	 * the key a schema was stored under.
	 */
	public function testNoEntryIsStoredWithAnAbsolutePath() {
		$this->useTheme('ycf-child');

		$scan = $this->scan();
		foreach (array_merge($scan['templates'], $scan['partials']) as $entry) {
			$this->assertStringNotContainsString(ABSPATH, $entry['file'], 'Template keys must be relative to the theme.');
			$this->assertStringStartsNotWith('/', $entry['file']);
		}
	}

	/**
	 * The child's own single-event.php has to be the one that resolves, not the
	 * parent's absence of it.
	 */
	public function testTheResolverSeesAChildThemesTemplate() {
		$this->useTheme('ycf-child');

		register_post_type('event', ['public' => true]);
		$post_id = $this->createTestPost(['post_type' => 'event']);

		$resolver = \YamlCF\Core\Plugin::getInstance()->get('template_resolver');
		$this->assertSame('single-event.php', $resolver->getTemplateForPost(get_post($post_id)));

		unregister_post_type('event');
	}

	// --------------------------------------------------------- block themes

	public function testABlockThemeListsItsPostTemplates() {
		$this->useTheme('twentytwentyfour');

		$found = $this->files($this->scan()['templates']);

		$this->assertContains('page', $found, 'A block theme has a "page" template.');
		$this->assertContains('single', $found, 'A block theme has a "single" template.');
	}

	public function testABlockThemeSortsListTemplatesIntoSiteParts() {
		$this->useTheme('twentytwentyfour');

		$scan = $this->scan();

		$this->assertContains('index', $this->files($scan['partials']));
		$this->assertContains('archive', $this->files($scan['partials']));
		$this->assertNotContains('index', $this->files($scan['templates']));
	}

	public function testABlockThemeTemplateHasAReadableName() {
		$this->useTheme('twentytwentyfour');

		foreach ($this->scan()['templates'] as $entry) {
			$this->assertNotSame('', $entry['name'], 'Every template needs a name to show in the table.');
		}
	}

	/**
	 * A page on a block theme resolves to a slug, not to page.php, so the schema
	 * is stored under the name the theme actually uses.
	 */
	public function testTheResolverReturnsABlockTemplateSlugForAPage() {
		$this->useTheme('twentytwentyfour');

		$post_id = $this->createTestPost(['post_type' => 'page']);

		$resolver = \YamlCF\Core\Plugin::getInstance()->get('template_resolver');
		$this->assertSame('page', $resolver->getTemplateForPost(get_post($post_id)));
	}

	public function testTheResolverReturnsSingleForAPostOnABlockTheme() {
		$this->useTheme('twentytwentyfour');

		$post_id = $this->createTestPost(['post_type' => 'post']);

		$resolver = \YamlCF\Core\Plugin::getInstance()->get('template_resolver');
		$this->assertSame('single', $resolver->getTemplateForPost(get_post($post_id)));
	}

	/**
	 * A template chosen in the editor wins on a block theme too.
	 */
	public function testAnAssignedBlockTemplateWins() {
		$this->useTheme('twentytwentyfour');

		$post_id = $this->createTestPost(['post_type' => 'page']);
		update_post_meta($post_id, '_wp_page_template', 'page-with-sidebar');

		$resolver = \YamlCF\Core\Plugin::getInstance()->get('template_resolver');
		$this->assertSame('page-with-sidebar', $resolver->getTemplateForPost(get_post($post_id)));
	}

	/**
	 * Fields have to travel end to end on a block theme: a schema saved under the
	 * slug the resolver returns must be the one the accessors read.
	 */
	public function testFieldsWorkEndToEndOnABlockTheme() {
		$this->useTheme('twentytwentyfour');

		update_option('yaml_cf_schemas', ['page' => "fields:\n  - name: headline\n    type: string\n"]);
		update_option('yaml_cf_template_settings', ['page' => true]);

		$post_id = $this->createTestPost(['post_type' => 'page'], ['headline' => 'Set on a block theme']);

		$this->assertSame('Set on a block theme', yaml_cf_get_field('headline', $post_id));
		$this->assertSame('Set on a block theme', yaml_cf_get_fields($post_id)['headline']);
	}
}
