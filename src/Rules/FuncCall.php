<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPCfg\Operand\Literal;
use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\FuncCallStorage;
use PHPWander\Analyser\Scope;
use PHPWander\Describer\Describer;
use PHPWander\ScalarTaint;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
class FuncCall extends AbstractRule implements Rule
{

	/** @var string */
	private $functionName;

	/** @var array */
	private $args;

	/** @var array */
	private $sanitizers;

	public function __construct(Describer $describer, BlockScopeStorage $blockScopeStorage, FuncCallStorage $funcCallStorage, string $name, array $args, array $sanitizers)
	{
		parent::__construct($describer, $blockScopeStorage, $funcCallStorage);

		$this->functionName = $name;
		$this->args = $args;
		$this->sanitizers = $sanitizers;
	}

	/**
	 * @return string Class implementing PHPCfg\Op
	 */
	public function getNodeType(): string
	{
		return Op\Expr\FuncCall::class;
	}

	/**
	 * @param Op\Expr\FuncCall $node
	 * @return string[]
	 */
	public function processNode(Op $node, Scope $scope): array
	{
		$name = $node->name instanceof Literal ? $node->name->value : get_class($node);

		if ($name !== $this->functionName) {
			return [];
		}

		return $this->checkTaints($node, $scope, $name, 'function');
	}

	/**
	 * @param Op\Expr\FuncCall|Op\Expr\MethodCall|Op\Expr\StaticCall $node
	 * @return string[]
	 */
	protected function checkTaints(Op $node, Scope $scope, string $name, ?string $description = null): array
	{
		if ($node->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN))->isTainted() && $node->getAttribute(Taint::ATTR_SINK) !== null) {
			return [
				sprintf('Sensitive sink %s is tainted.', $name),
			];
		}

		foreach ($this->args as $argNumber) {
			if ($argNumber > 0) {
				if (!array_key_exists($argNumber-1, $node->args)) {
					break; // optional argument
				}

				$arg = $node->args[$argNumber-1];

				if ($this->isArgumentTainted($arg, $scope)) {
					return [
						sprintf('%s argument of sensitive%s call %s is tainted.%s', $this->formatNumber($argNumber), $description ? " $description" : '', $name, $this->describeTaint($node, $scope)),
					];
				}
			} elseif ($argNumber === 0) {
				foreach ($node->args as $arg) {
					if ($this->isArgumentTainted($arg, $scope)) {
						return [
							sprintf('Output of sensitive%s call %s is tainted.%s', $description ? " $description" : '', $name, $this->describeTaint($node, $scope)),
						];
					}
				}
			}
		}

		return [];
	}

	protected function isArgumentTainted(Operand $arg, Scope $scope): bool
	{
		if ($arg instanceof Literal) {
			return false;
		}

		if ($arg instanceof Operand\Variable) {
			return $scope->getVariableTaint($this->printOperand($arg, $scope))->isTainted();
		}

		if ($arg instanceof Operand\Temporary) {
			if ($arg->original instanceof Operand\Variable) {
				return $this->isArgumentTainted($arg->original, $scope);
			}
		}

		if (!empty($arg->ops)) {
			return $this->decideOpsTaint($arg->ops)->isTainted();
		}

		dump($arg);
		dump(__METHOD__);

		return false;
	}

	protected function formatNumber(int $argNumber): string
	{
		return ((string) $argNumber) . '.';
	}

	private function describeTaint(Op $op, Scope $scope): string
	{
		if (count($scope->getStatementStack()) === 0) {
			return '';
		}

		$str = ' (';

		foreach ($scope->getStatementStack() as $statement) {
			$str .= sprintf(' %s%s', $scope->isNegated() ? 'not ': '', $this->describeOp($statement, $scope));
		}

		return $str . ')';
	}

}
