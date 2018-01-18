<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPWander\SinkFunctions;

/**
 * @author Pavel Jurásek
 */
class FuncCallFactory
{

	/** @var SinkFunctions */
	private $sinkFunctions;

	public function __construct(SinkFunctions $sinkFunctions)
	{
		$this->sinkFunctions = $sinkFunctions;
	}

	public function create(Registry $registry): void
	{
		foreach ($this->sinkFunctions->getAll() as $functionName => $params) {
			$registry->addRule(new FuncCall($functionName, $params[0], $params[1]));
		}
	}

}
