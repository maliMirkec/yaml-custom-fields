<?php
/**
 * Tests for TemplateSorter
 */

namespace YamlCF\Tests\Unit\Template;

use YamlCF\Tests\TestCase;
use YamlCF\Template\TemplateSorter;

class TemplateSorterTest extends TestCase {
	private function files(array $items) {
		return array_column($items, 'file');
	}

	public function testTemplatesStartWithPageThenSinglePostThenAlphabetical() {
		$sorter = new TemplateSorter();

		$sorted = $sorter->sortTemplates([
			['file' => 'template-landing.php', 'name' => 'Landing'],
			['file' => 'single.php', 'name' => 'Single'],
			['file' => 'single-event.php', 'name' => 'Single Event'],
			['file' => 'page.php', 'name' => 'Page'],
			['file' => 'template-about.php', 'name' => 'about us'],
			['file' => 'page-contact.php', 'name' => 'Contact'],
		]);

		$this->assertSame(
			['page.php', 'single.php', 'template-about.php', 'page-contact.php', 'template-landing.php', 'single-event.php'],
			$this->files($sorted)
		);
	}

	public function testPartialsStartWithHeaderFooterSidebar() {
		$sorter = new TemplateSorter();

		$sorted = $sorter->sortPartials([
			['file' => 'archive.php', 'name' => 'Archive'],
			['file' => 'sidebar.php', 'name' => 'Sidebar'],
			['file' => '404.php', 'name' => '404'],
			['file' => 'footer.php', 'name' => 'Footer'],
			['file' => 'header.php', 'name' => 'Header'],
			['file' => 'header-shop.php', 'name' => 'Header Shop'],
		]);

		$this->assertSame(
			['header.php', 'footer.php', 'sidebar.php', '404.php', 'archive.php', 'header-shop.php'],
			$this->files($sorted)
		);
	}

	public function testNaturalOrderForNumbers() {
		$sorter = new TemplateSorter();

		$sorted = $sorter->sortTemplates([
			['file' => 'template-10.php', 'name' => 'Layout 10'],
			['file' => 'template-2.php', 'name' => 'Layout 2'],
		]);

		$this->assertSame(['template-2.php', 'template-10.php'], $this->files($sorted));
	}
}
