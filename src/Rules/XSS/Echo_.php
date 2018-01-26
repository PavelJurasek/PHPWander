<?php declare(strict_types=1);

namespace PHPWander\Rules\XSS;

use PHPCfg\Op;
use PHPCfg\Operand\Temporary;
use PHPCfg\Operand\Variable;
use PHPWander\Analyser\Helpers;
use PHPWander\Analyser\Scope;
use PHPWander\Rules\AbstractRule;
use PHPWander\Rules\Rule;
use PHPWander\Taint;

/**
 * @author Pavel Jurásek
 */
class Echo_ extends AbstractRule implements Rule
{

	/**
	 * @return string Class implementing PHPCfg\Op
	 */
	public function getNodeType(): string
	{
		return Op\Terminal\Echo_::class;
	}

	/**
	 * @param Op\Terminal\Echo_ $node
	 * @return string[] errors
	 */
	public function processNode(Op $node, Scope $scope): array
	{
		if ($node->expr instanceof Temporary) {
			if ($node->expr->original instanceof Variable) {
				$variable = Helpers::unwrapOperand($node->expr);

				if ($scope->getVariableTaint($variable) === Taint::TAINTED) {
//				if (
//					in_array('string', (array) $variable->ops[0]->getAttribute(Taint::ATTR_TAINT), true) ||
//					in_array('userinput', (array) $variable->ops[0]->getAttribute(Taint::ATTR_SOURCE), true) ||
//					!in_array('xss', (array) $variable->ops[0]->getAttribute(Taint::ATTR_SANITIZE), true)
//				) {
					return [
						sprintf('Echo is tainted by %s.', $this->describeOp($node->expr->ops[0], $scope)),
					];
				}
			}

			$name = Helpers::unwrapOperand($node->expr);

			if ($scope->hasVariableTaint($name)) {
				if ($this->isTainted($scope->getVariableTaint($name))) {
					return [
						sprintf('Echo is tainted by %s.', $this->unwrapOperand($node->expr, $scope)),
					];
				}
			}

			if ($node->expr->ops) {
				/** @var Op $op */
				foreach ($node->expr->ops as $op) {
					if ($this->isTainted((int) $op->getAttribute(Taint::ATTR))) {
						return [
							sprintf('Echo is tainted by %s.', $this->describeOp($op, $scope)),
						];
					}
				}
			}
		}

		return [];
	}

}
