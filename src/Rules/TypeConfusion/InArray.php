<?php declare(strict_types=1);

namespace PHPWander\Rules\TypeConfusion;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPWander\Analyser\Scope;
use PHPWander\Rules\AbstractRule;
use PHPWander\Rules\Rule;

/**
 * @author Pavel Jurásek
 */
class InArray extends AbstractRule implements Rule
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
		if ($node->name instanceof Operand\Literal) {
			$name = $node->name->value;

			if ($name === 'in_array') {
				if (!array_key_exists(2, $node->args) || $this->isArgumentFalse($node->args[2], $scope)) {
					return [
						sprintf('Type confusion is possible as comparison is not strict.'),
					];
				}
			}
		}

		return [];
	}

	private function isArgumentFalse($arg, Scope $scope): bool
	{
		if ($arg instanceof Operand\Variable) {
			return true;
		} elseif ($arg instanceof Operand\Temporary) {
			if (!empty($arg->ops)) {
				return $this->isArgumentFalse($arg->ops[0], $scope);
			}

			return $this->isArgumentFalse($arg->original, $scope);
		} elseif ($arg instanceof Op\Expr\ConstFetch) {
			return $this->isArgumentFalse($arg->name, $scope);
		} elseif ($arg instanceof Operand\Literal) {
			return $arg->value === 'false';
		}

		return true;
	}

}
