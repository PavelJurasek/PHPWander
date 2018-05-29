<?php

/**
 * Test: PHPWander\SinkFunctions.
 *
 * @testCase Tests\PHPWander\SinkFunctionsTest
 * @author Pavel Jurásek
 * @package PHPWander
 */

namespace Tests\PHPWander;

use PHPWander\SanitizerFunctions;
use PHPWander\Utils\Configuration;
use PHPWander\Utils\ConfigurationLoaders\Dummy;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Pavel Jurásek
 */
class SanitizerFunctionsTest extends Tester\TestCase
{

	/** @var Configuration */
	private $configuration;

	protected function setUp()
	{
		$this->configuration = new Configuration(new Dummy([
			'section1' => [
				'sanitizer1',
				'sanitizer2',
				'sanitizer3',
			],
			'section2' => [
				'sanitizer4',
				'sanitizer5',
			],
		]));
	}

	public function testBasic()
	{
		$class = new SanitizerFunctions($this->configuration, [
			'section1' => [
				'extra1',
			],
			'extraSection1' => [
				'extra2',
			],
		]);

		Assert::same([
			'sanitizer1',
			'sanitizer2',
			'sanitizer3',
			'sanitizer4',
			'sanitizer5',
			'extra1',
			'extra2',
		], $class->getAll());

		Assert::same([
			'sanitizer1',
			'sanitizer2',
			'sanitizer3',
			'extra1',
		], $class->getSection('section1'));

		Assert::same('section1', $class->getSanitizingCategory('sanitizer1'));
		Assert::same('extraSection1', $class->getSanitizingCategory('extra2'));
	}

	public function tearDown()
	{
		parent::tearDown();

		\Mockery::close();
	}

}

(new SanitizerFunctionsTest())->run();
