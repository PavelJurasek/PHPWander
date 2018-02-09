<?php declare(strict_types=1);

namespace PHPWander\Describer;

use PHPWander\Analyser\FuncCallPath;
use PHPWander\Analyser\Scope;
use PHPCfg\Op;
use PHPCfg\Operand;
use PHPWander\Printer\Printer;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
class StandardDescriber implements Describer
{

	/** @var Printer */
	private $printer;

	public function __construct(Printer $printer)
	{
		$this->printer = $printer;
	}

	public function getPrinter(): Printer
	{
		return $this->printer;
	}

	public function describe($node, Scope $scope): string
	{
		if ($node instanceof Op\Expr\Assign) {
			$str = sprintf('assignment on line %d in file %s', $node->getLine(), $node->getFile());
			$subOp = $node->expr;
		} elseif ($node instanceof Op\Expr\ArrayDimFetch) {
			$str = $this->printer->print($node, $scope);
		} elseif ($node instanceof Op\Expr\FuncCall) {
			$str = sprintf('function call to %s', $this->printer->print($node, $scope));

//			$mapping = $this->funcCallStorage->get($node);
			$mapping = $node->getAttribute('mapping');

			if ($mapping) {
				foreach ($mapping->getFuncCallResult()->getTaintingCallPaths() as $taintingCallPath) {
					$str .= ' - (' . $this->describeFuncCallPath($taintingCallPath, $scope) . ')';
				}
			}
		} elseif ($node instanceof Op\Expr\PropertyFetch) {
			$str = sprintf('property %s', $this->printer->print($node, $scope));
//			return $this->describeOp($op->var->ops[0]);
		} elseif ($node instanceof Op\Stmt\JumpIf) {
			$str = $this->printer->print($node, $scope);
		} elseif ($node instanceof Op\Stmt\Jump) {
//			$str = sprintf('jump');
//			$blockScope = $this->blockScopeStorage->get($node->target);
			$blockScope = $node->getAttribute('block');

			$str = $blockScope->isNegated() ? 'not ' : '';
			foreach ($node->target->children as $_op) {
				$str .= $this->describe($_op, $blockScope);
			}

		} elseif ($node instanceof Op\Expr\BinaryOp\Concat) {
			$str = sprintf('concat of %s and %s on line %s in file %s', $this->describeOperand($node->left, $scope), $this->describeOperand($node->right, $scope), $node->getLine(), $node->getFile());
		} elseif ($node instanceof Op\Expr\BinaryOp) {
			$str = sprintf('%s %s %s', $this->describeOperand($node->left, $scope), $this->printer->printBinaryOp($node), $this->describeOperand($node->right, $scope));
		} elseif ($node instanceof Op\Phi) {
			$parentBlock = $scope->getParentBlock();

			if ($parentBlock === null) {
				dump(__METHOD__);
				dump('no parent block');
				die;
			}

			$stmt = $scope->getStatementForBlock($parentBlock);
//			dump($scope->getCurrentBlock());

			/** @var Operand\Variable $var */
			foreach ($node->vars as $var) {
				foreach ($parentBlock->children as $child) {
					if (in_array($child, $var->ops, true)) {
						return $stmt ? sprintf('%s (%s)', $this->describeOperand($var, $scope), $this->describe($stmt, $scope)) : $this->describeOperand($var, $scope);
					}
				}
			}

			$str = sprintf('one of: %s', $this->describeOperand($node->vars[0], $scope));

			for ($i = 1; $i < count($node->vars); $i++) {
				$str .= sprintf(', %s', $this->describeOperand($node->vars[$i], $scope));
			}
		} elseif ($node instanceof Op\Terminal\Return_) {
			$str = sprintf('return %s', $this->describeOperand($node->expr, $scope));
		} elseif ($node instanceof Op\Expr\Param) {
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
					$str .= ' - ' . $this->describe($_op, $scope);
				}
			}
		}

		return $str;
	}

	private function describeOperand(Operand $operand, Scope $scope)
	{
		if ($operand instanceof Operand\Literal) {
			return sprintf('literal %s', $this->printer->print($operand, $scope));
		} elseif ($operand instanceof Operand\Temporary) {
			if (!empty($operand->ops)) {
				$result = $this->describe($operand->ops[0], $scope);

				if ($result !== '') {
					return $result;
				}
			}

			if ($operand->original) {
				return $this->describeOperand($operand->original, $scope);
			}
		} elseif ($operand instanceof Operand\Variable) {
			return sprintf('variable %s', $this->printer->print($operand, $scope));
		}

		dump(__METHOD__);
		return '?';
	}

	private function describeFuncCallPath(FuncCallPath $taintingCallPath, Scope $scope): string
	{
		$str = '';
		if ($taintingCallPath->getStatement() instanceof Op\Stmt\JumpIf) {
			$str = sprintf('%s%s', $taintingCallPath->getEvaluation() === FuncCallPath::EVAL_FALSE ? 'not ' : '', $this->describe($taintingCallPath->getStatement(), $scope));

			foreach ($taintingCallPath->getChildren() as $child) {
				if ($this->isTainted($child->getTaint())) {
					$str .= ' - ' . $this->describeFuncCallPath($child, $scope);
				}
			}
		} elseif ($taintingCallPath->getStatement() instanceof Op\Terminal\Return_) {
			$str = sprintf('return %s', $this->describeOperand($taintingCallPath->getStatement()->expr, $scope));
		} elseif ($taintingCallPath->getStatement() instanceof Op\Stmt\Jump) {
			$str = $this->describe($taintingCallPath->getStatement(), $scope);
		}

		return $str;
	}

	protected function isTainted(int $taint): bool
	{
		return $taint === Taint::TAINTED || $taint === Taint::BOTH;
	}

}
