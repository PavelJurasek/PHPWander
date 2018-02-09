<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPCfg\Operand\Literal;
use PHPWander\Analyser\Scope;

/**
 * @author Pavel Jurásek
 */
class VariableFunctionCalled extends AbstractRule implements Rule
{

	/**
	 * @return string Class implementing PHPCfg\Op
	 */
	public function getNodeType(): string
	{
		return Op\Expr\FuncCall::class;
	}

	/**
	 * @param Op\Expr\FuncCall $node
	 * @return string[] errors
	 */
	public function processNode(Op $node, Scope $scope): array
	{
		if ($node->name instanceof Operand\Variable) {
			$name = $this->printOperand($node->name, $scope);

			if ($this->isTainted($scope->getVariableTaint($name))) {
				return [
					sprintf('Variable function is called on variable %s.', $this->printOperand($node->name, $scope)),
				];
			}
		}

		return [];
	}

	private function isArgumentTainted(Operand $arg, Scope $scope): bool
	{
		if ($arg instanceof Operand\Variable) {
			return $this->isTainted($scope->getVariableTaint($arg->name->value));
		} if ($arg instanceof Operand\Temporary) {
			if ($arg->original instanceof Operand\Variable) {
				return $this->isArgumentTainted($arg->original, $scope);
			}
		}

		return true;
	}

	private function formatNumber(int $argNumber): string
	{
		return ((string) $argNumber) . '.';
	}

}
