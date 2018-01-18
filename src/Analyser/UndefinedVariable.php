<?php declare(strict_types=1);

namespace PHPWander\Analyser;

/**
 * @author Pavel Jurásek
 */
class UndefinedVariable extends \Exception
{

	/** @var Scope */
	private $scope;

	/** @var string */
	private $variableName;

	public function __construct(Scope $scope, string $variableName)
	{
		parent::__construct(sprintf('Undefined variable: $%s', $variableName));
		$this->scope = $scope;
		$this->variableName = $variableName;
	}

	public function getScope(): Scope
	{
		return $this->scope;
	}

	public function getVariableName(): string
	{
		return $this->variableName;
	}

}
