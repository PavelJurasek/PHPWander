<?php declare(strict_types=1);

namespace PHPWander\Rules;

use Nette\Utils\Strings;
use PHPCfg\Op;
use PHPCfg\Operand\Literal;
use PHPStan\Type\TypeWithClassName;
use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\FuncCallStorage;
use PHPWander\Analyser\Scope;
use PHPWander\Describer\Describer;
use PHPWander\VectorTaint;

/**
 * @author Pavel Jurásek
 */
class MethodCall extends FuncCall implements Rule
{

	/** @var string */
	protected $className;

	/** @var string */
	protected $methodName;

	public function __construct(Describer $describer, BlockScopeStorage $blockScopeStorage, FuncCallStorage $funcCallStorage, string $name, array $args, array $sanitizers)
	{
		parent::__construct($describer, $blockScopeStorage, $funcCallStorage, $name, $args, $sanitizers);

		if (Strings::contains($name, '->')) {
			list($this->className, $this->methodName) = explode('->', $name);
		}
	}

	/**
	 * @return string Class implementing PHPCfg\Op
	 */
	public function getNodeType(): string
	{
		return Op\Expr\MethodCall::class;
	}

	/**
	 * @param Op\Expr\MethodCall $node
	 * @return string[]
	 */
	public function processNode(Op $node, Scope $scope): array
	{
		$calledVariable = $this->printOperand($node->var, $scope);
		$variableTaint = $scope->getVariableTaint($calledVariable);

		if (!$variableTaint instanceof VectorTaint || !$variableTaint->getType() instanceof TypeWithClassName) {
			return [
				sprintf('Type of variable %s is not known.', $calledVariable),
			];
		}

		/** @var TypeWithClassName $variableType */
		$variableType = $variableTaint->getType();

		if ($this->className !== $variableType->getClassName()) {
			return [];
		}

		$name = $node->name instanceof Literal ? $node->name->value : $node;

		if ($name !== $this->methodName) {
			return [];
		}

		return $this->checkTaints($node, $scope, $this->printOp($node, $scope), 'method');
	}

}
