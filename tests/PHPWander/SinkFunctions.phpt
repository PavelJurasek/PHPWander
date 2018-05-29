<?php

/**
 * Test: PHPWander\SinkFunctions.
 *
 * @testCase Tests\PHPWander\SinkFunctionsTest
 * @author Pavel Jurásek
 * @package PHPWander
 */

namespace Tests\PHPWander;

use PHPWander\SinkFunctions;
use PHPWander\Utils\Configuration;
use PHPWander\Utils\ConfigurationLoaders\Dummy;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Pavel Jurásek
 */
class SinkFunctionsTest extends Tester\TestCase
{

	/** @var Configuration */
	private $configuration;

	protected function setUp()
	{
		$this->configuration = new Configuration(new Dummy([
			'section1' => [
				'sink1' => [[1], ['sanitizer1', 'sanitizer2']],
				'sink2' => [[1, 2], ['sanitizer2', 'sanitizer3']],
			],
			'section2' => [
				'sink3' => [[1], []],
			],
		]));
	}

	public function testBasic()
	{
		$class = new SinkFunctions($this->configuration, [
			'section1' => [
				'extra1' => [[2], []],
			],
			'extraSection1' => [
				'extra2' => [[0], ['extraSanitizer1']]
			],
		]);

		Assert::same([
			'sink1' => [[1], ['sanitizer1', 'sanitizer2']],
			'sink2' => [[1, 2], ['sanitizer2', 'sanitizer3']],
			'sink3' => [[1], []],
			'extra1' => [[2], []],
			'extra2' => [[0], ['extraSanitizer1']],
		], $class->getAll());

		Assert::same([
			'sink1' => [[1], ['sanitizer1', 'sanitizer2']],
			'sink2' => [[1, 2], ['sanitizer2', 'sanitizer3']],
			'extra1' => [[2], []],
		], $class->getSection('section1'));

		Assert::true($class->isSensitive('sink1'));
		Assert::false($class->isSensitive('unknown'));

		Assert::same('section1', $class->getSinkCategory('sink1'));
		Assert::same('extraSection1', $class->getSinkCategory('extra2'));
	}

	public function testRewriting()
	{
		$class = new SinkFunctions($this->configuration, [
			'section1' => [
				'sink2' => [[2], []],
			],
			'extraSection1' => [
				'extra2' => [[0], ['extraSanitizer1']]
			],
		]);

		Assert::same([
			'sink1' => [[1], ['sanitizer1', 'sanitizer2']],
			'sink2' => [[2], []],
		], $class->getSection('section1'));

		Assert::true($class->isSensitive('sink2'));
		Assert::false($class->isSensitive('unknown'));

		Assert::same('section1', $class->getSinkCategory('sink2'));
	}

	public function tearDown()
	{
		parent::tearDown();

		\Mockery::close();
	}

}

(new SinkFunctionsTest())->run();
