<?php declare(strict_types=1);

namespace PHPWander\Printer;

use PHPCfg\Op;
use PHPCfg\Op\Expr\BinaryOp;
use PHPCfg\Operand;
use PHPWander\Analyser\Scope;

/**
 * @author Pavel Jurásek
 */
interface Printer
{

	public function print($node, Scope $scope): string;

	public function printOp(Op $node, Scope $scope): string;

	public function printOperand(Operand $operand, Scope $scope, bool $quote = false): string;

	public function printArrayFetch(Operand $var, $dim, Scope $scope): string;

	public function printBinaryOp(BinaryOp $op): string;

}
