<?php

/**
 * Test: App\PHPWander\Printer\StandardPrinter.
 *
 * @testCase Tests\PHPWander\Printer\StandardPrinterTest
 * @author Pavel Jurásek
 * @package App\PHPWander\Printer
 */

namespace Tests\PHPWander\Printer;

use PHPCfg\Block;
use PHPCfg\Op;
use PHPCfg\Op\Expr\ArrayDimFetch;
use PHPCfg\Op\Expr\BinaryOp;
use PHPCfg\Op\Expr\ConstFetch;
use PHPCfg\Op\Expr\FuncCall;
use PHPCfg\Operand\Literal;
use PHPCfg\Operand\Temporary;
use PHPCfg\Operand\Variable;
use PHPWander\Analyser\Scope;
use PHPWander\Printer\StandardPrinter;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

/**
 * @author Pavel Jurásek
 */
class StandardPrinterTest extends Tester\TestCase
{

	/** @var StandardPrinter */
	private $printer;

	protected function setUp()
	{
		$this->printer = new StandardPrinter;
	}

	public function getOperandTestData()
	{
		$scope = \Mockery::mock(Scope::class);

		return [
			[
				new Literal('str'),
				$scope,
				'str'
			],
			[
				new Literal(false),
				$scope,
				'false'
			],
			[
				new Variable(new Literal('abc')),
				$scope,
				'$abc',
			],
			[
				new Temporary(new Variable(new Literal('var'))),
				$scope,
				'$var',
			],
		];
	}

	/**
	 * @dataProvider getOperandTestData
	 */
	public function testOperand($node, Scope $scope, string $result)
	{
		Assert::equal($result, $this->printer->printOperand($node, $scope));
	}

	public function getArrayFetchTestData()
	{
		$scope = \Mockery::mock(Scope::class);

		return [
			[
				new Variable(new Literal('array')),
				'abc',
				$scope,
				'$array[\'abc\']',
			],
			[
				new Variable(new Literal('array')),
				1,
				$scope,
				'$array[1]',
			],
			[
				new Variable(new Literal('array')),
				new Variable(new Literal('b')),
				$scope,
				'$array[$b]',
			],
			[
				new Variable(new Literal('array')),
				new ArrayDimFetch(
					new Variable(new Literal('other')),
					new Literal('key')
				),
				$scope,
				'$array[$other[\'key\']]',
			],
			[
				new Variable(new Literal('array')),
				new FuncCall(
					new Literal('f'),
					[]
				),
				$scope,
				'$array[f()]',
			],
		];
	}

	/**
	 * @dataProvider getArrayFetchTestData
	 */
	public function testArrayFetch($var, $dim, Scope $scope, string $result)
	{
		Assert::equal($result, $this->printer->printArrayFetch($var, $dim, $scope));
	}

	public function getBinaryOpTestData()
	{
		$scope = \Mockery::mock(Scope::class);

		return [
			[
				function () {
					$left = new Temporary;
					$left->ops[] = new ConstFetch(new Literal('true'));

					$right = new Temporary;
					$right->ops[] = new ConstFetch(new Literal('false'));

					return new BinaryOp\BitwiseAnd(
						$left,
						$right
					);
				},
				$scope,
				'true & false',
			],
			[
				new BinaryOp\Minus(new Literal(1), new Literal(3)),
				$scope,
				'1 - 3',
			],
			[
				new BinaryOp\Concat(new Literal(1), new Literal('abc')),
				$scope,
				'1 . \'abc\'',
			],
		];
	}

	/**
	 * @dataProvider getBinaryOpTestData
	 */
	public function testBinaryOp($op, Scope $scope, string $result)
	{
		if (is_callable($op)) {
			$op = $op();
		}

		Assert::equal($result, $this->printer->printOp($op, $scope));
	}

