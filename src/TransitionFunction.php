<?php declare(strict_types=1);

namespace PHPWander;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPCfg\Operand\Literal;
use PHPStan\Type\FalseBooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\TrueBooleanType;
use PHPStan\Type\Type;
use PHPWander\Broker\Broker;
use PHPWander\Analyser\Scope;
use PHPWander\Printer\Printer;

/**
 * @author Pavel Jurásek
 */
class TransitionFunction
{

	/** @var Broker */
	private $broker;

	/** @var Printer */
	private $printer;

	/** @var SourceFunctions */
	private $sourceFunctions;

	/** @var SinkFunctions */
	private $sinkFunctions;

	/** @var SanitizerFunctions */
	private $sanitizerFunctions;

	/** @var TaintFunctions */
	private $taintFunctions;

	public function __construct(
		Broker $broker,
		Printer $printer,
		SourceFunctions $sourceFunctions,
		SinkFunctions $sinkFunctions,
		SanitizerFunctions $sanitizerFunctions,
		TaintFunctions $taintFunctions
	) {
		$this->broker = $broker;
		$this->printer = $printer;
		$this->sourceFunctions = $sourceFunctions;
		$this->sinkFunctions = $sinkFunctions;
		$this->sanitizerFunctions = $sanitizerFunctions;
		$this->taintFunctions = $taintFunctions;
	}

	public function transfer(Scope $scope, Operand $node): Taint
	{
		if ($node instanceof Literal) {
			return new ScalarTaint(Taint::UNTAINTED, $this->resolveType($node->value));
		} elseif ($node instanceof Operand\Temporary) {
			return $this->transferTemporary($scope, $node);
		} elseif ($node instanceof Operand\Variable) {
			if ($this->isSource($node, $scope, 'userinput')) {
				return new ScalarTaint(Taint::TAINTED);
			}

			return $scope->getVariableTaint($this->printer->printOperand($node, $scope));
		}

		dump($node);
		dump(__METHOD__);

		return new ScalarTaint(Taint::UNKNOWN);
	}

	private function transferTemporary(Scope $scope, Operand\Temporary $node): Taint
	{
		if ($scope->hasTemporaryTaint($node)) {
			return $scope->getTemporaryTaint($node);
		}

		if ($node->original !== null) {
			return $this->transfer($scope, $node->original);
		}

		if (count($node->ops) === 1) {
			return $this->transferOp($scope, $node->ops[0]);
		}

		$taint = new ScalarTaint(Taint::UNKNOWN);
		foreach ($node->ops as $op) {
			$taint = $taint->leastUpperBound($this->transferOp($scope, $op));
		}

		return $taint;
	}

	public function transferOp(Scope $scope, Op $op, bool $omitSavedAttribute = false): Taint
	{
		if ($op->hasAttribute(Taint::ATTR) && !$omitSavedAttribute) {
			return $op->getAttribute(Taint::ATTR) ?: new ScalarTaint(Taint::UNKNOWN, $op->getAttribute('type'));
		}

		if ($op instanceof Op\Terminal\Return_ && $op->expr !== null) {
			return $this->transfer($scope, $op->expr);
		} elseif ($op instanceof Op\Expr\FuncCall) {
			if ($op->name instanceof Literal) {
				$funcName = $this->printer->printOperand($op->name, $scope);

				$taintSection = $this->taintFunctions->getTaint($funcName);
				if ($taintSection) {
					$taints = [$taintSection];
					$taint = new ScalarTaint(Taint::TAINTED);
					$type = 'string';
					$op->setAttribute(Taint::ATTR_TAINT, $taints);
					$op->setAttribute(Taint::ATTR_TYPE, $type);
				}

				$source = $this->sourceFunctions->getSource($funcName);
				if ($source) {
					$taints = [$source];
					$taint = new ScalarTaint(Taint::TAINTED);
					$op->setAttribute(Taint::ATTR_SOURCE, $taints);
				}

				$sanitize = $this->sanitizerFunctions->getSanitize($funcName);
				if ($sanitize) {
					$sanitize = [$sanitize];
					$taint = new ScalarTaint(Taint::UNTAINTED);
					$op->setAttribute(Taint::ATTR_SANITIZE, $sanitize);
				}

				// sinks should be handled by rules?
				$sink = $this->sinkFunctions->getSink($funcName);
				if ($sink) {
					$sink = [$sink];
					$taint = new ScalarTaint(Taint::TAINTED);
					$op->setAttribute(Taint::ATTR_SINK, $sink);
				}

				if (isset($taint)) {
					return $taint;
				}

				$taint = new ScalarTaint(Taint::UNKNOWN);

				foreach ($op->args as $arg) {
					$taint = $taint->leastUpperBound($this->transfer($scope, $arg));
				}

				return $taint;

//				$function = $this->broker->getFunction($op->name, $scope);
//				$type = $function->getReturnType();

//				return $this->transferType($type);
			} elseif ($op->name instanceof Operand\Variable) {
				// func call on variable, will be handled by rule
			} else {
				dump(__METHOD__);
				dump('?');
				dump($op);
			}
		} elseif ($op instanceof Op\Expr\PropertyFetch) {
			return $scope->getVariableTaint($this->printer->print($op, $scope));

		} elseif ($op instanceof Op\Expr\BinaryOp\Plus) {
			return new ScalarTaint(Taint::UNTAINTED);
		} elseif ($op instanceof Op\Phi) {
			$taint = $this->transfer($scope, $op->vars[0]);

			for ($i = 1; $i < count($op->vars); $i++) {
				$taint = $taint->leastUpperBound($this->transfer($scope, $op->vars[$i]));
			}

			return $taint;
		}

		return new ScalarTaint(Taint::UNKNOWN);
	}

