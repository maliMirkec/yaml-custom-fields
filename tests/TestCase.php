<?php
/**
 * Base test case for unit tests
 */

namespace YamlCF\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase {
	/**
	 * Set up before each test
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Tear down after each test
	 */
	protected function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Create a mock object with specific methods
	 *
	 * @param string $class Class name to mock
	 * @param array $methods Methods to mock
	 * @return object Mock object
	 */
	protected function createMockObject($class, $methods = []) {
		$builder = $this->getMockBuilder($class)
			->disableOriginalConstructor();

		if (!empty($methods)) {
			$builder->onlyMethods($methods);
		}

		return $builder->getMock();
	}

	/**
	 * Assert that a string contains a substring
	 *
	 * @param string $needle Substring to search for
	 * @param string $haystack String to search in
	 * @param string $message Optional failure message
	 */
	protected function assertStringContains($needle, $haystack, $message = '') {
		$this->assertStringContainsString($needle, $haystack, $message);
	}

	/**
	 * Assert that a string does NOT contain a substring
	 *
	 * @param string $needle Substring to search for
	 * @param string $haystack String to search in
	 * @param string $message Optional failure message
	 */
	protected function assertStringNotContains($needle, $haystack, $message = '') {
		$this->assertStringNotContainsString($needle, $haystack, $message);
	}

	/**
	 * Assert that an array has a specific structure
	 *
	 * @param array $expected Expected structure (keys only)
	 * @param array $actual Actual array
	 */
	protected function assertArrayStructure($expected, $actual) {
		foreach ($expected as $key) {
			$this->assertArrayHasKey($key, $actual, "Array is missing expected key: $key");
		}
	}
}
