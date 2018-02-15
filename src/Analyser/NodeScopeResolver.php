<?php declare(strict_types = 1);

namespace PHPWander\Analyser;

use Nette\InvalidStateException;
use PHPCfg\Block;
use PHPCfg\Func;
use PHPCfg\Op;
use PHPCfg\Operand;
use PHPCfg\Script;
use PHPCfg\Op\Expr\ArrayDimFetch;
use PHPCfg\Op\Expr\Assign;
use PHPCfg\Op\Expr\BinaryOp;
use PHPWander\Broker\Broker;
use PHPStan\File\FileHelper;
use PHPWander\Printer\Printer;
use PHPWander\Taint;
use PHPWander\TransitionFunction;

class NodeScopeResolver
{

	/** @var Printer */
	private $printer;

	/** @var TransitionFunction */
	private $transitionFunction;

	/** @var \PHPWander\Broker\Broker */
	private $broker;

	/** @var \PHPWander\Parser\Parser */
	private $parser;

	/** @var \PHPStan\File\FileHelper */
	private $fileHelper;

	/** @var bool */
	private $polluteScopeWithLoopInitialAssignments;

	/** @var bool */
	private $polluteCatchScopeWithTryAssignments;

	/** @var string[][] className(string) => methods(string[]) */
	private $earlyTerminatingMethodCalls;

	/** @var bool[] filePath(string) => bool(true) */
	private $analysedFiles = [];

	/** @var int[] filePath(string) => int */
	private $includedFilesResults = [];

	/** @var Func[] */
	private $functions;

	/** @var BlockScopeStorage */
	private $blockScopeStorage;

	/** @var FuncCallStorage */
	private $funcCallStorage;

	public function __construct(
		Printer $printer,
		BlockScopeStorage $blockScopeStorage,
		FuncCallStorage $funcCallStorage,
		Broker $broker,
		TransitionFunction $transitionFunction,
		\PHPWander\Parser\Parser $parser,
		FileHelper $fileHelper,
		bool $polluteScopeWithLoopInitialAssignments = true,
		bool $polluteCatchScopeWithTryAssignments = false,
		array $earlyTerminatingMethodCalls = []
	)
	{
		$this->printer = $printer;
		$this->blockScopeStorage = $blockScopeStorage;
		$this->funcCallStorage = $funcCallStorage;
		$this->broker = $broker;
		$this->transitionFunction = $transitionFunction;
		$this->parser = $parser;
		$this->fileHelper = $fileHelper;
		$this->polluteScopeWithLoopInitialAssignments = $polluteScopeWithLoopInitialAssignments;
		$this->polluteCatchScopeWithTryAssignments = $polluteCatchScopeWithTryAssignments;
		$this->earlyTerminatingMethodCalls = $earlyTerminatingMethodCalls;
		$this->functions = [];
	}

	/** @param string[] $files */
	public function setAnalysedFiles(array $files): void
	{
		$this->analysedFiles = array_fill_keys($files, true);
	}

	public function addAnalysedFile(string $file): void
	{
		$this->analysedFiles[$file] = true;
	}

	public function isFileAnalysed(string $file): bool
	{
		return array_key_exists($file, $this->analysedFiles);
	}

	public function processScript(
		Script $script,
		Scope $scope,
		callable $opCallback
	): Scope {
		foreach ($script->functions as $function) {
			if (array_key_exists($function->name, $this->functions)) {
				throw new InvalidStateException(sprintf('Cannot redeclare a function %s.', $function->name));
			}

			$this->functions[$function->name] = $function;
		}

		$scope = $this->processBlock($script->main->cfg, $scope, $opCallback);

		return $scope;
	}