	public function transferCast(Scope $scope, Op\Expr\Cast $op): Scope
	{
		$trusted = false;

		if ($op->expr->original instanceof Operand\Variable) {
			$variableName = $this->printer->printOperand($op->expr->original, $scope);

			$trusted = $scope->hasVariableTaint($variableName) && !$scope->getVariableTaint($variableName)->isTainted();
		}

		$taint = new ScalarTaint(Taint::UNTAINTED);

		if ($op instanceof Op\Expr\Cast\String_ && !$trusted) {
			$taint = new ScalarTaint(Taint::TAINTED);
		}

		$op->setAttribute(Taint::ATTR, $taint);

		if (!$taint->isTainted()) {
			$sanitizers = ['string', 'xss'];
			$op->setAttribute(Taint::ATTR_SANITIZE, $sanitizers);
		}

		return $scope;
	}

	private function setAttributes(Op $node, array $attributes): void
	{
		foreach ($attributes as $key => $value) {
			$node->setAttribute($key, $value);
		}
	}

	public function isSource(Operand $operand, Scope $scope, ?string $section = null): bool
	{
		if ($section === null) {
			$sources = $this->sourceFunctions->getAll();
		} else {
			$sources = $this->sourceFunctions->getSection($section);
		}

		return in_array($this->printer->printOperand($operand, $scope), $sources);
	}

	public function isSink(Operand $operand, Scope $scope, ?string $section = null): bool
	{
		if ($section === null) {
			$sources = $this->sinkFunctions->getAll();
		} else {
			$sources = $this->sinkFunctions->getSection($section);
		}

		return in_array($this->printer->printOperand($operand, $scope), $sources);
	}

	public function isSanitizer(Operand $operand, Scope $scope, ?string $section = null): bool
	{
		if ($section === null) {
			$sources = $this->sanitizerFunctions->getAll();
		} else {
			$sources = $this->sanitizerFunctions->getSection($section);
		}

		return in_array($this->printer->print($operand, $scope), $sources);
	}

	public function isSuperGlobal(Operand\Variable $variable, Scope $scope): bool
	{
		return in_array($this->printer->print($variable, $scope), $this->sourceFunctions->getSection('userinput'));
	}

	public function transferSuperGlobal(Operand\Variable $variable, ?string $dim = null): Taint
	{
		if (
			$variable->name->value === '_SERVER'
			&& (
				$dim === null
				|| !in_array($dim, $this->sourceFunctions->getSection('serverParameters'), true)
			)
		) {
			return new ScalarTaint(Taint::UNTAINTED);
		}

		return new ScalarTaint(Taint::TAINTED);
	}

	private function resolveType($value): Type
	{
		if (is_bool($value)) {
			return $value ? new TrueBooleanType : new FalseBooleanType;
		} elseif (is_integer($value)) {
			return new IntegerType;
		} elseif (is_double($value)) {
			return new FloatType;
		} elseif (is_string($value)) {
			return new StringType;
		}

		return new MixedType;
	}

}
