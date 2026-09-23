<?php
/**
 * Tests for RequestHelper
 */

namespace YamlCF\Tests\Unit\Helpers;

use YamlCF\Tests\TestCase;
use YamlCF\Helpers\RequestHelper;

class RequestHelperTest extends TestCase {
	/**
	 * Set up before each test
	 */
	protected function setUp(): void {
		parent::setUp();
		$_GET = [];
		$_POST = [];
	}

	/**
	 * Tear down after each test
	 */
	protected function tearDown(): void {
		$_GET = [];
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * Test getParam() returns sanitized GET parameter
	 */
	public function testGetParamReturnsSanitizedValue() {
		$_GET['test_key'] = 'test value';

		$result = RequestHelper::getParam('test_key', 'default');

		$this->assertSame('test value', $result);
	}

	/**
	 * Test getParam() returns default when key missing
	 */
	public function testGetParamReturnsDefaultWhenKeyMissing() {
		$result = RequestHelper::getParam('nonexistent', 'my_default');
		$this->assertEquals('my_default', $result);
	}

	/**
	 * Test getParamInt() validates integer
	 */
	public function testGetParamIntValidatesInteger() {
		$_GET['number'] = '123';

		$result = RequestHelper::getParamInt('number', 0);

		$this->assertSame(123, $result);
	}

	/**
	 * Test getParamInt() returns default for non-integer
	 */
	public function testGetParamIntReturnsDefaultForNonInteger() {
		$_GET['text'] = 'not a number';

		$result = RequestHelper::getParamInt('text', 42);
		$this->assertEquals(42, $result);
	}

	/**
	 * Test getParamInt() with negative number
	 */
	public function testGetParamIntHandlesNegativeNumber() {
		$_GET['negative'] = '-5';

		$result = RequestHelper::getParamInt('negative', 0);

		$this->assertSame(-5, $result);
	}

	/**
	 * Test getParamKey() sanitizes to valid key
	 */
	public function testGetParamKeySanitizesToValidKey() {
		$_GET['type'] = 'My-Type!';

		$this->assertSame('my-type', RequestHelper::getParamKey('type', 'default_key'));
		$this->assertEquals('default_key', RequestHelper::getParamKey('nonexistent', 'default_key'));
	}

	/**
	 * Test postRaw() returns default when key missing
	 */
	public function testPostRawReturnsDefaultWhenKeyMissing() {
		$result = RequestHelper::postRaw('nonexistent', 'my_default');
		$this->assertEquals('my_default', $result);
	}

	/**
	 * Test postSanitized() applies callback
	 */
	public function testPostSanitizedAppliesCallback() {
		$_POST['test'] = '<b>Bold</b> text';

		$this->assertEquals('Bold text', RequestHelper::postSanitized('test', 'default', 'sanitize_text_field'));
		$this->assertEquals('default', RequestHelper::postSanitized('missing', 'default', 'sanitize_text_field'));
	}

	/**
	 * Test postSanitized() with custom callback
	 */
	public function testPostSanitizedWithCustomCallback() {
		$result = RequestHelper::postSanitized('test', 'HELLO', 'strtolower');

		// Should apply the callback to default value
		$this->assertEquals('hello', $result);
	}

	/**
	 * Test default values are correct types
	 */
	public function testDefaultValuesAreCorrectTypes() {
		// Test string default
		$this->assertIsString(RequestHelper::getParam('missing'));

		// Test int default
		$this->assertIsInt(RequestHelper::getParamInt('missing'));

		// Test key default
		$this->assertIsString(RequestHelper::getParamKey('missing'));
	}

	/**
	 * Test empty string default
	 */
	public function testEmptyStringDefault() {
		$this->assertEquals('', RequestHelper::getParam('missing'));
		$this->assertEquals('', RequestHelper::getParamKey('missing'));
	}

	/**
	 * Test zero default for integer
	 */
	public function testZeroDefaultForInteger() {
		$this->assertEquals(0, RequestHelper::getParamInt('missing'));
	}

	/**
	 * Test custom defaults
	 */
	public function testCustomDefaults() {
		$this->assertEquals('custom', RequestHelper::getParam('missing', 'custom'));
		$this->assertEquals(999, RequestHelper::getParamInt('missing', 999));
		$this->assertEquals('my_key', RequestHelper::getParamKey('missing', 'my_key'));
	}

	/**
	 * Test GET values are unslashed once (WordPress slashes superglobals)
	 */
	public function testGetParamUnslashesAndDoesNotDoubleEncode() {
		$_GET['template'] = addslashes("Tom's & Jerry's.php");

		$this->assertSame("Tom's & Jerry's.php", RequestHelper::getParam('template'));
	}

	/**
	 * Test postRaw() unslashes once and keeps real backslashes and HTML
	 */
	public function testPostRawUnslashesOnce() {
		$submitted = [
			'code' => 'console.log("a\\"b"); <div>x</div>',
			'nested' => ['path' => 'C:\\temp'],
		];
		$_POST['yaml_cf'] = array_map(function ($value) {
			return is_array($value) ? array_map('addslashes', $value) : addslashes($value);
		}, $submitted);

		$this->assertSame($submitted, RequestHelper::postRaw('yaml_cf', []));
	}

	/**
	 * Test array GET values fall back to the default
	 */
	public function testGetParamIgnoresArrays() {
		$_GET['template'] = ['a', 'b'];

		$this->assertSame('default', RequestHelper::getParam('template', 'default'));
	}
}
