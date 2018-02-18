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
class Print_ extends AbstractRule implements Rule
{

	/**
	 * @return string Class implementing PHPCfg\Op
	 */
	public function getNodeType(): string
	{
		return Op\Expr\Print_::class;
	}

	/**
	 * @param Op\Expr\Print_ $node
	 * @return string[] errors
	 */
	public function processNode(Op $node, Scope $scope): array
	{
		if ($node->expr instanceof Temporary) {
			if ($node->expr->original instanceof Variable) {
				$variable = $node->expr->original;

				if ($scope->getVariableTaint($variable->name->value)->isTainted()) {
					return [
						sprintf('Print is tainted by %s.', $this->describeOp($node->expr->ops[0], $scope)),
					];
				}
			}

			if ($node->expr->ops) {
				/** @var Op $op */
				foreach ($node->expr->ops as $op) {
					if ($op->getAttribute(Taint::ATTR, new ScalarTaint(Taint::UNKNOWN))->isTainted()) {
						return [
							sprintf('Print is tainted by %s.', $this->describeOp($op, $scope)),
						];
					}
				}
			}
		}

		return [];
	}

}
