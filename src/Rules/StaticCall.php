<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPCfg\Op;
use PHPCfg\Operand\Literal;
use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\FuncCallStorage;
use PHPWander\Analyser\Scope;
use PHPWander\Describer\Describer;

/**
 * @author Pavel Jurásek
 */
class StaticCall extends MethodCall implements Rule
{

	public function __construct(Describer $describer, BlockScopeStorage $blockScopeStorage, FuncCallStorage $funcCallStorage, string $name, array $args, array $sanitizers)
	{
		parent::__construct($describer, $blockScopeStorage, $funcCallStorage, $name, $args, $sanitizers);

		list($this->className, $this->methodName) = explode('::', $name);
	}

	/**
	 * @return string Class implementing PHPCfg\Op
	 */
	public function getNodeType(): string
	{
		return Op\Expr\StaticCall::class;
	}

	/**
	 * @param Op\Expr\StaticCall $node
	 * @return string[]
	 */
	public function processNode(Op $node, Scope $scope): array
	{
		if ($this->className !== $node->class->value) {
			return [];
		}

		$name = $node->name instanceof Literal ? $node->name->value : $node;

		if ($name !== $this->methodName) {
			return [];
		}

		return $this->checkTaints($node, $scope, $this->printOp($node, $scope), 'static');
	}

}
