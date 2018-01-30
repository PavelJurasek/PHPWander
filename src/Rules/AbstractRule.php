<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\FuncCallPath;
use PHPWander\Analyser\FuncCallStorage;
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

	/** @var FuncCallStorage */
	private $funcCallStorage;

	public function __construct(BlockScopeStorage $blockScopeStorage, FuncCallStorage $funcCallStorage)
	{
		$this->blockScopeStorage = $blockScopeStorage;
		$this->funcCallStorage = $funcCallStorage;
	}

	protected function describeOp(Op $op, Scope $scope): string
	{
		if ($op instanceof Op\Expr\Assign) {
			$str = sprintf('assignment on line %d in file %s', $op->getLine(), $op->getFile());
			$subOp = $op->expr;
		} elseif ($op instanceof Op\Expr\ArrayDimFetch) {
			$str = sprintf('%s[%s]', $this->unwrapOperand($op->var, $scope), $this->unwrapOperand($op->dim, $scope));
		} elseif ($op instanceof Op\Expr\FuncCall) {
			$str = sprintf('function call to %s', $this->unwrapOperand($op->name, $scope));

			foreach ($this->funcCallStorage->get($op)->getFuncCallResult()->getTaintingCallPaths() as $taintingCallPath) {
				$str .= ' - (' . $this->describeFuncCallPath($taintingCallPath, $scope) . ')';
			}
		} elseif ($op instanceof Op\Expr\PropertyFetch) {
			$str = sprintf('property $%s', Helpers::unwrapOp($op));
//			return $this->describeOp($op->var->ops[0]);
		} elseif ($op instanceof Op\Stmt\JumpIf) {
			$str = sprintf('if %s', $this->describeOperand($op->cond, $scope));
		} elseif ($op instanceof Op\Stmt\Jump) {
//			$str = sprintf('jump');
			$blockScope = $this->blockScopeStorage->get($op->target);

			$str = '';
			foreach ($op->target->children as $_op) {
				$str .= $this->describeOp($_op, $blockScope);
			}

		} elseif ($op instanceof Op\Expr\BinaryOp\Concat) {
			$str = sprintf('concat of %s and %s on line %s in file %s', $this->describeOperand($op->left, $scope), $this->describeOperand($op->right, $scope), $op->getLine(), $op->getFile());
		} elseif ($op instanceof Op\Expr\BinaryOp) {
			$str = sprintf('%s %s %s', $this->describeOperand($op->left, $scope), $this->resolveBinaryOp($op), $this->describeOperand($op->right, $scope));
		} elseif ($op instanceof Op\Phi) {
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
		} elseif ($op instanceof Op\Terminal\Return_) {
			$str = sprintf('return %s', $this->describeOperand($op->expr, $scope));
		} elseif ($op instanceof Op\Expr\Param) {
			return '';
		}

		if (!isset($str)) {
			dump(__METHOD__);
			return '?';
		}

		if (isset($subOp)) {
			if ($subOp instanceof Operand) {
				$str .= ' - ' . $this->describeOperand($subOp, $scope);
			} elseif (in_array('ops', $subOp->getVariableNames(), true)) {
				foreach ($subOp->ops as $_op) {
					$str .= ' - ' . $this->describeOp($_op, $scope);
				}
			}
		}

		return $str;
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
				$result = $this->describeOp($operand->ops[0], $scope);

				if ($result !== '') {
					return $result;
				}
			}

			if ($operand->original instanceof Operand\Variable) {
				return sprintf('variable $%s', $operand->original->name->value);
			}
		}

		dump(__METHOD__);
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

		dump(__METHOD__);
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

	private function describeFuncCallPath(FuncCallPath $taintingCallPath, Scope $scope): string
	{
		if ($taintingCallPath->getStatement() instanceof Op\Stmt\JumpIf) {
			$str = sprintf('%s%s', $taintingCallPath->getEvaluation() === FuncCallPath::EVAL_FALSE ? 'not ' : '', $this->describeOp($taintingCallPath->getStatement(), $scope));

			foreach ($taintingCallPath->getChildren() as $child) {
				if ($this->isTainted($child->getTaint())) {
					$str .= ' - ' . $this->describeFuncCallPath($child, $scope);
				}
			}
		} elseif ($taintingCallPath->getStatement() instanceof Op\Terminal\Return_) {
			$str = sprintf('return %s', $this->describeOperand($taintingCallPath->getStatement()->expr, $scope));
		} elseif ($taintingCallPath->getStatement() instanceof Op\Stmt\Jump) {
			$str = $this->describeOp($taintingCallPath->getStatement(), $scope);
		}

		return $str;
	}

}