	private function processBlock(Block $block, Scope $scope, callable $opCallback, Op\Stmt $stmt = null, bool $negated = false): Scope
	{
		if ($this->blockScopeStorage->hasBlock($block)) {
			return $scope;
		}

		$blockScope = $scope->enterBlock($block, $stmt, $negated);
		$this->blockScopeStorage->put($block, $blockScope);

		if ($stmt) {
			$stmt->setAttribute('block', $blockScope);
		}

		$blockScope = $this->processNodes($block->children, $blockScope, $opCallback);

		return $blockScope->leaveBlock();
	}

	/**
	 * @param Op[] $nodes
	 */
	public function processNodes(array $nodes, Scope $scope, callable $opCallback): Scope {
		foreach ($nodes as $i => $op) {
			$scope = $this->processNode($op, $scope, $opCallback);
		}

		return $scope;
	}

	private function processNode(Op $op, Scope $scope, callable $nodeCallback): Scope
	{
		if ($op instanceof Op\Expr\New_) {
			$name = $this->printer->printOperand($op->class, $scope);
			$op->setAttribute('type', $name);

		} elseif ($op instanceof Op\Stmt\Jump) {
			$scope = $this->processBlock($op->target, $scope, $nodeCallback, null, $scope->isNegated());

		} elseif ($op instanceof Op\Expr\Include_) {
			$scope = $this->processInclude($scope, $op, $nodeCallback);

		} elseif ($op instanceof Op\Stmt\JumpIf) {
			$scope = $this->processIf($op, $scope, $nodeCallback);

		} elseif ($op instanceof Op\Stmt\Function_) {
//			$scope = $this->enterFunction($scope, $op);

		} elseif ($op instanceof Op\Expr\Closure) {

		} elseif ($op instanceof Assign) {
			$scope = $this->processAssign($scope, $op);

		} elseif ($op instanceof Op\Expr\FuncCall) {
			$funcName = $this->printer->printOperand($op->name, $scope);
			if (array_key_exists($funcName, $this->functions)) {
				$this->processFunctionCall($this->functions[$funcName], $op, $scope, $nodeCallback);
			} else {
				$taint = $this->transitionFunction->transferOp($scope, $op);
				$op->setAttribute(Taint::ATTR, $taint);
			}

		} elseif ($op instanceof ArrayDimFetch) {
			$this->processArrayFetch($op, $scope);

		} elseif ($op instanceof BinaryOp\Concat) {
			$taint = $this->transitionFunction->leastUpperBound(
				$this->transitionFunction->transfer($scope, $op->left),
				$this->transitionFunction->transfer($scope, $op->right)
			);
			$op->setAttribute(Taint::ATTR, $taint);
		} elseif ($op instanceof Op\Expr\ConcatList) {
			$taint = Taint::UNKNOWN;
			foreach ($op->list as $part) {
				$taint = $this->transitionFunction->leastUpperBound($taint, $this->transitionFunction->transfer($scope, $part));
			}
			$op->setAttribute(Taint::ATTR, $taint);
		} elseif ($op instanceof Op\Terminal\Return_) {
			$taint = $this->transitionFunction->transferOp($scope, $op, true);
			$op->setAttribute(Taint::ATTR, $taint);

			if (!$scope->isInFuncCall()) {
				$scope->setResultTaint($taint);
			}
		} elseif ($op instanceof Op\Expr\Cast) {
			$scope = $this->transitionFunction->transferCast($scope, $op);
		} elseif ($op instanceof Op\Expr\MethodCall) {
			dump(__METHOD__);
			dump($op);
			die;
		} elseif ($op instanceof Op\Iterator\Reset) {
			$name = $this->printer->printOperand($op->var, $scope);
			$scope = $scope->assignVariable($name, $this->transitionFunction->transfer($scope, $op->var));
		} elseif ($op instanceof Op\Iterator\Value) {
			$taint = $this->transitionFunction->transfer($scope, $op->var);

			$op->setAttribute(Taint::ATTR, $taint);
			$scope = $scope->assignTemporary($op->result, $taint);
		}

		$nodeCallback($op, $scope);

		return $scope;
	}

