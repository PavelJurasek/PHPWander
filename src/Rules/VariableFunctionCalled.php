<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand;
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

			if ($scope->getVariableTaint($name)->isTainted()) {
				return [
					sprintf('Variable function is called on variable %s.', $this->printOperand($node->name, $scope)),
				];
			}
		}

		return [];
	}

}
