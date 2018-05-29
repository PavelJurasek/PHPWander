<?php declare(strict_types = 1);

namespace PHPWander\Analyser;

use Nette\InvalidStateException;
use Nette\NotImplementedException;
use PHPCfg\Block;
use PHPCfg\Func;
use PHPCfg\Op;
use PHPCfg\Operand;
use PHPCfg\Script;
use PHPCfg\Op\Expr\ArrayDimFetch;
use PHPCfg\Op\Expr\Assign;
use PHPCfg\Op\Expr\BinaryOp;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType;
use PHPWander\Broker\Broker;
use PHPStan\File\FileHelper;
use PHPWander\PhiTaint;
use PHPWander\Printer\Printer;
use PHPWander\Reflection\ClassReflection;
use PHPWander\ScalarTaint;
use PHPWander\Taint;
use PHPWander\TransitionFunction;
use PHPWander\VectorTaint;

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

	/** @var Taint[] filePath(string) => ScalarTaint */
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
			$funcName = $function->class === null ? $function->name : sprintf('%s::%s', $function->class->value, $function->name);

			if (array_key_exists($funcName, $this->functions)) {
				throw new InvalidStateException(sprintf('Cannot redeclare a function %s.', $funcName));
			}

			$this->functions[$funcName] = $function;
		}

		$scope = $this->processBlock($script->main->cfg, $scope, $opCallback);

		return $scope;
	}

	private function processBlock(Block $block, Scope $scope, callable $opCallback, Op\Stmt $stmt = null, bool $negated = false, bool $omitSavedBlock = false): Scope
	{
		if ($this->blockScopeStorage->hasBlock($block) && !$omitSavedBlock) {
			return $scope;
		}

		$blockScope = $scope->enterBlock($block, $stmt, $negated);

		if (!$omitSavedBlock) {
			$this->blockScopeStorage->put($block, $blockScope);
		}

		if ($stmt) {
			$stmt->setAttribute('block', $blockScope);
		}

		$blockScope = $this->processNodes($block->children, $blockScope, $opCallback);

		return $blockScope->leaveBlock();
	}

	/**
	 * @param Op[] $nodes
	 */
	public function processNodes(array $nodes, Scope $scope, callable $opCallback): Scope
	{
		foreach ($nodes as $i => $op) {
			$scope = $this->processNode($op, $scope, $opCallback);
		}

		return $scope;
	}

	private function processNode(Op $op, Scope $scope, callable $nodeCallback): Scope
	{
		if ($op instanceof Op\Expr\New_) {
			$type = $this->printer->printOperand($op->class, $scope);
			$class = $this->broker->getClass($type);

			$taint = new VectorTaint(new ObjectType($type));
			foreach ($class->getPropertyNames() as $property) {
				$taint->addTaint($property, new ScalarTaint(Taint::UNKNOWN));
			}

			if ($class->hasMethod('__construct')) {
				$method = $class->getMethod('__construct');
				$methodCall = new Op\Expr\MethodCall(new Operand\Temporary(), new Operand\Literal($method->func->name), $op->args);

				$this->processMethodCall($method->func, $methodCall, $scope, $nodeCallback, $class, new BoundVariable('$this', $taint));
			}

			$type = $taint->getType();

			$op->setAttribute('type', $type);
			$op->setAttribute(Taint::ATTR, $taint);
			$scope = $scope->assignTemporary($op->result, $taint);

		} elseif ($op instanceof Op\Stmt\Jump) {
			$scope = $this->processBlock($op->target, $scope, $nodeCallback, null, $scope->isNegated(), true);

		} elseif ($op instanceof Op\Expr\Include_) {
			$scope = $this->processInclude($scope, $op, $nodeCallback);

		} elseif ($op instanceof Op\Stmt\JumpIf) {
			$scope = $this->processIf($op, $scope, $nodeCallback);

		} elseif ($op instanceof Op\Stmt\Switch_) {
			$scope = $this->processSwitch($op, $scope, $nodeCallback);

		} elseif ($op instanceof Assign) {
			$scope = $this->processAssign($scope, $op);

		} elseif ($op instanceof Op\Expr\FuncCall) {
			$funcName = $this->printer->printOperand($op->name, $scope);

			if (array_key_exists($funcName, $this->functions)) {
				$this->processFunctionCall($this->functions[$funcName], $op, $scope, $nodeCallback);
			} else {
				$taint = $this->transitionFunction->transferOp($scope, $op);
				$op->setAttribute(Taint::ATTR, $taint);
				$scope = $scope->assignTemporary($op->result, $taint);
			}

		} elseif ($op instanceof ArrayDimFetch) {
			$this->processArrayFetch($op, $scope);

		} elseif ($op instanceof BinaryOp\Concat) {
			$left = $this->transitionFunction->transfer($scope, $op->left);
			$right = $this->transitionFunction->transfer($scope, $op->right);

			$taint = $left->leastUpperBound($right);
			$op->setAttribute(Taint::ATTR, $taint);

		} elseif ($op instanceof Op\Expr\ConcatList) {
			$taint = new ScalarTaint(Taint::UNKNOWN);

			foreach ($op->list as $part) {
				$taint = $taint->leastUpperBound($this->transitionFunction->transfer($scope, $part));
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
			$var = $this->printer->printOperand($op->var, $scope);

			if ($scope->isInMethodCall() && $scope->getBoundVariable() !== null && $scope->getBoundVariable()->getVar() === $var) {
				$taint = $scope->getBoundVariable()->getTaint();
				$class = $scope->getClass();

			} elseif ($op->var instanceof Operand\Temporary && $op->var->ops[0] instanceof Op\Expr\StaticCall) {
				$className = $op->var->ops[0]->class->value;
				$taint = new VectorTaint(new ObjectType($className));
				$class = $this->broker->getClass($className);

			} else {
				/** @var VectorTaint $taint */
				$taint = $scope->getVariableTaint($var);
				$type = $taint->getType();

				if ($type instanceof UnionType) {
					$classes = $type->getReferencedClasses();

					if (count($classes) === 0) {
						throw new ShouldNotHappenException;
					} elseif (count($classes) > 1) {
						throw new NotImplementedException;
					}

					$className = reset($classes);
				} elseif ($type instanceof TypeWithClassName) {
					$className = $type->getClassName();
				} else {
					// @todo add warning to results
//					throw new ShouldNotHappenException;
					return $scope;
				}

				$class = $this->broker->getClass($className);
			}

			if ($class->isUserDefined()) {
				$method = $class->getMethod($this->printer->printOperand($op->name, $scope));

				$this->processMethodCall($method->func, $op, $scope, $nodeCallback, $class, new BoundVariable('$this', $taint));
			}

		} elseif ($op instanceof Op\Iterator\Reset) {
			$name = $this->printer->printOperand($op->var, $scope);
			$scope = $scope->assignVariable($name, $this->transitionFunction->transfer($scope, $op->var));

		} elseif ($op instanceof Op\Iterator\Value) {
			$taint = $this->transitionFunction->transfer($scope, $op->var);

			$op->setAttribute(Taint::ATTR, $taint);
			$scope = $scope->assignTemporary($op->result, $taint);

		} elseif ($op instanceof Op\Expr\BooleanNot) {
			$taint = new ScalarTaint(Taint::UNTAINTED, new BooleanType);
			$scope = $scope->assignTemporary($op->result, $taint);
			$op->setAttribute(Taint::ATTR, $taint);
		} elseif ($op instanceof Op\Expr\StaticCall) {
			$className = $this->printer->printOperand($op->class, $scope);
			$class = $this->broker->getClass($className);

			$method = $class->getMethod($this->printer->printOperand($op->name, $scope));

			if ($class->getStaticPropertiesTaint() === null) {
				$class->setStaticPropertiesTaint($this->processClassStaticProperties($class, $scope));
			}

			$this->processMethodCall($method->func, $op, $scope, $nodeCallback, $class, new BoundVariable('self', $class->getStaticPropertiesTaint()));

		} elseif ($op instanceof Op\Expr\StaticPropertyFetch) {
			$className = $this->printer->printOperand($op->class, $scope);

			if ($scope->isInMethodCall() && $scope->getBoundVariable()->getVar() === $className) {
				$class = $scope->getClass();
			} else {
				$class = $this->broker->getClass($className);
			}

			if ($class->getStaticPropertiesTaint() === null) {
				$class->setStaticPropertiesTaint($this->processClassStaticProperties($class, $scope));
			}

			$propertyName = $this->printer->printOperand($op->name, $scope);
			$staticProperties = $class->getStaticPropertiesTaint();

			$taints = $staticProperties->getTaint($propertyName);

			$op->setAttribute(Taint::ATTR, $taints);
		} elseif ($op instanceof Op\Expr\Array_) {
			$scope = $this->processArrayCreation($op, $scope, null);
		} elseif ($op instanceof Op\Expr\Isset_) {
			$scope = $scope->assignTemporary($op->result, new ScalarTaint(Taint::UNTAINTED, new BooleanType));
		} elseif ($op instanceof Op\Expr\ConstFetch) {
			$taint = $this->transitionFunction->transferOp($scope, $op);

			$op->setAttribute(Taint::ATTR, $taint);
			$scope->assignTemporary($op->result, $taint);
		}

		$nodeCallback($op, $scope);

		return $scope;
	}

	private function processAssign(Scope $scope, Assign $op): Scope
	{
		if (!$op->var instanceof Operand\Temporary || $op->var->original !== null || $op->var->ops[0] !== $op) {
			$name = $this->printer->printOperand($op->var, $scope);
		}

		if ($op->expr instanceof Operand\Temporary) {
			foreach ($op->expr->ops as $_op) {
				if ($_op instanceof Op\Expr && $scope->hasTemporaryTaint($_op->result)) {
					$taint = $scope->getTemporaryTaint($_op->result);
					$type = $_op->getAttribute('type');
				} elseif ($_op instanceof Op\Expr\Closure && isset($name)) {
					$this->functions[$name] = &$this->functions[$_op->func->name];
				} elseif ($_op instanceof Op\Expr\Array_) {
					$scope = $this->processArrayCreation($_op, $scope, $op);
				} elseif ($_op instanceof Op\Iterator\Value) {
					$taint = $_op->getAttribute(Taint::ATTR);
				}
			}
		}

		if (isset($taint)) {
			// do nothing
		} elseif ($op->expr instanceof Operand\Temporary && $scope->hasTemporaryTaint($op->expr)) {
			$taint = $scope->getTemporaryTaint($op->expr);
			$type = $taint->getType();
		} else {
			$taint = $this->transitionFunction->transfer($scope, $op->expr);
		}

		$op->setAttribute(Taint::ATTR, $taint);

		if (isset($name)) {
			$scope = $scope->assignVariable($name, $taint);
		}

		if ($op->var instanceof Operand\Temporary) {
			$scope = $scope->assignTemporary($op->var, $taint);

			foreach ($op->var->ops as $_op) {
				if ($_op instanceof Op\Expr\StaticPropertyFetch) {
					$className = $this->printer->printOperand($_op->class, $scope);
					$class = $this->broker->getClass($className);

					if ($class->getStaticPropertiesTaint() === null) {
						$class->setStaticPropertiesTaint($this->processClassStaticProperties($class, $scope));
					}

					$propertyName = $this->printer->printOperand($_op->name, $scope);
					$class->updateStaticProperty($propertyName, $taint);
				} elseif ($_op instanceof ArrayDimFetch) {
					$property = $_op->getAttribute('property');

					if ($property !== null) {
						/** @var Op\Expr\PropertyFetch $property */
						$propertyName = $this->printer->print($property, $scope);

						$scope->assignVariable($propertyName, $taint);
					}
				}
			}
		}

		$scope = $scope->assignTemporary($op->expr, $taint);
		$scope = $scope->assignTemporary($op->result, $taint);

		if (isset($type)) {
			$op->setAttribute('type', $type);
		}
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
					$dim = $this->unpackExpression($op->dim, $scope);
					$taint = $this->transitionFunction->transferSuperGlobal($variable, $dim ? $dim[0] : null);
				} else {
					$taint = $this->transitionFunction->transfer($scope, $variable);
				}

				$op->setAttribute(Taint::ATTR, $taint);
				$scope = $scope->assignTemporary($op->result, $taint);
			} elseif ($op->var->ops) {
				$taint = new ScalarTaint(Taint::UNKNOWN);
				foreach ($op->var->ops as $_op) {
					if ($_op instanceof Op\Expr\PropertyFetch || $_op instanceof Op\Expr\StaticPropertyFetch) {
						$op->setAttribute('property', $_op);
					}
					$taint = $taint->leastUpperBound($this->transitionFunction->transferOp($scope, $_op));
				}

				$op->setAttribute(Taint::ATTR, $taint);
				$scope = $scope->assignTemporary($op->result, $taint);
			}
		}

		return $scope;
	}

	/**
	 * @param Op\Expr\FuncCall|Op\Expr\NsFuncCall $call
	 */
	private function processFunctionCall(
		Func $function,
		Op\Expr $call,
		Scope $scope,
		callable $nodeCallback
	): Scope
	{
		$this->assertFuncCallArgument($call);
		$bindArgs = [];
		$scope = $scope->enterFuncCall($function, $call);
		$scope = $this->bindFuncCallArgs($function, $call, $scope, $nodeCallback, $bindArgs);

		$mapping = $this->findFuncCallMapping($function, $bindArgs);

		if ($mapping !== null) {
			$taint = $mapping->getTaint();
		} else {
			$scope = $this->processBlock($function->cfg, $scope, $nodeCallback, null, false, true);

			$funcCallResult = new FuncCallResult($this->transitionFunction);
			$taint = $this->collectTaintsOfSubgraph($function->cfg, $funcCallResult, new BlockTaintStorage);

			$mapping = new FuncCallMapping($function, $bindArgs, $funcCallResult, $funcCallResult->getTaint());
			$this->funcCallStorage->put($call, $mapping);
			$call->setAttribute('mapping', $mapping);
		}

		$call->setAttribute(Taint::ATTR, $taint);
		$scope = $scope->assignTemporary($call->result, $taint);

		return $scope;
	}

	/**
	 * @param Op\Expr\MethodCall|Op\Expr\StaticCall $call
	 */
	private function processMethodCall(
		Func $function,
		Op\Expr $call,
		Scope $scope,
		callable $nodeCallback,
		ClassReflection $classReflection,
		BoundVariable $boundVariable
	): Scope
	{
		$this->assertFuncCallArgument($call);
		$bindArgs = [];
		$scope = $scope->enterMethodCall($function, $call, $classReflection, $boundVariable);
		$scope = $this->bindFuncCallArgs($function, $call, $scope, $nodeCallback, $bindArgs);

		$mapping = $this->findFuncCallMapping($function, $bindArgs);

		if ($mapping !== null) {
			$taint = $mapping->getTaint();
		} else {
			$scope = $this->processBlock($function->cfg, $scope, $nodeCallback, null, false, true);

			$funcCallResult = new FuncCallResult($this->transitionFunction);
			$taint = $this->collectTaintsOfSubgraph($function->cfg, $funcCallResult, new BlockTaintStorage);

			$mapping = new FuncCallMapping($function, $bindArgs, $funcCallResult, $funcCallResult->getTaint());
			$this->funcCallStorage->put($call, $mapping);
			$call->setAttribute('mapping', $mapping);
		}

		$call->setAttribute(Taint::ATTR, $taint);

		return $scope;
	}

	private function assertFuncCallArgument($call): void
	{
		if (!$call instanceof Op\Expr\FuncCall && !$call instanceof Op\Expr\NsFuncCall && !$call instanceof Op\Expr\MethodCall && !$call instanceof Op\Expr\StaticCall) {
			throw new \InvalidArgumentException(sprintf('%s: $call must be instance of FuncCall, NsFuncCall, MethodCall or StaticCall, %s given.', __METHOD__, get_class($call)));
		}
	}

	private function lookForFuncCalls(Operand\Temporary $arg, Scope $scope, callable $nodeCallback): Scope
	{
		if (count($arg->ops) === 1) {
			/** @var Op $op */
			$op = $arg->ops[0];
			if ($op->getAttribute(Taint::ATTR) === null) {
				$scope = $this->processNode($op, $scope, $nodeCallback);
			}

			$scope = $scope->assignTemporary($arg, $op->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN)));

			return $scope;
		} else {
			$taint = new ScalarTaint(Taint::UNKNOWN);
			/** @var Op $op */
			foreach ($arg->ops as $op) {
				if ($op->getAttribute(Taint::ATTR) === null) {
					$scope = $this->processNode($op, $scope, $nodeCallback);
				}
				$taint = $taint->leastUpperBound($op->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN)));
			}

			$scope = $scope->assignTemporary($arg, $taint);
		}

		return $scope;
	}

	private function processInclude(Scope $scope, Op\Expr\Include_ $op, callable $nodeCallback): Scope
	{
		if ($op->expr instanceof Operand\Temporary) {
			if ($this->isExprResolvable($op->expr)) {
				$files = $this->resolveIncludedFile($op->expr, $scope);

				if (count($files) === 0 && $op->expr->original instanceof Operand\Variable) {
					$taint = $scope->getVariableTaint($this->printer->printOperand($op->expr->original, $scope));

					if ($taint->isTainted()) {
						$threats = ['file'];
						$op->setAttribute(Taint::ATTR_THREATS, $threats);
					}

					$op->setAttribute(Taint::ATTR, $taint);
				}

				foreach ($files as $file) {
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
							$taint = new ScalarTaint(Taint::UNKNOWN);
						}

						$threats = ['result'];

						if (!$this->isSafeForFileInclusion($op->expr, $scope)) {
							$threats[] = 'file';
						}

						$op->setAttribute(Taint::ATTR, $taint);
						$op->setAttribute(Taint::ATTR_THREATS, $threats);
					}
				}

			} elseif ($this->isSafeForFileInclusion($op->expr, $scope)) {
				$taint = new ScalarTaint(Taint::UNTAINTED);

				$op->setAttribute(Taint::ATTR, $taint);

			} else {
				$taint = new ScalarTaint(Taint::TAINTED);
				$threats = ['file'];

				$op->setAttribute(Taint::ATTR, $taint);
				$op->setAttribute(Taint::ATTR_THREATS, $threats);
			}

			return $scope;
		} elseif ($op->expr instanceof Operand\Literal) {
			$file = dirname($scope->getFile()) .DIRECTORY_SEPARATOR. $this->printer->printOperand($op->expr, $scope);

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
					$taint = new ScalarTaint(Taint::UNKNOWN);
				}

				$threats = ['result'];

				$op->setAttribute(Taint::ATTR, $taint);
				$op->setAttribute(Taint::ATTR_THREATS, $threats);
			}

			return $scope;
		}

		return $scope;
	}

	private function resolveIncludedFile(Operand\Temporary $expr, Scope $scope): array
	{
		if (!empty($expr->ops)) {
			return $this->unpackExpression($expr->ops[0], $scope);
		}

		return ['?'];
	}

	private function unpackExpression($expr, Scope $scope): array
	{
		if ($expr instanceof Operand\Literal) {
			return [$this->printer->printOperand($expr, $scope)];
		} elseif ($expr instanceof Op\Phi) {
			$vars = array_map(function ($expr) use ($scope) {
				return implode('', $this->unpackExpression($expr, $scope));
			}, $expr->vars);

			return $vars;
//			return array_filter($vars, function ($var) {
//				return $var !== '';
//			});

		} elseif ($expr instanceof BinaryOp\Concat) {
			return $this->unpackConcat($expr->left, $expr->right, $scope);
		} elseif ($expr instanceof Assign) {
			return $this->unpackExpression($expr->expr, $scope);
		} elseif ($expr instanceof Operand\Temporary) {
			if ($expr->original !== null) {
				return $this->unpackExpression($expr->original, $scope);
			}

			if (!empty($expr->ops)) {
				return $this->unpackExpression($expr->ops[0], $scope);
			}

			return $this->unpackExpression($expr->original, $scope);
		} elseif ($expr instanceof Op\Expr\ConstFetch) {
			$constName = $this->printer->printOperand($expr->name, $scope);

			if ($scope->hasConstant($constName)) {
				return [$scope->getConstant($constName)];
			}

			return $this->unpackExpression($expr->name, $scope);
		} elseif ($expr instanceof Op\Expr\FuncCall) {
			if ($expr->name->value === 'dirname') {
				return [dirname($expr->args[0]->value)];
			}
		} elseif ($expr instanceof Op\Expr\ConcatList) {
			if (count($expr->list) === 2) {
				return $this->unpackConcat($expr->list[0], $expr->list[1], $scope);
			}
		} elseif ($expr instanceof Op\Iterator\Value) {
//			$vars = array_map(function ($expr) use ($scope) {
//				return implode('', $this->unpackExpression($expr, $scope));
//			}, $expr->vars);

//			return $vars;

			return $this->unpackExpression($expr->var, $scope);
		} elseif ($expr instanceof Op\Expr\Array_) {
			$values = array_map(function ($expr) use ($scope) {
				return implode('', $this->unpackExpression($expr, $scope));
			}, $expr->values);

			return $values;
		} elseif ($expr instanceof Op\Expr\Param) {
			return $this->unpackExpression($expr->name, $scope);
		}

		return [];
	}

	private function unpackConcat($left, $right, Scope $scope): array
	{
		$values = [];
		foreach ($this->unpackExpression($left, $scope) as $left) {
			foreach ($this->unpackExpression($right, $scope) as $right) {
				$values[] = $left . $right;
			}
		}

		return $values;
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

//			return $this->isExprResolvable($expr->ops[0]); // all ops?
		} elseif ($expr instanceof Assign) {
			return $this->isExprResolvable($expr->expr);
		} elseif ($expr instanceof BinaryOp\Concat) {
			return $this->isExprResolvable($expr->left) && $this->isExprResolvable($expr->right);
		} elseif ($expr instanceof Op\Expr\ConcatList) {
			foreach ($expr->list as $item) {
				if (!$this->isExprResolvable($item)) {
					return false;
				}
			}

			return true;

		} elseif ($expr instanceof Op\Expr\ConstFetch) {
			return $this->isExprResolvable($expr->name);
		} elseif ($expr instanceof Op\Phi) {
			foreach ($expr->vars as $var) {
				if (!$this->isExprResolvable($var)) {
					return false;
				}
			}

			return true;
		} elseif ($expr instanceof Op\Expr\FuncCall) {
			return true;
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
		$evaluation = $this->evaluate($op->cond, $scope);
		$op->setAttribute('eval', $evaluation);

		if ($evaluation instanceof ConstantScalarType) {
			if ($evaluation->getValue() == true) { // intentionally ==
				$scope = $this->processBlock($op->if, $scope, $nodeCallback, $op);
			} else {
				$scope = $this->processBlock($op->else, $scope, $nodeCallback, $op, true);
			}
		} else {
			$scope = $this->processBlock($op->if, $scope, $nodeCallback, $op);
			$scope = $this->processBlock($op->else, $scope, $nodeCallback, $op, true);
		}

		return $scope;
	}

	private function processSwitch(Op\Stmt\Switch_ $op, Scope $scope, callable $nodeCallback): Scope
	{
		$defaultReached = false;

		/** @var Block $target */
		foreach ($op->targets as $target) {
			$scope = $this->processBlock($target, $scope, $nodeCallback, $op);

			$firstChild = $target->children[0];
			if ($firstChild instanceof Op\Stmt\Jump && $firstChild->target === $op->default) {
				$defaultReached = true;
			}
		}

		if (!$defaultReached) {
			$scope = $this->processBlock($op->default, $scope, $nodeCallback, $op);
		}

		return $scope;
	}

	private function collectTaintsOfSubgraph(Block $cfg, FuncCallResult $funcCallResult, BlockTaintStorage $blockTaintStorage, FuncCallPath $parent = null): Taint
	{
		$taint = new PhiTaint;

		if ($blockTaintStorage->hasBlock($cfg)) {
			return $blockTaintStorage->get($cfg);
		} else {
			$blockTaintStorage->put($cfg, $taint);
		}

		foreach ($cfg->children as $op) {
			if ($op instanceof Op\Terminal\Return_) {
				$taint->addTaint($op->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN)));
				$path = new FuncCallPath($parent, $op, FuncCallPath::EVAL_UNCONDITIONAL);
				$path->setTaint($taint);
				if ($parent === null) {
					$funcCallResult->addPath($path);
				}
			} elseif ($op instanceof Op\Stmt\Jump) {
				$path = new FuncCallPath($parent, $op, FuncCallPath::EVAL_UNCONDITIONAL);
				$path->setTaint($this->collectTaintsOfSubgraph($op->target, $funcCallResult, $blockTaintStorage, $path));
				$blockTaintStorage->put($cfg, $path->getTaint());
				if ($parent === null) {
					$funcCallResult->addPath($path);
				}

				$taint->addTaint($path->getTaint());
			} elseif ($op instanceof Op\Stmt\JumpIf) {
				$eval = $op->getAttribute('eval', new MixedType);

				if ($eval instanceof MixedType || ($eval instanceof ConstantBooleanType && $eval->getValue() === true)) {
					$ifPath = new FuncCallPath($parent, $op, FuncCallPath::EVAL_TRUE);
					$ifPath->setTaint($this->collectTaintsOfSubgraph($op->if, $funcCallResult, $blockTaintStorage, $ifPath));
					$taint->addTaint($ifPath->getTaint());
					if ($parent === null) {
						$funcCallResult->addPath($ifPath);
					}
				}

				if ($eval instanceof MixedType || ($eval instanceof ConstantBooleanType && $eval->getValue() === false)) {
					$elsePath = new FuncCallPath($parent, $op, FuncCallPath::EVAL_FALSE);
					$elsePath->setTaint($this->collectTaintsOfSubgraph($op->else, $funcCallResult, $blockTaintStorage, $elsePath));
					$taint->addTaint($elsePath->getTaint());
					if ($parent === null) {
						$funcCallResult->addPath($elsePath);
					}
				}

				$blockTaintStorage->put($cfg, $taint);
			}
		}

		return $taint;
	}

	/**
	 * @param Op\Expr\FuncCall|Op\Expr\NsFuncCall|Op\Expr\MethodCall|Op\Expr\StaticCall $call
	 */
	private function bindFuncCallArgs(Func $function, Op\Expr $call, Scope $scope, callable $nodeCallback, array &$bindArgs): Scope
	{
		/** @var Op\Expr\Param $param */
		foreach ($function->params as $i => $param) {
			$variableName = $this->printer->print($param, $scope);

			if (array_key_exists($i, $call->args)) {
				/** @var Operand $arg */
				$arg = $call->args[$i];

				if ($arg instanceof Operand\Temporary) {
					if ($arg->original instanceof Operand\Variable) {
						if ($this->transitionFunction->isSuperGlobal($arg->original, $scope)) {
							$taint = $this->transitionFunction->transferSuperGlobal($arg->original);
						} else {
							$taint = $scope->getVariableTaint($this->printer->printOperand($arg, $scope));
						}
					} else {
						$scope = $this->lookForFuncCalls($arg, $scope, $nodeCallback);
						$taint = $scope->getTemporaryTaint($arg);
					}
				} elseif ($arg instanceof Operand\Literal) {
					$taint = $this->transitionFunction->transfer($scope, $arg);
				} else {
					$taint = new ScalarTaint(Taint::UNKNOWN);
				}

			} else {
				if ($param->defaultVar === null) {
					continue; // missing param, report?
				}

				$taint = $this->transitionFunction->transfer($scope, $param->defaultVar);
			}

			$bindArgs[$variableName] = $taint;
			$scope = $scope->assignVariable($variableName, $taint);
		}

		return $scope;
	}

	private function processClassStaticProperties(ClassReflection $classReflection, Scope $scope): VectorTaint
	{
		$staticTaints = new VectorTaint(new ObjectType($classReflection->getName()));
		foreach ($classReflection->getProperties() as $property) {
			if ($property->defaultVar === null) {
				$staticTaints->addTaint($property->name->value, new ScalarTaint(Taint::UNKNOWN));
			} elseif ($property->defaultVar instanceof Operand\Literal) {
				$staticTaints->addTaint($property->name->value, $this->transitionFunction->transfer($scope, $property->defaultVar));
			} elseif ($property->defaultVar instanceof Operand\Temporary) {
				foreach ($property->defaultVar->ops as $op) {
					if ($op instanceof Op\Expr\Array_) {
						$scope = $this->processArrayCreation($op, $scope);
					}
				}

				$staticTaints->addTaint($property->name->value, $property->defaultVar->ops[0]->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN)));
			} else {
				dump($property);
				die;
			}
		}

		return $staticTaints;
	}

	private function processArrayCreation(Op\Expr\Array_ $op, Scope $scope, ?Assign $parentOp = null): Scope
	{
		if ($op->getAttribute(Taint::ATTR) !== null) {
			return $scope;
		}

		$items = [];
		$taint = new VectorTaint(new MixedType);

		foreach ($op->keys as $index => $key) {
			$itemTaint = $this->transitionFunction->transfer($scope, $op->values[$index]);

			if ($parentOp) {
				$arrayItem = $this->printer->printArrayFetch($parentOp->var, $key ?: $index, $scope);
				$scope = $scope->assignVariable($arrayItem, $itemTaint);
			}

			$itemKey = $key ? $this->printer->printOperand($key, $scope) : $index;

			$taint->addTaint($itemKey, $itemTaint);
			$items[$itemKey] = $itemTaint;
		}

		$op->setAttribute(Taint::ATTR, $taint);
		$op->setAttribute('items', $items);

		return $scope;
	}

	private function evaluate($expr, Scope $scope): Type
	{
		if ($expr instanceof Operand\Literal) {
			return $this->transitionFunction->transfer($scope, $expr)->getType();
		} elseif ($expr instanceof Operand\Temporary) {
			if ($expr->original) {
				return $this->evaluate($expr->original, $scope);
			}

			return $this->evaluate($expr->ops[0], $scope);
		} elseif ($expr instanceof BinaryOp\Identical) {
			$left = $this->transitionFunction->transfer($scope, $expr->left);
			$right = $this->transitionFunction->transfer($scope, $expr->right);

			if ($left->getType()->accepts($right->getType())) {
				return new ConstantBooleanType(true);
			}
		}

		return new MixedType;
	}

}