	private function processAssign(Scope $scope, Assign $op): Scope
	{
		$name = $this->printer->printOperand($op->var, $scope);

		if ($op->expr instanceof Operand\Temporary) {
			foreach ($op->expr->ops as $_op) {
				if ($_op instanceof Op\Expr\Closure) {
					$this->functions[$name] = &$this->functions[$_op->func->name];
				} elseif ($_op instanceof Op\Expr\New_) {
					$type = $_op->getAttribute('type');
					$op->setAttribute('type', $type);
				} elseif ($_op instanceof Op\Expr\Array_) {
					$taint = Taint::UNKNOWN;
					foreach ($_op->keys as $index => $key) {
						$arrayItem = $this->printer->printArrayFetch($op->var, $key ?: $index, $scope);
						$scope = $scope->assignVariable($arrayItem, $this->transitionFunction->transfer($scope, $_op->values[$index]));
						$taint = $this->transitionFunction->leastUpperBound($taint, $scope->getVariableTaint($arrayItem));
					}

					$_op->setAttribute(Taint::ATTR, $taint);
				}
			}
		}

		$taint = $this->transitionFunction->transfer($scope, $op->expr);
		$op->setAttribute(Taint::ATTR, $taint);
		$scope = $scope->assignVariable($name, $taint);

		$scope = $scope->assignTemporary($op->expr, $taint);
		$scope = $scope->assignTemporary($op->result, $taint);
//		taint($scope->getVariableTaints());

		return $scope;
	}

	private function processArrayFetch(ArrayDimFetch $op, Scope $scope): Scope
	{
		if ($op->var instanceof Operand\Temporary) {
			if ($op->var->original instanceof Operand\Variable) {
				/** @var Operand\Variable $variable */
				$variable = $op->var->original;

				if ($this->transitionFunction->isSuperGlobal($variable, $scope)) {
					$taint = $this->transitionFunction->transferSuperGlobal($variable, $this->unpackExpression($op->dim, $scope));
					$op->setAttribute(Taint::ATTR, $taint);
				} else {
					$taint = $this->transitionFunction->transfer($scope, $variable);
					$op->setAttribute(Taint::ATTR, $taint);
				}

				$scope = $scope->assignTemporary($op->result, $taint);
//				$op->result->setAttribute(Taint::ATTR, $taint);
			} else {
				dump(__METHOD__);
				dump($op->var->original);
				die;
			}
		}

		return $scope;
	}

	private function processFunctionCall(Func $function, Op\Expr\FuncCall $call, Scope $scope, callable $nodeCallback): Scope
	{
		$bindArgs = [];

		foreach ($function->params as $i => $param) {
			/** @var Operand $arg */
			$arg = $call->args[$i];

			if ($arg instanceof Operand\Temporary) {
				if ($arg->original instanceof Operand\Variable) {
					$bindArgs[$this->printer->print($param, $scope)] = $scope->getVariableTaint($this->printer->printOperand($arg, $scope));
				} else {
					$bindArgs[$this->printer->print($param, $scope)] = $this->lookForFuncCalls($arg);
				}
			}
		}

		$currentScope = $scope;
		$mapping = $this->findFuncCallMapping($function, $bindArgs);

		if ($mapping !== null) {
			$taint = $mapping->getTaint();
		} else {
			$scope = $scope->enterFuncCall($function, $call);

			foreach ($bindArgs as $argName => $argTaint) {
				$scope = $scope->assignVariable($argName, $argTaint);
			}

			$this->processNodes($function->cfg->children, $scope, $nodeCallback);

			$funcCallResult = new FuncCallResult($this->transitionFunction);
			$taint = $this->collectTaintsOfSubgraph($function->cfg, $funcCallResult);

			$mapping = new FuncCallMapping($function, $bindArgs, $funcCallResult, $funcCallResult->getTaint());
			$this->funcCallStorage->put($call, $mapping);
			$call->setAttribute('mapping', $mapping);

//			$currentScope = $scope->leaveFuncCall();
		}

		$call->setAttribute(Taint::ATTR, $taint);

		return $currentScope;
	}