	public function getOpTestData()
	{
		$scope = \Mockery::mock(Scope::class);

		return [
			[
				new Op\Expr\Assign(
					new Temporary(new Variable(new Literal('var'))),
					new Literal(3)
				),
				$scope,
				'$var = 3',
			],
			[
				new Op\Expr\ArrayDimFetch(
					new Temporary(new Variable(new Literal('var'))),
					new Literal('b')
				),
				$scope,
				'$var[\'b\']',
			],
			[
				new Op\Expr\ArrayDimFetch(
					new Temporary(new Variable(new Literal('var')))
				),
				$scope,
				'$var[]',
			],
			[
				new Op\Expr\FuncCall(
					new Literal('fnc'), []
				),
				$scope,
				'fnc()',
			],
			[
				new Op\Expr\FuncCall(
					new Literal('fnc'),
					[
						new ConstFetch(new Literal('false')),
						new Literal(3),
						new Op\Expr\Assign(
							new Temporary(new Variable(new Literal('var'))),
							new Literal('str')
						),
						new Op\Expr\Assign(
							new Temporary(new Variable(new Literal('var2'))),
							new Temporary(new Variable(new Literal('arg')))
						),
						new Variable(new Literal('arg')),
					]
				),
				$scope,
				'fnc(false, 3, $var = \'str\', $var2 = $arg, $arg)',
			],
			[
				new Op\Expr\NsFuncCall(
					new Literal('fnc'),
					new Literal('ns\fnc'),
					[]
				),
				$scope,
				'ns\fnc()',
			],
			[
				new Op\Expr\PropertyFetch(
					new Variable(new Literal('object')),
					new Literal('property')
				),
				$scope,
				'$object->property',
			],
			[
				new Op\Stmt\JumpIf(
					new Literal(1),
					new Block,
					new Block
				),
				$scope,
				'if (1)',
			],
			[
				new Op\Expr\Cast\Int_(
					new Variable(new Literal('var'))
				),
				$scope,
				'(int) $var',
			],
			[
				new Op\Terminal\Return_,
				$scope,
				'return',
			],
			[
				function () {
					$temporary = new Temporary;
					$temporary->ops[] = new ConstFetch(new Literal('null'));
					return new Op\Terminal\Return_($temporary);
				},
				$scope,
				'return null',
			],
			[
				new Op\Expr\Param(
					new Literal('param'),
					null, false, false
				),
				$scope,
				'$param',
			],
			[
				new Op\Expr\ConcatList([
					new Literal('abc'),
					new Literal(123),
					new Variable(new Literal('var')),
				]),
				$scope,
				'\'abc\' . 123 . $var',
			],
			[
				new Op\Expr\MethodCall(
					new Variable(new Literal('object')),
					new Literal('method'),
					[]
				),
				$scope,
				'$object->method()',
			],
			[
				new Op\Expr\MethodCall(
					new Variable(new Literal('object')),
					new Literal('method'),
					[
						new ConstFetch(new Literal('false')),
						new Literal(3),
						new Op\Expr\Assign(
							new Temporary(new Variable(new Literal('var'))),
							new Literal('str')
						),
						new Op\Expr\Assign(
							new Temporary(new Variable(new Literal('var2'))),
							new Temporary(new Variable(new Literal('arg')))
						),
						new Variable(new Literal('arg')),
					]
				),
				$scope,
				'$object->method(false, 3, $var = \'str\', $var2 = $arg, $arg)',
			],
			[
				new ConstFetch(new Literal('true')),
				$scope,
				'true',
			],
			[
				new Op\Iterator\Valid(new Temporary),
				$scope,
				'*in iteration*',
			],
			[
				new Op\Expr\StaticCall(
					new Literal('StaticClass'),
					new Literal('staticMethod'),
					[]
				),
				$scope,
				'StaticClass::staticMethod()',
			],
			[
				new Op\Expr\Empty_(
					new Temporary(new Variable(new Literal('a')))
				),
				$scope,
				'empty($a)'
			],
			[
				function () {
					$phi = new Op\Phi(new Temporary());
					$phi->vars = [
						new ArrayDimFetch(
							new Variable(new Literal('_GET')),
							new Literal('x')
						),
						new Literal(3)
					];

					return $phi;
				},
				$scope,
				'phi($_GET[\'x\'], 3)'
			],
			[
				new Op\Expr\StaticPropertyFetch(
					new Literal('StaticClass'),
					new Literal('var')
				),
				$scope,
				'StaticClass::$var',
			],
			[
				new Op\Expr\Array_(
					[
						0 => null,
						1 => new Literal('b'),
					],
					[
						new Literal('val'),
						new ArrayDimFetch(
							new Variable(new Literal('_GET')),
							new Literal('dim')
						),
					],
					[]
				),
				$scope,
				'[\'val\', \'b\' => $_GET[\'dim\']]'
			],
			[
				new Op\Expr\BooleanNot(
					new Variable(new Literal('x'))
				),
				$scope,
				'!$x'
			],
			[
				new Op\Expr\Isset_([
					new Variable(new Literal('y')),
					new ArrayDimFetch(
						new Variable(new Literal('z')),
						new Literal('x')
					)
				]),
				$scope,
				'isset($y, $z[\'x\'])'
			],
		];
	}

	/**
	 * @dataProvider getOpTestData
	 */
	public function testOp($op, Scope $scope, string $result)
	{
		if (is_callable($op)) {
			$op = $op();
		}

		Assert::equal($result, $this->printer->printOp($op, $scope));
	}

	protected function tearDown()
	{
		parent::tearDown();

		\Mockery::close();
	}

}

(new StandardPrinterTest())->run();
