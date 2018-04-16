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
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;
use PHPWander\PhiTaint;
use PHPWander\ScalarTaint;
use PHPWander\Taint;
use PHPWander\VectorTaint;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Pavel Jurásek
 */
class PhiTaintTest extends Tester\TestCase
{

	public function testLeastUpperBound(): void
	{
		$unknown = new ScalarTaint(Taint::UNKNOWN);
		$untainted = new ScalarTaint(Taint::UNTAINTED);
		$tainted = new ScalarTaint(Taint::TAINTED);
		$both = new ScalarTaint(Taint::BOTH);

		$phiTaint = new PhiTaint;
		$phiTaint->addTaint($unknown);
		$phiTaint->addTaint($tainted);

		Assert::true(Taint::TAINTED, $phiTaint->isTainted());
		Assert::same(Taint::TAINTED, $phiTaint->leastUpperBound($tainted)->getTaint());

		$secondPhiTaint = new PhiTaint;
		$secondPhiTaint->addTaint($untainted);

		Assert::false($secondPhiTaint->isTainted());

		Assert::same(Taint::BOTH, $phiTaint->leastUpperBound($secondPhiTaint)->getTaint());
	}

	public function tearDown()
	{
		parent::tearDown();

		\Mockery::close();
	}

}

(new PhiTaintTest())->run();
