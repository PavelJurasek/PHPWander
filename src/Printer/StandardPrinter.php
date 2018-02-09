<?php declare(strict_types=1);

namespace PHPWander\Printer;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPWander\Analyser\Scope;

/**
 * @author Pavel Jurásek
 */
class StandardPrinter implements Printer
{

	public function print($node, Scope $scope): string
	{
		if ($node instanceof Operand) {
			return $this->printOperand($node, $scope);
		}

		return $this->printOp($node, $scope);
	}

	private function printOp(Op $node, Scope $scope): string
	{
		if ($node instanceof Op\Expr\Assign) {
			return sprintf('%s = %s', $this->print($node->var, $scope), $this->print($node->expr, $scope));
		} elseif ($node instanceof Op\Expr\ArrayDimFetch) {
			return sprintf('%s[%s]', $this->printOperand($node->var, $scope), $this->printOperand($node->dim, $scope, true));
		} elseif ($node instanceof Op\Expr\FuncCall) {
			return sprintf('%s(%s)', $this->printOperand($node->name, $scope), $this->printList($node->args, $scope));
		} elseif ($node instanceof Op\Expr\PropertyFetch) {
			return sprintf('%s->%s', $this->printOperand($node->var, $scope), $this->printOperand($node->name, $scope));
		} elseif ($node instanceof Op\Stmt\JumpIf) {
			return sprintf('if (%s)', $this->print($node->cond, $scope));
		} elseif ($node instanceof Op\Expr\BinaryOp\Concat) {
			return sprintf('%s . %s', $this->printOperand($node->left, $scope), $this->printOperand($node->right, $scope));
		} elseif ($node instanceof Op\Expr\BinaryOp) {
			return sprintf('%s %s %s', $this->printOperand($node->left, $scope), $this->printBinaryOp($node), $this->printOperand($node->right, $scope));
		} elseif ($node instanceof Op\Expr\Cast) {
			$class = get_class($node);
			$cast = strtolower(rtrim(substr($class, strrpos($class, '\\')), '_'));

			return sprintf('(%s)', $cast);
		} elseif ($node instanceof Op\Terminal\Return_) {
			return sprintf('return %s', $this->printOperand($node->expr, $scope));
		} elseif ($node instanceof Op\Stmt\Jump) {
//			$str = sprintf('jump');
//			$blockScope = $this->blockScopeStorage->get($node->target);
			$blockScope = $node->getAttribute('block');

			$str = $blockScope->isNegated() ? 'not ' : '';
			foreach ($node->target->children as $_op) {
				$str .= $this->print($_op, $blockScope);
			}

		} elseif ($node instanceof Op\Expr\Param) {
			return sprintf('$%s', $this->printOperand($node->name, $scope));
		} elseif ($node instanceof Op\Expr\ConcatList) {
			return $this->printList($node->list, $scope, ' . ');
		} elseif ($node instanceof Op\Expr\MethodCall) {
			return sprintf('$%s->%s(%s)', $this->printOperand($node->var, $scope), $this->printOperand($node->name, $scope), $this->printList($node->args, $scope));
 		} elseif ($node instanceof Op\Expr\ConstFetch) {
			return $this->printOperand($node->name, $scope);
		}

		dump($node);
		die;

		return '?';
	}

	public function printOperand(Operand $operand, Scope $scope, bool $quote = false): string
	{
		if ($operand instanceof Operand\Variable) {
			return sprintf('$%s', $this->printOperand($operand->name, $scope));
		} elseif ($operand instanceof Operand\Literal) {
			return $quote ? sprintf('\'%s\'', $operand->value) : $operand->value;
		} elseif ($operand instanceof Operand\Temporary) {
			if ($operand->original instanceof Operand\Variable) {
				return $this->printOperand($operand->original, $scope);
			}
		}

		foreach ($operand->ops as $op) {
			return $this->print($op, $scope);
		}

		return '?';
	}

	public function printBinaryOp(Op\Expr\BinaryOp $op): string
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

	private function printList(array $args, Scope $scope, string $glue = ', '): string
	{
		return implode($glue, array_map(function ($arg) use ($scope) {
			return $this->print($arg, $scope);
		}, $args));
	}

}