	private function lookForFuncCalls(Operand\Temporary $arg): int
	{
		$taint = Taint::UNKNOWN;
		if ($arg->original === null) {
			/** @var Op $op */
			foreach ($arg->ops as $op) {
				$taint = $this->transitionFunction->leastUpperBound($taint, (int) $op->getAttribute(Taint::ATTR));
			}
		}

		return $taint;
	}

	private function processInclude(Scope $scope, Op\Expr\Include_ $op, callable $nodeCallback): Scope
	{
		if ($op->expr instanceof Operand\Temporary) {
			if ($this->isExprResolvable($op->expr)) {
				$file = $this->resolveIncludedFile($op->expr, $scope);

				if (is_file($file)) {
					if (!array_key_exists($file, $this->analysedFiles)) {
						$this->addAnalysedFile($file);
						$scriptScope = $this->processScript(
							$this->parser->parseFile($file),
							$scope->enterFile($file),
							$nodeCallback
						);

						$scope = $scriptScope->leaveFile();

						$taint = $scriptScope->getResultTaint();

						$this->includedFilesResults[$file] = $taint;
					} elseif (array_key_exists($file, $this->includedFilesResults)) {
						$taint = $this->includedFilesResults[$file];
					} else {
						$taint = Taint::UNKNOWN;
					}

					$threats = ['result'];

					$op->setAttribute(Taint::ATTR, $taint);
					$op->setAttribute(Taint::ATTR_THREATS, $threats);
				}
			} elseif ($this->isSafeForFileInclusion($op->expr, $scope)) {
				$taint = Taint::UNTAINTED;
				$threats = ['file'];

				$op->setAttribute(Taint::ATTR, $taint);
				$op->setAttribute(Taint::ATTR_THREATS, $threats);

			} else {
				$taint = Taint::TAINTED;
				$threats = ['file'];

				$op->setAttribute(Taint::ATTR, $taint);
				$op->setAttribute(Taint::ATTR_THREATS, $threats);
			}

			return $scope;
		}

		dump(__FUNCTION__);
		dump($scope);
		die;

		return $scope;
	}

	private function resolveIncludedFile(Operand\Temporary $expr, Scope $scope): string
	{
		if (!empty($expr->ops)) {
			return $this->unpackExpression($expr->ops[0], $scope);
		}

		dump('?');
		dump($expr);
		die;

		return '?';
	}

	private function unpackExpression($expr, Scope $scope): string
	{
		if ($expr instanceof BinaryOp\Concat) {
			return $this->unpackExpression($expr->left, $scope) . $this->unpackExpression($expr->right, $scope);
		} elseif ($expr instanceof Assign) {
			return $this->unpackExpression($expr->expr, $scope);
		} elseif ($expr instanceof Operand\Temporary) {
			if (!empty($expr->ops)) {
				return $this->unpackExpression($expr->ops[0], $scope);
			}

			return $this->unpackExpression($expr->original, $scope);
		} elseif ($expr instanceof Operand) {
			return $this->printer->printOperand($expr, $scope);
		}

		dump($expr);
		die;
	}

	private function isExprResolvable($expr): bool
	{
		if ($expr instanceof Operand\Literal) {
			return true;
		} elseif ($expr instanceof Operand\Variable) {
			return $this->isExprResolvable($expr->ops[0]);

		} elseif ($expr instanceof Operand\Temporary) {
			if (!empty($expr->ops)) {
				return $this->isExprResolvable($expr->ops[0]);
			} elseif ($expr->original) {
				return $this->isExprResolvable($expr->original);
			}

			dump('??');
			die;
//			return $this->isExprResolvable($expr->ops[0]); // all ops?
		} elseif ($expr instanceof Assign) {
			return $this->isExprResolvable($expr->expr);
		} elseif ($expr instanceof BinaryOp\Concat) {
			return $this->isExprResolvable($expr->left) && $this->isExprResolvable($expr->right);
		}

		return false;
	}

