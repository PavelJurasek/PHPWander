<?php declare(strict_types=1);

namespace PHPWander\Rules;

use PHPWander\Analyser\BlockScopeStorage;
use PHPWander\Analyser\FuncCallStorage;
use PHPWander\Describer\Describer;
use PHPWander\SinkFunctions;

/**
 * @author Pavel Jurásek
 */
class FuncCallFactory
{

	/** @var SinkFunctions */
	private $sinkFunctions;

	/** @var Describer */
	private $describer;

	/** @var BlockScopeStorage */
	private $blockScopeStorage;

	/** @var FuncCallStorage */
	private $funcCallStorage;

	public function __construct(SinkFunctions $sinkFunctions, Describer $describer, BlockScopeStorage $blockScopeStorage, FuncCallStorage $funcCallStorage)
	{
		$this->sinkFunctions = $sinkFunctions;
		$this->describer = $describer;
		$this->blockScopeStorage = $blockScopeStorage;
		$this->funcCallStorage = $funcCallStorage;
	}

	public function create(Registry $registry): void
	{
		foreach ($this->sinkFunctions->getAll() as $functionName => $params) {
			$registry->addRule(new FuncCall($this->describer, $this->blockScopeStorage, $this->funcCallStorage, $functionName, $params[0], $params[1]));
		}
	}

}
