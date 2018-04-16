<?php

/**
 * Test: App\PHPWander\Describer\StandardDescriber.
 *
 * @testCase Tests\PHPWander\Describer\StandardDescriberTest
 * @author Pavel Jurásek
 * @package App\PHPWander\Describer
 */

namespace Tests\PHPWander\Describer;

use PHPWander\ScalarTaint;
use PHPWander\Taint;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Pavel Jurásek
 */
class ScalarTaintTest extends Tester\TestCase
{

	public function testLeastUpperBound(): void
	{
		$unknown = new ScalarTaint(Taint::UNKNOWN);
		$untainted = new ScalarTaint(Taint::UNTAINTED);
		$tainted = new ScalarTaint(Taint::TAINTED);
		$both = new ScalarTaint(Taint::BOTH);

		Assert::same(Taint::UNKNOWN, $unknown->leastUpperBound($unknown)->getTaint());
		Assert::same(Taint::UNTAINTED, $unknown->leastUpperBound($untainted)->getTaint());
		Assert::same(Taint::TAINTED, $unknown->leastUpperBound($tainted)->getTaint());
		Assert::same(Taint::BOTH, $unknown->leastUpperBound($both)->getTaint());

		Assert::same(Taint::UNTAINTED, $untainted->leastUpperBound($unknown)->getTaint());
		Assert::same(Taint::UNTAINTED, $untainted->leastUpperBound($untainted)->getTaint());
		Assert::same(Taint::BOTH, $untainted->leastUpperBound($tainted)->getTaint());
		Assert::same(Taint::BOTH, $untainted->leastUpperBound($both)->getTaint());

		Assert::same(Taint::TAINTED, $tainted->leastUpperBound($unknown)->getTaint());
		Assert::same(Taint::BOTH, $tainted->leastUpperBound($untainted)->getTaint());
		Assert::same(Taint::TAINTED, $tainted->leastUpperBound($tainted)->getTaint());
		Assert::same(Taint::BOTH, $tainted->leastUpperBound($both)->getTaint());

		Assert::same(Taint::BOTH, $both->leastUpperBound($unknown)->getTaint());
		Assert::same(Taint::BOTH, $both->leastUpperBound($untainted)->getTaint());
		Assert::same(Taint::BOTH, $both->leastUpperBound($tainted)->getTaint());
		Assert::same(Taint::BOTH, $both->leastUpperBound($both)->getTaint());

		Assert::false($unknown->isTainted());
		Assert::false($untainted->isTainted());
		Assert::true($tainted->isTainted());
		Assert::true($both->isTainted());
	}

	public function tearDown()
	{
		parent::tearDown();

		\Mockery::close();
	}

}

(new ScalarTaintTest())->run();
