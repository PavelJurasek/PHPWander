<?php

/**
 * Test: PHPWander\SinkFunctions.
 *
 * @testCase Tests\PHPWander\SinkFunctionsTest
 * @author Pavel Jurásek
 * @package PHPWander
 */

namespace Tests\PHPWander;

use PHPWander\SourceFunctions;
use PHPWander\Utils\Configuration;
use PHPWander\Utils\ConfigurationLoaders\Dummy;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Pavel Jurásek
 */
class SourceFunctionsTest extends Tester\TestCase
{

	/** @var Configuration */
	private $configuration;

	protected function setUp()
	{
		$this->configuration = new Configuration(new Dummy([
			'section1' => [
				'source1',
				'source2',
				'source3',
			],
			'section2' => [
				'source4',
				'source5',
			],
		]));
	}

	public function testBasic()
	{
		$class = new SourceFunctions($this->configuration, [
			'section1' => [
				'extra1',
			],
			'extraSection1' => [
				'extra2',
			],
		]);

		Assert::same([
			'source1',
			'source2',
			'source3',
			'source4',
			'source5',
			'extra1',
			'extra2',
		], $class->getAll());

		Assert::same([
			'source1',
			'source2',
			'source3',
			'extra1',
		], $class->getSection('section1'));

		Assert::same('section1', $class->getSourceCategory('source1'));
		Assert::same('extraSection1', $class->getSourceCategory('extra2'));
	}

	public function tearDown()
	{
		parent::tearDown();

		\Mockery::close();
	}

}

(new SourceFunctionsTest())->run();
