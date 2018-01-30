<?php declare(strict_types=1);

namespace PHPWander\Analyser;

use PHPWander\Taint;
use PHPWander\TransitionFunction;

/**
 * @author Pavel Jurásek
 */
class FuncCallResult
{

	/** @var FuncCallPath[] */
	private $callPaths = [];

	/** @var FuncCallPath[] */
	private $taintingCallPaths = [];

	/** @var int */
	private $taint;

	/** @var TransitionFunction */
	private $transitionFunction;

	public function __construct(TransitionFunction $transitionFunction)
	{
		$this->taint = Taint::UNKNOWN;
		$this->transitionFunction = $transitionFunction;
	}

	public function addPath(FuncCallPath $callPath)
	{
		$this->callPaths[] = $callPath;
		$this->taint = $this->transitionFunction->leastUpperBound($this->taint, $callPath->getTaint());

		if ($this->transitionFunction->isTainted($callPath->getTaint())) {
			$this->taintingCallPaths[] = $callPath;
		}
	}

	public function getTaintingCallPaths(): array
	{
		return $this->taintingCallPaths;
	}

	public function getTaint(): int
	{
		return $this->taint;
	}

}
