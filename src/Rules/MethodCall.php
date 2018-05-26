<?php declare(strict_types=1);

namespace PHPWander\Rules;

use Nette\Utils\Strings;
use PHPCfg\Op;
use PHPCfg\Operand\Literal;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType;
use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\FuncCallStorage;
use PHPWander\Analyser\Scope;
use PHPWander\Describer\Describer;

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
		$type = $variableTaint->getType();

		if (!$type instanceof TypeWithClassName && !($type instanceof UnionType && count($type->getReferencedClasses()) > 0)) {
			return [
				sprintf('Type of variable %s is not known.', $calledVariable),
			];
		}

		if ($type instanceof TypeWithClassName) {
			$classNames = [$type->getClassName()];
		} else {
			$classNames = $type->getReferencedClasses();
		}

		if (!in_array($this->className, $classNames, true)) {
			return [];
		}

		$name = $node->name instanceof Literal ? $node->name->value : $node;

		if ($name !== $this->methodName) {
			return [];
		}

		return $this->checkTaints($node, $scope, $this->printOp($node, $scope), 'method');
	}

}
