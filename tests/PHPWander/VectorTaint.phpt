<?php

/**
 * Test: App\PHPWander\Describer\StandardDescriber.
 *
 * @testCase Tests\PHPWander\Describer\StandardDescriberTest
 * @author Pavel Jurásek
 * @package App\PHPWander\Describer
 */

namespace Tests\PHPWander\Describer;

use PHPStan\Type\ArrayType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPWander\ScalarTaint;
use PHPWander\Taint;
use PHPWander\VectorTaint;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Pavel Jurásek
 */
class VectorTaintTest extends Tester\TestCase
{

	public function testLeastUpperBound(): void
	{
		$unknown = new ScalarTaint(Taint::UNKNOWN);
		$untainted = new ScalarTaint(Taint::UNTAINTED);
		$tainted = new ScalarTaint(Taint::TAINTED);
		$both = new ScalarTaint(Taint::BOTH);

		$vectorTaint = new VectorTaint(new ObjectType(VectorTaint::class));
		$vectorTaint->addTaint('type', $untainted);

		$innerTaint = new VectorTaint(new ArrayType(
			new StringType,
			new ObjectType(Taint::class)
		));
		$vectorTaint->addTaint('taints', $innerTaint);

		$overallTaint = $vectorTaint->getOverallTaint();

		Assert::type(ScalarTaint::class, $overallTaint);

		Assert::false($vectorTaint->isTainted());
		Assert::true($vectorTaint->leastUpperBound($tainted)->isTainted());
		Assert::equal(Taint::BOTH, $vectorTaint->leastUpperBound($tainted)->getTaint());
	}

	public function tearDown()
	{
		parent::tearDown();

		\Mockery::close();
	}

}

(new VectorTaintTest())->run();
