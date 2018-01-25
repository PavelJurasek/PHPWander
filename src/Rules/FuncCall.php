<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand;
use PHPCfg\Operand\Literal;
use PHPWander\Analyser\Scope;
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

	public function __construct(string $functionName, array $args, array $sanitizers)
	{
		$this->functionName = $functionName;
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
		$name = $node->name instanceof Literal ? $node->name->value : $node;

		if ($name !== $this->functionName) {
			return [];
		}

		if ($this->isTainted((int) $node->getAttribute(Taint::ATTR)) && $node->getAttribute(Taint::ATTR_SINK) !== null) {
			return [
				sprintf('Sensitive sink %s is tainted.', $name),
			];
		}

		foreach ($this->args as $argNumber) {
			if ($argNumber > 0) {
				$arg = $node->args[$argNumber-1];

				if ($this->isArgumentTainted($arg, $scope)) {
					return [
						sprintf('%s argument of sensitive function call %s is tainted.', $this->formatNumber($argNumber), $name),
					];
				}
			}
		}

		return [];
	}

	private function isArgumentTainted(Operand $arg, Scope $scope): bool
	{
		if ($arg instanceof Literal) {
			return false;
		}

		if ($arg instanceof Operand\Variable) {
			return $this->isTainted($scope->getVariableTaint($arg->name->value));
		}

		if ($arg instanceof Operand\Temporary) {
			if ($arg->original instanceof Operand\Variable) {
				return $this->isArgumentTainted($arg->original, $scope);
			}
		}

		if (!empty($arg->ops)) {
			return $this->isTainted($this->decideOpsTaint($arg->ops));
		}

		dump($arg);
		dump(__METHOD__);

		return false;
	}

	private function formatNumber(int $argNumber): string
	{
		return ((string) $argNumber) . '.';
	}

}
