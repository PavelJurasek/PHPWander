<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\Helpers;
use PHPWander\Analyser\Scope;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
abstract class AbstractRule
{

	/** @var BlockScopeStorage */
	private $blockScopeStorage;

	public function __construct(BlockScopeStorage $blockScopeStorage)
	{
		$this->blockScopeStorage = $blockScopeStorage;
	}

	protected function describeOp(Op $op, Scope $scope): string
	{
		if ($op instanceof Op\Expr\Assign) {
			return sprintf('assignment on line %d in file %s', $op->getLine(), $op->getFile());
		} elseif ($op instanceof Op\Expr\ArrayDimFetch) {
			return sprintf('%s[%s]', $this->unwrapOperand($op->var, $scope), $this->unwrapOperand($op->dim, $scope));
		} elseif ($op instanceof Op\Expr\FuncCall) {
			return sprintf('function call to %s', $this->unwrapOperand($op->name, $scope));
		} elseif ($op instanceof Op\Expr\PropertyFetch) {
			return sprintf('property $%s', Helpers::unwrapOp($op));
//			return $this->describeOp($op->var->ops[0]);
		}

		if ($op instanceof Op\Stmt\JumpIf) {
			return sprintf('if %s', $this->describeOperand($op->cond, $scope));
		}

		if ($op instanceof Op\Expr\BinaryOp\Concat) {
			return sprintf('concat of %s and %s on line %s in file %s', $this->describeOperand($op->left, $scope), $this->describeOperand($op->right, $scope), $op->getLine(), $op->getFile());
		} elseif ($op instanceof Op\Expr\BinaryOp) {
			return sprintf('%s %s %s', $this->describeOperand($op->left, $scope), $this->resolveBinaryOp($op), $this->describeOperand($op->right, $scope));
		}

		if ($op instanceof Op\Phi) {
			$parentBlock = $scope->getParentBlock();

			if ($parentBlock === null) {
				dump(__METHOD__);
				dump('no parent block');
				die;
			}

			$stmt = $scope->getStatementForBlock($parentBlock);
//			dump($scope->getCurrentBlock());

			/** @var Operand\Variable $var */
			foreach ($op->vars as $var) {
				foreach ($parentBlock->children as $child) {
					if (in_array($child, $var->ops, true)) {
						return $stmt ? sprintf('%s (%s)', $this->describeOperand($var, $scope), $this->describeOp($stmt, $scope)) : $this->describeOperand($var, $scope);
					}
				}
			}

			$str = sprintf('one of: %s', $this->describeOperand($op->vars[0], $scope));

			for ($i = 1; $i < count($op->vars); $i++) {
				$str .= sprintf(', %s', $this->describeOperand($op->vars[$i], $scope));
			}

			return $str;
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

	private function describeOperand(Operand $operand, Scope $scope)
	{
		if ($operand instanceof Operand\Literal) {
			return sprintf('literal %s', $operand->value);
		} elseif ($operand instanceof Operand\Temporary) {
			if (!empty($operand->ops)) {
				return $this->describeOp($operand->ops[0], $scope);
			}

			if ($operand->original instanceof Operand\Variable) {
				return sprintf('variable $%s', $operand->original->name->value);
			}
		}

		return '?';
	}

	protected function unwrapOperand(Operand $operand, Scope $scope, bool $quote = true): string
	{
		if ($operand instanceof Operand\Variable) {
			return sprintf('$%s', $this->unwrapOperand($operand->name, $scope, false));
		} elseif ($operand instanceof Operand\Literal) {
			return $quote ? sprintf('\'%s\'', $operand->value) : $operand->value;
		} elseif ($operand instanceof Operand\Temporary) {
			if ($operand->original instanceof Operand\Variable) {
				return $this->unwrapOperand($operand->original, $scope);
			}
		}

		foreach ($operand->ops as $op) {
			return $this->describeOp($op, $scope);
		}

		return '?';
	}

	protected function isTainted(int $taint): bool
	{
		return $taint === Taint::TAINTED || $taint === Taint::BOTH;
	}

	private function resolveBinaryOp(Op\Expr\BinaryOp $op): string
	{
		switch (get_class($op)) {
			case Op\Expr\BinaryOp\BitwiseAnd::class:
				return '&';
			case Op\Expr\BinaryOp\BitwiseOr::class:
				return '|';
			case Op\Expr\BinaryOp\BitwiseXor::class:
				return '^';
			case Op\Expr\BinaryOp\Coalesce::class:
				return '??';
			case Op\Expr\BinaryOp\Concat::class:
				return '.';
			case Op\Expr\BinaryOp\Div::class:
				return '/';
			case Op\Expr\BinaryOp\Equal::class:
				return '==';
			case Op\Expr\BinaryOp\Greater::class:
				return '>';
			case Op\Expr\BinaryOp\GreaterOrEqual::class:
				return '>=';
			case Op\Expr\BinaryOp\Identical::class:
				return '===';
			case Op\Expr\BinaryOp\LogicalXor::class:
				return 'xor';
			case Op\Expr\BinaryOp\Minus::class:
				return '-';
			case Op\Expr\BinaryOp\Mod::class:
				return '%';
			case Op\Expr\BinaryOp\Mul::class:
				return '*';
			case Op\Expr\BinaryOp\NotEqual::class:
				return '!=';
			case Op\Expr\BinaryOp\NotIdentical::class:
				return '!==';
			case Op\Expr\BinaryOp\Plus::class:
				return '+';
			case Op\Expr\BinaryOp\Pow::class:
				return '**';
			case Op\Expr\BinaryOp\ShiftLeft::class:
				return '<<';
			case Op\Expr\BinaryOp\ShiftRight::class:
				return '>>';
			case Op\Expr\BinaryOp\Smaller::class:
				return '<';
			case Op\Expr\BinaryOp\SmallerOrEqual::class:
				return '<=';
			case Op\Expr\BinaryOp\Spaceship::class:
				return '<=>';
			default:
				return 'unknown';
		}
	}

}
