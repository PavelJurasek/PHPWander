<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\Helpers;
use PHPWander\Rules\XSS\FuncCall;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
abstract class AbstractRule
{

	protected function describeOp(Op $op): string
	/** @var BlockScopeStorage */
	private $blockScopeStorage;

	public function __construct(BlockScopeStorage $blockScopeStorage)
	{
		$this->blockScopeStorage = $blockScopeStorage;
	}

	{
		if ($op instanceof Op\Expr\Assign) {
			return sprintf('assignment on line %d in file %s', $op->getLine(), $op->getFile());
		} elseif ($op instanceof Op\Expr\ArrayDimFetch) {
			return sprintf('%s[%s]', $this->unwrapOperand($op->var), $this->unwrapOperand($op->dim));
		} elseif ($op instanceof Op\Expr\FuncCall) {
			return sprintf('function call to %s', $this->unwrapOperand($op->name));
		} elseif ($op instanceof Op\Expr\PropertyFetch) {
			return sprintf('property $%s', Helpers::unwrapOp($op));
//			return $this->describeOp($op->var->ops[0]);
		}

		if ($op instanceof Op\Expr\BinaryOp\Concat) {
			return sprintf('concat of %s and %s on line %s in file %s', $this->describeOperand($op->left), $this->describeOperand($op->right), $op->getLine(), $op->getFile());
		}

		return '?';
	}

	protected function decideOpsTaint(array $ops): int
	{
		$taint = Taint::UNKNOWN;
		/** @var Op $op */
		foreach ($ops as $op) {
			$taint = $this->leastUpperBound($taint, (int) $op->getAttribute(Taint::ATTR));
		}

		return $taint;
	}

	protected function leastUpperBound(int $taint, int $transferOp)
	{
		return max($taint, $transferOp);
	}

	private function describeOperand(Operand $operand)
	{
		if ($operand instanceof Operand\Literal) {
			return sprintf('literal %s', $operand->value);
		} elseif ($operand instanceof Operand\Temporary) {
			if ($operand->original instanceof Operand\Variable) {
				return sprintf('variable $%s', $operand->original->name->value);
			}

			return $this->describeOp($operand->ops[0]);
		}

		return '?';
	}

	protected function unwrapOperand(Operand $operand, bool $quote = true): string
	{
		if ($operand instanceof Operand\Variable) {
			return sprintf('$%s', $this->unwrapOperand($operand->name, false));
		} elseif ($operand instanceof Operand\Literal) {
			return $quote ? sprintf('\'%s\'', $operand->value) : $operand->value;
		} elseif ($operand instanceof Operand\Temporary) {
			if ($operand->original instanceof Operand\Variable) {
				return $this->unwrapOperand($operand->original);
			}
		}

		foreach ($operand->ops as $op) {
			return $this->describeOp($op);
		}

		return '?';
	}

	protected function isTainted(int $taint): bool
	{
		return $taint === Taint::TAINTED || $taint === Taint::BOTH;
	}

}
