<?php declare(strict_types=1);

namespace PHPWander\Rules\XSS;

use PHPCfg\Op;
use PHPCfg\Operand\Temporary;
use PHPCfg\Operand\Variable;
use PHPWander\Analyser\Scope;
use PHPWander\Rules\AbstractRule;
use PHPWander\Rules\Rule;
use PHPWander\ScalarTaint;
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
				$variable = $this->printOperand($node->expr, $scope);

				if ($scope->getVariableTaint($variable)->isTainted()) {
//				if (
//					in_array('string', (array) $variable->ops[0]->getAttribute(Taint::ATTR_TAINT), true) ||
//					in_array('userinput', (array) $variable->ops[0]->getAttribute(Taint::ATTR_SOURCE), true) ||
//					!in_array('xss', (array) $variable->ops[0]->getAttribute(Taint::ATTR_SANITIZE), true)
//				) {
					return [$this->describeTaint($node->expr->ops[0], $scope)];
				}
			}

			if ($node->expr->ops) {
				/** @var Op $op */
				foreach ($node->expr->ops as $op) {
					if ($op->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN))->isTainted()) {
						return [$this->describeTaint($op, $scope)];
					}
				}
			}
		}

		return [];
	}

	private function describeTaint(Op $op, Scope $scope): string
	{
		$str = sprintf('Echo is tainted by %s', $this->describeOp($op, $scope));

		foreach ($scope->getStatementStack() as $statement) {
			$str .= sprintf(' %s%s', $scope->isNegated() ? 'not ': '', $this->describeOp($statement, $scope));
		}

		return $str . '.';
	}

}