	private function isSafeForFileInclusion($expr, Scope $scope): bool
	{
		if ($expr instanceof Operand\Temporary) {
			if (!empty($expr->ops)) {
				return $this->isSafeForFileInclusion($expr->ops[0], $scope);
			}

			return $this->isSafeForFileInclusion($expr->original, $scope);
		} elseif ($expr instanceof BinaryOp\Concat) {
			return $this->isSafeForFileInclusion($expr->left, $scope) && $this->isSafeForFileInclusion($expr->right, $scope);
		} elseif ($expr instanceof Operand\Literal) {
			return true;
		} elseif ($expr instanceof Operand\Variable) {
			if (!empty($expr->ops)) {
				return $this->isSafeForFileInclusion($expr->ops[0], $scope);
			}
		} elseif ($expr instanceof Op\Expr\FuncCall) {
			return $this->transitionFunction->isSanitizer($expr->name, $scope, 'file');
		} elseif ($expr instanceof Assign) {
			return $this->isSafeForFileInclusion($expr->expr, $scope);
		}

		return false;
	}

	private function findFuncCallMapping(Func $function, array $bindArgs): ?FuncCallMapping
	{
		return $this->funcCallStorage->findMapping($function, $bindArgs);
	}

	private function processIf(Op\Stmt\JumpIf $op, Scope $scope, callable $nodeCallback): Scope
	{
		$scope = $this->processBlock($op->if, $scope, $nodeCallback, $op);
		$scope = $this->processBlock($op->else, $scope, $nodeCallback, $op, true);

		return $scope;
	}

	private function collectTaintsOfSubgraph(Block $cfg, FuncCallResult $funcCallResult, FuncCallPath $parent = null): int
	{
		$taint = Taint::UNKNOWN;
		foreach ($cfg->children as $op) {
			if ($op instanceof Op\Terminal\Return_) {
				$taint = $this->transitionFunction->leastUpperBound($taint, (int) $op->getAttribute(Taint::ATTR));
				$path = new FuncCallPath($parent, $op, FuncCallPath::EVAL_UNCONDITIONAL);
				$path->setTaint($taint);
				if ($parent === null) {
					$funcCallResult->addPath($path);
				}
			} elseif ($op instanceof Op\Stmt\Jump) {
				$path = new FuncCallPath($parent, $op, FuncCallPath::EVAL_UNCONDITIONAL);
				$path->setTaint($this->collectTaintsOfSubgraph($op->target, $funcCallResult, $path));
				if ($parent === null) {
					$funcCallResult->addPath($path);
				}

				$taint = $this->transitionFunction->leastUpperBound($taint, $path->getTaint());
			} elseif ($op instanceof Op\Stmt\JumpIf) {
				$ifPath = new FuncCallPath($parent, $op, FuncCallPath::EVAL_TRUE);
				$ifPath->setTaint($this->collectTaintsOfSubgraph($op->if, $funcCallResult, $ifPath));
				$taint = $this->transitionFunction->leastUpperBound($taint, $ifPath->getTaint());
				if ($parent === null) {
					$funcCallResult->addPath($ifPath);
				}

				$elsePath = new FuncCallPath($parent, $op, FuncCallPath::EVAL_FALSE);
				$elsePath->setTaint($this->collectTaintsOfSubgraph($op->else, $funcCallResult, $elsePath));
				$taint = $this->transitionFunction->leastUpperBound($taint, $elsePath->getTaint());
				if ($parent === null) {
					$funcCallResult->addPath($elsePath);
				}
			}
		}

		return $taint;
	}

}
