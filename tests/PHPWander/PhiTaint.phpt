<?php

/**
 * Test: PHPWander\PhiTaint.
 *
 * @testCase Tests\PHPWander\PhiTaintTest
 * @author Pavel Jurásek
 * @package PHPWander
 */

namespace Tests\PHPWander;

use PHPWander\PhiTaint;
use PHPWander\ScalarTaint;
use PHPWander\Taint;
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

		Assert::true($phiTaint->isTainted());
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
