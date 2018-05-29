<?php declare(strict_types=1);

namespace PHPWander\DI;

use Nette\DI\CompilerExtension;

/**
 * @author Pavel Jurásek
 */
class ExtraExtension extends CompilerExtension
{

	/** @var array */
	private $defaults = [];

	/** @var string */
	private $definitionName;

	/** @var string */
	private $constructorArgumentName;

	public function __construct(string $definitionName, string $constructorArgumentName)
	{
		$this->definitionName = $definitionName;
		$this->constructorArgumentName = $constructorArgumentName;
	}

	public function beforeCompile()
	{
		$config = $this->getConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		$definition = $builder->getDefinition($this->definitionName);

		$definition->getFactory()->arguments[$this->constructorArgumentName] = $config;
	}

}
