<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\SinkFunctions;

/**
 * @author Pavel Jurásek
 */
class FuncCallFactory
{

	/** @var SinkFunctions */
	private $sinkFunctions;

	/** @var BlockScopeStorage */
	private $blockScopeStorage;

	public function __construct(SinkFunctions $sinkFunctions, BlockScopeStorage $blockScopeStorage)
	{
		$this->sinkFunctions = $sinkFunctions;
		$this->blockScopeStorage = $blockScopeStorage;
	}

	public function create(Registry $registry): void
	{
		foreach ($this->sinkFunctions->getAll() as $functionName => $params) {
			$registry->addRule(new FuncCall($this->blockScopeStorage, $functionName, $params[0], $params[1]));
		}
	}

}
